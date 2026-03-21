#!/usr/bin/env python3
"""
End-to-end Revolut Payment test using Playwright.

Tests both success and declined payment flows:
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


def create_order(room_id, amount, currency='GBP'):
    ngrok_url = get_ngrok_url() or APP_URL
    order = api_request('POST', '/api/1.0/orders', {
        'amount': int(amount * 100),
        'currency': currency,
        'description': f'Room {room_id} booking',
        'success_redirect_url': f'{ngrok_url}/payment/success',
        'cancel_redirect_url':  f'{ngrok_url}/payment/cancel',
    })
    register_payment_locally(order['id'], room_id, amount, currency)
    return order


def get_ngrok_url():
    try:
        resp = urllib.request.urlopen('http://localhost:4040/api/tunnels')
        data = json.loads(resp.read())
        for t in data.get('tunnels', []):
            if t.get('proto') == 'https':
                return t['public_url']
    except Exception:
        return None


def complete_checkout(checkout_url, card_number):
    """Fill card details on Revolut hosted checkout page via Playwright."""
    from playwright.sync_api import sync_playwright

    print(f'  Checkout URL: {checkout_url}')
    print(f'  Card: {card_number}')

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        ctx = browser.new_context(viewport={'width': 1280, 'height': 720}, ignore_https_errors=True)
        page = ctx.new_page()

        try:
            page.goto(checkout_url, wait_until='domcontentloaded', timeout=30000)
            time.sleep(8)

            # Click "Pay with card" button
            pay_card_btn = page.query_selector('button:has-text("Pay with card")')
            if pay_card_btn:
                pay_card_btn.click()
                print('  Clicked "Pay with card"')
                time.sleep(5)
            else:
                print('  WARNING: No "Pay with card" button found')

            page.screenshot(path=os.path.join(BASE_DIR, 'data', 'checkout_card_form.png'))

            # Find the card-field iframe (contains card number, expiry, CVV)
            card_frame = None
            for frame in page.frames:
                if 'card-field.html' in frame.url:
                    card_frame = frame
                    break

            if not card_frame:
                page.screenshot(path=os.path.join(BASE_DIR, 'data', 'checkout_no_iframe.png'))
                raise RuntimeError('Card field iframe not found')

            # Fill card number
            num_input = card_frame.wait_for_selector('input[name="number"]', timeout=5000)
            num_input.click()
            num_input.fill(card_number)
            print('  Filled card number')
            time.sleep(0.5)

            # Fill expiry
            exp_input = card_frame.wait_for_selector('input[name="expiry"]', timeout=5000)
            exp_input.click()
            exp_input.fill('12/29')
            print('  Filled expiry')
            time.sleep(0.5)

            # Fill CVV
            cvv_input = card_frame.wait_for_selector('input[name="code"]', timeout=5000)
            cvv_input.click()
            cvv_input.fill('123')
            print('  Filled CVV')
            time.sleep(0.5)

            # Fill postcode (only if visible — some card types don't require it)
            try:
                post_input = card_frame.wait_for_selector('input[name="postcode"]:not([disabled])', timeout=2000)
                if post_input and post_input.is_visible():
                    post_input.click()
                    post_input.fill('SW1A 1AA')
                    print('  Filled postcode')
            except Exception:
                print('  Postcode field hidden/disabled — skipping')
            time.sleep(0.5)

            # Fill cardholder name (on main page)
            name_input = page.query_selector('input[placeholder="Cardholder name"]')
            if name_input:
                name_input.click()
                name_input.fill('Test User')
                print('  Filled cardholder name')
                time.sleep(0.5)

            # Fill email (on main page)
            email_input = page.query_selector('input[placeholder="Email address"]')
            if email_input:
                email_input.click()
                email_input.fill('test@example.com')
                print('  Filled email')
                time.sleep(0.5)

            page.screenshot(path=os.path.join(BASE_DIR, 'data', 'checkout_filled.png'))

            # Click submit (Pay button on main page)
            submit_btn = page.query_selector('button[type="submit"]')
            if submit_btn:
                submit_btn.click()
                print('  Clicked submit/Pay button')
            else:
                raise RuntimeError('Submit button not found')

            # Wait for payment processing
            print('  Waiting for payment to process...')
            time.sleep(15)
            page.screenshot(path=os.path.join(BASE_DIR, 'data', 'checkout_result.png'))
            print(f'  Final URL: {page.url}')

        except Exception as e:
            page.screenshot(path=os.path.join(BASE_DIR, 'data', 'checkout_error.png'))
            print(f'  ERROR during checkout: {e}')
            raise
        finally:
            browser.close()


def poll_order_status(order_id, expected_states, timeout=90):
    print(f'  Polling for states: {expected_states}')
    start = time.time()
    last_state = 'UNKNOWN'
    while time.time() - start < timeout:
        order = api_request('GET', f'/api/1.0/orders/{order_id}')
        last_state = order.get('state', 'UNKNOWN')

        # Check the payments array for declined attempts
        # Revolut keeps the order PENDING even after declined payments
        payments = order.get('payments', [])
        has_declined = any(p.get('state') == 'DECLINED' for p in payments)

        effective_state = last_state
        if has_declined and last_state == 'PENDING':
            effective_state = 'FAILED'

        print(f'    Order state: {last_state}, effective: {effective_state}')
        if effective_state in expected_states:
            order['state'] = effective_state  # override for our purposes
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
        # Show what payment status is displayed
        for label in markers.values():
            if label in html:
                print(f'    (found: "{label}" instead)')
        return False


def register_payment_locally(order_id, room_id, amount, currency):
    sf = os.path.join(BASE_DIR, 'data', 'payments.json')
    payments = {}
    if os.path.exists(sf):
        try:
            with open(sf) as f:
                payments = json.load(f)
        except (json.JSONDecodeError, IOError):
            payments = {}
    payments[order_id] = {
        'order_id':   order_id,
        'room_id':    room_id,
        'amount':     amount,
        'currency':   currency,
        'state':      'PENDING',
        'created_at': time.strftime('%Y-%m-%dT%H:%M:%S+00:00'),
        'updated_at': time.strftime('%Y-%m-%dT%H:%M:%S+00:00'),
    }
    with open(sf, 'w') as f:
        json.dump(payments, f, indent=4)


def update_local_payment(order_id, state):
    sf = os.path.join(BASE_DIR, 'data', 'payments.json')
    payments = {}
    if os.path.exists(sf):
        try:
            with open(sf) as f:
                payments = json.load(f)
        except (json.JSONDecodeError, IOError):
            payments = {}
    if order_id in payments:
        payments[order_id]['state'] = state
        payments[order_id]['updated_at'] = time.strftime('%Y-%m-%dT%H:%M:%S+00:00')
        with open(sf, 'w') as f:
            json.dump(payments, f, indent=4)


def test_success_flow():
    print('\n' + '='*60)
    print('SUCCESS FLOW')
    print('='*60)

    room_id = 1

    print('\n1. Creating order...')
    order = create_order(room_id, 50.00, 'GBP')
    order_id = order['id']
    print(f'   Order: {order_id}')

    print('\n2. Completing checkout with success card...')
    complete_checkout(order['checkout_url'], SUCCESS_CARD)

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

    print('\n1. Creating order...')
    order = create_order(room_id, 80.00, 'GBP')
    order_id = order['id']
    print(f'   Order: {order_id}')

    print('\n2. Attempting checkout with declined card...')
    complete_checkout(order['checkout_url'], DECLINED_CARD)

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
    sf = os.path.join(BASE_DIR, 'data', 'payments.json')
    with open(sf, 'w') as f:
        json.dump({}, f)

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
