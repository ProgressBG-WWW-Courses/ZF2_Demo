#!/usr/bin/env python3
"""
End-to-end Revolut Payment test using Playwright.

Tests both success and declined payment flows via Revolut Hosted Checkout Page.
The user clicks "Pay" on the room detail page, gets redirected to Revolut's
checkout, fills in card details there, and gets redirected back.

  - Success card: 4929420573595709
  - Declined card: 4929573638125985

Usage:
  python3 test_payment.py success
  python3 test_payment.py declined
  python3 test_payment.py both
"""

import sys
import os
import json
import time
import subprocess
import urllib.request
import urllib.error

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
APP_URL  = 'http://localhost:8088'

SUCCESS_CARD  = '4929420573595709'
DECLINED_CARD = '4929573638125985'


def load_env():
    env = {}
    with open(os.path.join(BASE_DIR, '.env')) as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith('#') or '=' not in line:
                continue
            k, v = line.split('=', 1)
            env[k.strip()] = v.strip()
    return env


def api_request(method, path, body=None):
    env = load_env()
    url = env['REVOLUT_API_URL'].rstrip('/') + path
    headers = {
        'Authorization': f'Bearer {env["REVOLUT_API_SECRET_KEY"]}',
        'Accept': 'application/json',
        'Revolut-Api-Version': '2024-09-01',
    }
    data = None
    if body:
        headers['Content-Type'] = 'application/json'
        data = json.dumps(body).encode()
    req = urllib.request.Request(url, data=data, headers=headers, method=method)
    resp = urllib.request.urlopen(req)
    return json.loads(resp.read())


def docker_write_payments(payments):
    """Write payments.json via Docker exec to avoid permission issues."""
    escaped = json.dumps(payments).replace("'", "'\\''")
    subprocess.run(
        ['docker', 'compose', 'exec', '-T', 'app',
         'sh', '-c', f"echo '{escaped}' > /var/www/html/data/payments.json"],
        capture_output=True
    )


def load_payments():
    sf = os.path.join(BASE_DIR, 'data', 'payments.json')
    if os.path.exists(sf):
        try:
            with open(sf) as f:
                return json.load(f)
        except (json.JSONDecodeError, IOError):
            pass
    return {}


def update_local_payment(order_id, state):
    payments = load_payments()
    if order_id in payments:
        payments[order_id]['state'] = state
        payments[order_id]['updated_at'] = time.strftime('%Y-%m-%dT%H:%M:%S+00:00')
        docker_write_payments(payments)


def get_latest_order_for_room(room_id):
    """Read payments.json and return the most recent order for a room."""
    payments = load_payments()
    room_payments = [p for p in payments.values() if int(p.get('room_id', 0)) == room_id]
    if not room_payments:
        return None
    room_payments.sort(key=lambda p: p.get('created_at', ''), reverse=True)
    return room_payments[0]


def poll_order_status(order_id, expected_states, timeout=90):
    print(f'  Polling for states: {expected_states}')
    start = time.time()
    last_state = 'UNKNOWN'
    while time.time() - start < timeout:
        order = api_request('GET', f'/api/orders/{order_id}')
        last_state = order.get('state', 'UNKNOWN').upper()

        # Revolut keeps the order PENDING even after declined payments
        payments = order.get('payments', [])
        has_declined = any(p.get('state', '').upper() == 'DECLINED' for p in payments)

        effective_state = last_state
        if has_declined and last_state == 'PENDING':
            effective_state = 'FAILED'

        print(f'    Order state: {last_state}, effective: {effective_state}')
        if effective_state in expected_states:
            order['state'] = effective_state
            return order
        time.sleep(4)
    raise TimeoutError(f'Order did not reach {expected_states} within {timeout}s (last: {last_state})')


def verify_room_ui(room_id, expected_state):
    url = f'{APP_URL}/room/detail/{room_id}'
    resp = urllib.request.urlopen(url)
    html = resp.read().decode()

    markers = {
        'COMPLETED':  'Payment Successful',
        'FAILED':     'Payment Declined',
        'CANCELLED':  'Payment Cancelled',
    }
    expected_text = markers.get(expected_state, expected_state)

    if expected_text in html:
        print(f'  UI PASSED: "{expected_text}" found')
        return True
    else:
        print(f'  UI FAILED: "{expected_text}" NOT found in page')
        for label in markers.values():
            if label in html:
                print(f'    (found: "{label}" instead)')
        return False


def complete_hosted_checkout(room_id, card_number):
    """
    Start on the room detail page, click Pay, get redirected to Revolut's
    hosted checkout page, fill card details, submit, and wait for redirect back.
    """
    from playwright.sync_api import sync_playwright

    room_url = f'{APP_URL}/room/detail/{room_id}'
    print(f'  Room page: {room_url}')
    print(f'  Card: {card_number}')

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        ctx = browser.new_context(
            viewport={'width': 1280, 'height': 720},
            ignore_https_errors=True,
        )
        page = ctx.new_page()

        try:
            # Step 1: Load room detail page and click Pay
            page.goto(room_url, wait_until='networkidle', timeout=30000)
            time.sleep(2)

            submit_btn = page.query_selector('button[type="submit"]')
            if not submit_btn:
                page.screenshot(path=os.path.join(BASE_DIR, 'data', f'room{room_id}_no_pay.png'))
                raise RuntimeError('Pay button not found on room detail page')

            # Click the form submit — this POSTs to /payment/create
            # which redirects to Revolut's hosted checkout
            submit_btn.click()
            print('  Clicked Pay — waiting for Revolut checkout page...')

            # Step 2: Wait for redirect to Revolut checkout page
            # Use domcontentloaded since Revolut's page loads many external resources
            for i in range(30):
                time.sleep(1)
                if 'checkout.revolut.com' in page.url or 'revolut.com' in page.url:
                    break
            print(f'  Redirected to: {page.url[:80]}')
            time.sleep(8)

            page.screenshot(path=os.path.join(BASE_DIR, 'data', f'room{room_id}_checkout.png'))

            # Step 3: Click "Pay with card" if present (Revolut shows payment method selection)
            pay_card_btn = page.query_selector('button:has-text("Pay with card")')
            if pay_card_btn:
                pay_card_btn.click()
                print('  Clicked "Pay with card"')
                time.sleep(5)
            else:
                print('  No "Pay with card" button (card form may already be shown)')

            # Step 4: Find the card-field iframe
            card_frame = None
            for attempt in range(15):
                for f in page.frames:
                    if 'card-field' in f.url:
                        card_frame = f
                        break
                if card_frame:
                    break
                # Also try finding via DOM
                iframe_el = page.query_selector('iframe[data-testid="card-field-iframe"]')
                if iframe_el:
                    cf = iframe_el.content_frame()
                    if cf:
                        card_frame = cf
                        break
                time.sleep(1)

            if not card_frame:
                page.screenshot(path=os.path.join(BASE_DIR, 'data', f'room{room_id}_no_card_frame.png'))
                print('  Available frames:')
                for f in page.frames:
                    print(f'    {f.url[:120]}')
                raise RuntimeError('Card field iframe not found on checkout page')

            print('  Found card field iframe')
            time.sleep(2)

            # Step 5: Fill card details
            # Card number
            num_input = card_frame.wait_for_selector('input[name="number"], input[name="cardNumber"]', timeout=10000)
            num_input.click()
            num_input.type(card_number, delay=80)
            print('  Filled card number')
            time.sleep(1)

            # Expiry
            exp_input = card_frame.wait_for_selector('input[name="expiry"], input[name="expiryDate"]', timeout=5000)
            exp_input.click()
            exp_input.type('1229', delay=80)
            print('  Filled expiry')
            time.sleep(1)

            # CVV
            cvv_input = card_frame.wait_for_selector('input[name="code"], input[name="cvv"]', timeout=5000)
            cvv_input.click()
            cvv_input.type('123', delay=80)
            print('  Filled CVV')
            time.sleep(1)

            # Postcode (if visible)
            try:
                post_input = card_frame.wait_for_selector('input[name="postcode"]', timeout=2000)
                if post_input and post_input.is_visible():
                    post_input.click()
                    post_input.type('SW1A 1AA', delay=30)
                    print('  Filled postcode')
            except Exception:
                print('  Postcode field hidden — skipping')
            time.sleep(0.5)

            # Cardholder name (on main page, not in iframe)
            name_input = page.query_selector('input[placeholder="Cardholder name"]')
            if name_input:
                name_input.click()
                name_input.fill('Test User')
                print('  Filled cardholder name')
                time.sleep(0.5)

            # Email (on main page)
            email_input = page.query_selector('input[placeholder="Email address"]')
            if email_input:
                email_input.click()
                email_input.fill('test@example.com')
                print('  Filled email')
                time.sleep(0.5)

            page.screenshot(path=os.path.join(BASE_DIR, 'data', f'room{room_id}_filled.png'))

            # Step 6: Click the submit/Pay button on Revolut's page
            pay_submit = page.query_selector('button[type="submit"]')
            if pay_submit:
                pay_submit.click()
                print('  Clicked submit/Pay on checkout page')
            else:
                raise RuntimeError('Submit button not found on checkout page')

            # Step 7: Wait for payment processing and redirect back to our site
            print('  Waiting for payment to process and redirect back...')
            redirected_back = False
            for i in range(120):
                time.sleep(1)
                url = page.url

                # Ngrok free-tier shows an interstitial "Visit Site" page
                if 'ngrok' in url:
                    visit_btn = page.query_selector('button:has-text("Visit Site")')
                    if visit_btn:
                        visit_btn.click()
                        print(f'  Clicked ngrok "Visit Site" after {i + 1}s')
                        time.sleep(3)
                        continue
                    # If ngrok URL but no interstitial, we're through
                    if 'room/detail' in url:
                        redirected_back = True
                        print(f'  Redirected back (via ngrok) after {i + 1}s: {url}')
                        break

                if 'localhost' in url and 'room/detail' in url:
                    redirected_back = True
                    print(f'  Redirected back after {i + 1}s: {url}')
                    break
            if not redirected_back:
                # Revolut sandbox can take ~2 min before redirecting; give it extra time
                time.sleep(15)
                url = page.url
                # Handle ngrok interstitial if needed
                if 'ngrok' in url:
                    visit_btn = page.query_selector('button:has-text("Visit Site")')
                    if visit_btn:
                        visit_btn.click()
                        time.sleep(5)
                        url = page.url
                if 'room/detail' in url:
                    redirected_back = True
                    print(f'  Redirected back (late): {url}')
                else:
                    print(f'  Not redirected (stayed on Revolut): {url}')

            page.screenshot(path=os.path.join(BASE_DIR, 'data', f'room{room_id}_result.png'))
            print(f'  Final URL: {page.url}')

        except Exception as e:
            page.screenshot(path=os.path.join(BASE_DIR, 'data', f'room{room_id}_error.png'))
            print(f'  ERROR during checkout: {e}')
            raise
        finally:
            browser.close()


def test_success_flow():
    print('\n' + '='*60)
    print('SUCCESS FLOW')
    print('='*60)

    room_id = 1

    print('\n1. Starting checkout with success card...')
    complete_hosted_checkout(room_id, SUCCESS_CARD)

    print('\n2. Finding order ID from local storage...')
    payment = get_latest_order_for_room(room_id)
    if not payment:
        print('   ERROR: No payment found in local storage for room')
        return False
    order_id = payment['order_id']
    print(f'   Order: {order_id}')

    print('\n3. Polling order status...')
    try:
        final = poll_order_status(order_id, ['COMPLETED'], timeout=90)
        state = final['state']
    except TimeoutError as e:
        print(f'   {e}')
        state = 'TIMEOUT'

    print(f'   Final state: {state}')
    update_local_payment(order_id, state)

    print('\n4. Verifying UI...')
    ok = verify_room_ui(room_id, 'COMPLETED')

    status = 'PASSED' if ok else 'FAILED'
    print(f'\n>>> SUCCESS FLOW: {status}\n')
    return ok


def test_declined_flow():
    print('\n' + '='*60)
    print('DECLINED FLOW')
    print('='*60)

    room_id = 2

    print('\n1. Starting checkout with declined card...')
    complete_hosted_checkout(room_id, DECLINED_CARD)

    print('\n2. Finding order ID from local storage...')
    payment = get_latest_order_for_room(room_id)
    if not payment:
        print('   ERROR: No payment found in local storage for room')
        return False
    order_id = payment['order_id']
    print(f'   Order: {order_id}')

    print('\n3. Polling order status...')
    try:
        final = poll_order_status(order_id, ['FAILED', 'CANCELLED'], timeout=90)
        state = final['state']
    except TimeoutError as e:
        print(f'   {e}')
        state = 'TIMEOUT'

    print(f'   Final state: {state}')
    update_local_payment(order_id, state)

    print('\n4. Verifying UI...')
    ok = verify_room_ui(room_id, 'FAILED')

    status = 'PASSED' if ok else 'FAILED'
    print(f'\n>>> DECLINED FLOW: {status}\n')
    return ok


if __name__ == '__main__':
    mode = sys.argv[1] if len(sys.argv) > 1 else 'both'
    os.makedirs(os.path.join(BASE_DIR, 'data'), exist_ok=True)

    # Clear previous payments for clean test
    docker_write_payments({})

    results = {}
    if mode in ('success', 'both'):
        results['success'] = test_success_flow()
    if mode in ('declined', 'both'):
        results['declined'] = test_declined_flow()

    print('\n' + '='*60)
    print('FINAL RESULTS')
    print('='*60)
    for name, passed in results.items():
        print(f'  {name}: {"PASSED" if passed else "FAILED"}')
    print('='*60)
    sys.exit(0 if all(results.values()) else 1)
