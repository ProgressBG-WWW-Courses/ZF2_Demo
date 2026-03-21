#!/usr/bin/env python3
"""
End-to-end Revolut Payment test using Playwright.

Tests both success and declined payment flows using the embedded card field widget
(RevolutCheckout.js createCardField) rendered on the room detail page.

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
        order = api_request('GET', f'/api/1.0/orders/{order_id}')
        last_state = order.get('state', 'UNKNOWN')

        # Revolut keeps the order PENDING even after declined payments
        payments = order.get('payments', [])
        has_declined = any(p.get('state') == 'DECLINED' for p in payments)

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


def pay_on_room_page(room_id, card_number):
    """
    Navigate to room detail page, use the embedded card field to pay.

    Flow:
      1. Go to /room/detail/{room_id}
      2. Fill email and name
      3. Click "Pay" → AJAX creates order, SDK loads card field iframe
      4. Fill card details in the iframe (inputs: number, expiry, code)
      5. Click "Pay" again → SDK submits the payment
      6. Wait for result (page reload on success, error message on failure)
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
            page.goto(room_url, wait_until='networkidle', timeout=30000)
            time.sleep(2)

            # Verify pay section is visible
            pay_btn = page.query_selector('#pay-btn')
            if not pay_btn:
                page.screenshot(path=os.path.join(BASE_DIR, 'data', f'room{room_id}_no_pay_btn.png'))
                raise RuntimeError('Pay button not found — is there a stale payment blocking the form?')

            # Fill email and name
            page.fill('#customer-email', 'test@example.com')
            page.fill('#customer-name', 'Test User')

            # Click Pay to create order and load card field
            pay_btn.click()
            print('  Clicked Pay (creating order...)')

            # Wait for card-field iframe to appear
            card_frame = None
            for attempt in range(20):
                time.sleep(1)
                for f in page.frames:
                    if f == page.main_frame:
                        continue
                    # Match card-field iframe by URL or by checking for card inputs
                    if 'card-field' in f.url or 'revolut' in f.url:
                        card_frame = f
                        break
                if card_frame:
                    print(f'  Card iframe loaded after {attempt + 1}s')
                    break
                # Also check by looking for iframe inside #card-field div
                if not card_frame:
                    iframe_el = page.query_selector('#card-field iframe')
                    if iframe_el:
                        cf = iframe_el.content_frame()
                        if cf:
                            card_frame = cf
                            print(f'  Card iframe found via DOM after {attempt + 1}s')
                            break

            if not card_frame:
                # Last resort: try any non-main frame
                for f in page.frames:
                    if f != page.main_frame and f.url != 'about:blank':
                        card_frame = f
                        print(f'  Using fallback frame: {f.url[:80]}')
                        break

            if not card_frame:
                page.screenshot(path=os.path.join(BASE_DIR, 'data', f'room{room_id}_no_iframe.png'))
                print('  Available frames:')
                for f in page.frames:
                    print(f'    {f.url[:120]}')
                raise RuntimeError('Card field iframe did not appear')

            # Wait for inputs inside the iframe to render
            time.sleep(3)

            # Fill card number (Revolut iframe input name="number")
            num_input = card_frame.wait_for_selector('input[name="number"]', timeout=10000)
            num_input.click()
            num_input.type(card_number, delay=80)
            print('  Filled card number')
            time.sleep(1)

            # Fill expiry (input name="expiry")
            exp_input = card_frame.wait_for_selector('input[name="expiry"]', timeout=5000)
            exp_input.click()
            exp_input.type('1229', delay=80)
            print('  Filled expiry')
            time.sleep(1)

            # Fill CVV (input name="code")
            cvv_input = card_frame.wait_for_selector('input[name="code"]', timeout=5000)
            cvv_input.click()
            cvv_input.type('123', delay=80)
            print('  Filled CVV')
            time.sleep(1)

            # Fill postcode if visible
            try:
                post_input = card_frame.wait_for_selector('input[name="postcode"]', timeout=2000)
                if post_input and post_input.is_visible():
                    post_input.click()
                    post_input.type('SW1A 1AA', delay=30)
                    print('  Filled postcode')
            except Exception:
                print('  Postcode field hidden — skipping')

            page.screenshot(path=os.path.join(BASE_DIR, 'data', f'room{room_id}_card_filled.png'))

            # Click Pay to submit the payment
            pay_btn = page.query_selector('#pay-btn')
            if not pay_btn:
                raise RuntimeError('Pay button not found for submission')
            pay_btn.click()
            print('  Clicked Pay (submitting payment...)')

            # Wait for result
            result = None
            for i in range(45):
                time.sleep(1)

                # Check if page reloaded with payment status
                content = page.content()
                if 'Payment Successful' in content:
                    result = 'SUCCESS'
                    break
                if 'Payment Declined' in content:
                    result = 'DECLINED'
                    break

                # Check for inline error
                err_el = page.query_selector('#card-errors')
                if err_el and err_el.is_visible():
                    txt = err_el.text_content()
                    if txt:
                        result = f'ERROR: {txt}'
                        print(f'  Card error: {txt}')
                        break

                # Check spinner for success message (before page reload)
                spinner = page.query_selector('#pay-spinner')
                if spinner and spinner.is_visible():
                    stxt = spinner.text_content()
                    if 'successful' in stxt.lower():
                        result = 'SUCCESS_REFRESHING'
                        print('  Payment successful, waiting for page reload...')
                        time.sleep(5)
                        break

            if result is None:
                result = 'TIMEOUT'
                print('  Timed out waiting for payment result')

            print(f'  Result: {result}')
            page.screenshot(path=os.path.join(BASE_DIR, 'data', f'room{room_id}_result.png'))

        except Exception as e:
            page.screenshot(path=os.path.join(BASE_DIR, 'data', f'room{room_id}_error.png'))
            print(f'  ERROR during payment: {e}')
            raise
        finally:
            browser.close()


def test_success_flow():
    print('\n' + '='*60)
    print('SUCCESS FLOW')
    print('='*60)

    room_id = 1

    print('\n1. Paying on room detail page with success card...')
    pay_on_room_page(room_id, SUCCESS_CARD)

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

    print('\n1. Paying on room detail page with declined card...')
    pay_on_room_page(room_id, DECLINED_CARD)

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
