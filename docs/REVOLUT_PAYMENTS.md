# Revolut Payment Integration

Complete reference for the Revolut Merchant API integration, payment lifecycle, and end-to-end testing.

---

## Table of Contents

1. [Overview](#overview)
2. [Revolut API Configuration](#revolut-api-configuration)
3. [Payment Flow](#payment-flow)
4. [API Calls](#api-calls)
5. [Webhook Processing](#webhook-processing)
6. [Payment State Machine](#payment-state-machine)
7. [Frontend Status Polling](#frontend-status-polling)
8. [Security](#security)
9. [End-to-End Testing](#end-to-end-testing)
10. [Switching to Production](#switching-to-production)

---

## Overview

The application uses **Revolut's Hosted Checkout** to process payments for hotel room bookings. The integration uses the **Revolut Merchant API** (version `2024-09-01`).

**Key components:**
- `PaymentService` (`module/Payment/src/Payment/Service/PaymentService.php`) -- Revolut API wrapper and database operations
- `PaymentController` (`module/Payment/src/Payment/Controller/PaymentController.php`) -- HTTP request handlers
- `detail.phtml` (`module/Room/view/room/room/detail.phtml`) -- Payment UI and JavaScript status poller
- `payment_orders` table (`data/sql/001_payment_orders.sql`) -- Local payment state persistence

---

## Revolut API Configuration

All credentials are stored in the `.env` file and loaded via `config/autoload/payment.global.php`.

| Variable               | Purpose                                      | Example                              |
|------------------------|----------------------------------------------|--------------------------------------|
| `REVOLUT_API_URL`      | API base URL                                 | `https://sandbox-merchant.revolut.com` |
| `REVOLUT_API_SECRET_KEY` | Server-side authentication (Bearer token)  | `sk_WsA4I0Y...`                      |
| `REVOLUT_API_PUBLIC_KEY` | Client-side key (not used in hosted checkout) | `pk_J4v1ER6...`                    |
| `REVOLUT_WEBHOOK_SECRET` | HMAC signing secret for webhook verification | `wsk_XNHbm4y...`                  |
| `REVOLUT_ENVIRONMENT`   | `sandbox` or `prod`                          | `sandbox`                            |
| `APP_PUBLIC_URL`         | Public URL for redirect callbacks (ngrok)    | `https://abc.ngrok-free.dev`         |

### Getting Credentials

1. Sign up at [Revolut Business Sandbox](https://sandbox-business.revolut.com)
2. Navigate to **APIs** > **Merchant API**
3. Generate API keys (public + secret)
4. Set up a webhook endpoint and copy the signing secret

---

## Payment Flow

### Sequence Diagram

```
  User                App (PHP)             Revolut API          Revolut Checkout
   │                    │                       │                      │
   │  Click "Pay"       │                       │                      │
   │───────────────────>│                       │                      │
   │                    │  POST /api/orders      │                      │
   │                    │──────────────────────>│                      │
   │                    │  { id, checkout_url }  │                      │
   │                    │<──────────────────────│                      │
   │                    │                       │                      │
   │                    │  INSERT payment_orders │                      │
   │                    │  (state: PENDING)      │                      │
   │                    │                       │                      │
   │  302 Redirect      │                       │                      │
   │<───────────────────│                       │                      │
   │                    │                       │                      │
   │  Open checkout page│                       │                      │
   │────────────────────────────────────────────────────────────────>│
   │                    │                       │                      │
   │  Fill card details │                       │                      │
   │  Click "Pay"       │                       │                      │
   │────────────────────────────────────────────────────────────────>│
   │                    │                       │                      │
   │                    │  POST /payment/webhook │                      │
   │                    │<──────────────────────│  (event notification) │
   │                    │  Verify HMAC signature │                      │
   │                    │  UPDATE state          │                      │
   │                    │  200 OK               │                      │
   │                    │──────────────────────>│                      │
   │                    │                       │                      │
   │  302 Redirect back │                       │                      │
   │<───────────────────────────────────────────────────────────────│
   │                    │                       │                      │
   │  GET /room/detail/1│                       │                      │
   │───────────────────>│                       │                      │
   │  (shows payment    │                       │                      │
   │   status from DB)  │                       │                      │
   │<───────────────────│                       │                      │
```

### Step-by-Step

1. **User initiates payment** -- Clicks the "Pay" button on the room detail page (`/room/detail/:id`). This submits a hidden form via POST to `/payment/create`.

2. **App creates Revolut order** -- `PaymentController::createAction()` calls `PaymentService::createOrder()`:
   - Validates amount (positive) and currency (ISO 4217)
   - Sends `POST /api/orders` to Revolut with amount (in minor units), currency, description, and redirect URL
   - Saves the order to the `payment_orders` table with state `PENDING`

3. **User redirected to Revolut checkout** -- The browser is redirected to Revolut's `checkout_url` (hosted checkout page).

4. **User completes payment** -- The user fills in card details on Revolut's page and submits.

5. **Revolut sends webhook** -- Revolut posts an event notification to `/payment/webhook`. The app:
   - Verifies the HMAC-SHA256 signature
   - Maps the event type to a payment state
   - Updates the `payment_orders` table

6. **User redirected back** -- Revolut redirects the browser to the configured `redirect_url` (room detail page with `?payment=success`).

7. **Frontend shows status** -- The room detail page reads the payment state from the database and displays the appropriate status message. If the state is still PENDING, the JavaScript poller takes over.

---

## API Calls

All API calls are made via `PaymentService::apiRequest()` with the following defaults:

```
Base URL:   https://sandbox-merchant.revolut.com (or production URL)
Auth:       Authorization: Bearer <secret_key>
Headers:    Accept: application/json
            Revolut-Api-Version: 2024-09-01
Timeout:    30s connection, 10s connect
SSL:        Certificate verification enabled
```

### Create Order

```
POST /api/orders

Request Body:
{
    "amount": 5000,                              // Minor units (50.00 GBP = 5000 pence)
    "currency": "GBP",                           // ISO 4217
    "description": "Room 101 (Single)",           // Max 255 chars, sanitized
    "redirect_url": "https://ngrok-url/room/detail/1?payment=success"
}

Response:
{
    "id": "6516c652-d1bd-a0c7-8e78-b08b940a40b0",
    "state": "pending",
    "checkout_url": "https://checkout.revolut.com/payment-link/...",
    ...
}
```

**Note:** The amount is converted from major units (e.g. `50.00`) to minor units (e.g. `5000`) by multiplying by 100 and rounding: `(int) round($amount * 100)`.

### Get Order Status

```
GET /api/orders/{order_id}

Response:
{
    "id": "6516c652-d1bd-a0c7-8e78-b08b940a40b0",
    "state": "completed",
    "payments": [
        {
            "state": "completed",
            ...
        }
    ],
    ...
}
```

**Declined payment handling:** Revolut keeps the order in `PENDING` state even after a declined payment attempt. The app checks the `payments` array -- if the most recent payment has `state: "declined"`, it synthesizes a `FAILED` state locally.

---

## Webhook Processing

### Endpoint

```
POST /payment/webhook
```

### Headers

| Header                        | Description                              |
|-------------------------------|------------------------------------------|
| `Revolut-Signature`           | `v1=<hex_digest>` HMAC-SHA256 signature  |
| `Revolut-Request-Timestamp`   | Timestamp in milliseconds                |

### Signature Verification

The verification process in `PaymentService::verifyWebhookSignature()`:

1. **Normalize timestamp** -- Revolut sends timestamps in milliseconds; convert to seconds if > 10^12
2. **Replay protection** -- Reject if timestamp is older than 5 minutes
3. **Parse signature** -- Strip `v1=` prefix from `Revolut-Signature` header
4. **Compute HMAC** -- `HMAC-SHA256("v1.{timestamp}.{body}", webhook_secret)`
5. **Compare** -- Use `hash_equals()` for timing-safe string comparison

### Payload Format

```json
{
    "event": "ORDER_COMPLETED",
    "order_id": "6516c652-d1bd-a0c7-8e78-b08b940a40b0"
}
```

### Event-to-State Mapping

| Revolut Event               | Local State  | Meaning                          |
|------------------------------|-------------|----------------------------------|
| `ORDER_COMPLETED`            | `COMPLETED` | Payment fully captured           |
| `ORDER_PAYMENT_COMPLETED`    | `COMPLETED` | Individual payment completed     |
| `ORDER_PAYMENT_DECLINED`     | `FAILED`    | Card was declined                |
| `ORDER_PAYMENT_FAILED`       | `FAILED`    | Payment failed for other reason  |
| `ORDER_PAYMENT_CANCELLED`    | `CANCELLED` | User or merchant cancelled       |
| `ORDER_AUTHORISED`           | `AUTHORISED`| Payment authorized, not yet captured |

### State Update Logic

`PaymentService::updatePaymentState()` performs an atomic update:

```sql
UPDATE payment_orders
   SET state = :state, updated_at = :updated_at
 WHERE order_id = :order_id
   AND state NOT IN ('COMPLETED', 'FAILED', 'CANCELLED')
```

Terminal states (COMPLETED, FAILED, CANCELLED) cannot be overwritten. This prevents:
- Duplicate webhook deliveries from corrupting state
- Race conditions between webhook and API polling

---

## Payment State Machine

```
                    ┌──────────────┐
                    │   PENDING    │  (order created, awaiting payment)
                    └──────┬───────┘
                           │
              ┌────────────┼────────────┐
              │            │            │
              ▼            ▼            ▼
      ┌──────────┐  ┌───────────┐  ┌───────────┐
      │ AUTHORISED│  │  FAILED   │  │ CANCELLED │
      │(card ok)  │  │(declined) │  │(user quit)│
      └────┬──────┘  └───────────┘  └───────────┘
           │           (terminal)     (terminal)
           ▼
      ┌──────────┐
      │ COMPLETED│
      │(captured)│
      └──────────┘
        (terminal)
```

**Terminal states** are final -- once a payment reaches COMPLETED, FAILED, or CANCELLED, it cannot transition to any other state.

---

## Frontend Status Polling

The room detail page (`detail.phtml`) includes a JavaScript poller when the payment state is PENDING or AUTHORISED.

### Polling Behavior

| Time Window    | Poll Interval | Reason                                    |
|----------------|---------------|-------------------------------------------|
| 0 -- 30 seconds  | Every 3 seconds | Webhook should arrive quickly            |
| 30+ seconds    | Every 10 seconds | Server-side API fallback is active       |
| 5+ minutes     | Stops          | Shows "status unknown -- please refresh"  |

### Server-Side Fallback

`PaymentController::statusAction()` implements a fallback mechanism:

- If the payment is still PENDING/AUTHORISED **and** the record hasn't been updated for 30+ seconds, the server queries the Revolut API directly
- This handles cases where the webhook is delayed or fails to deliver
- The API result is saved to the database for subsequent polls

### JSON Response Format

```json
{
    "success": true,
    "state": "COMPLETED",
    "order_id": "6516c652-d1bd-a0c7-8e78-b08b940a40b0"
}
```

When a terminal state is detected, the page auto-reloads to show the final status UI (success banner, declined error, or cancellation notice).

---

## Security

### Summary of Protections

| Layer              | Measure                                           |
|--------------------|---------------------------------------------------|
| API Communication  | HTTPS + SSL cert verification + Bearer token auth |
| Webhook Integrity  | HMAC-SHA256 signature verification                |
| Replay Prevention  | 5-minute timestamp window on webhooks             |
| Timing Attacks     | `hash_equals()` for signature comparison          |
| SQL Injection      | PDO prepared statements, emulated prepares off    |
| XSS                | `escapeHtml()` / `escapeHtmlAttr()` in templates  |
| Input Validation   | Amount, currency, order ID format validated        |
| CSRF               | ZF2 CSRF token on room creation form              |
| State Integrity    | Terminal states are immutable (atomic SQL update)  |

---

## End-to-End Testing

### Test Suite: `test_payment.py`

A Playwright-based test suite that automates the full payment flow through a headless Chromium browser.

### Prerequisites

```bash
pip install playwright
playwright install chromium
```

### Test Cards (Revolut Sandbox)

| Card Number          | Result   | Use Case              |
|----------------------|----------|-----------------------|
| `4929 4205 7359 5709` | Success  | Tests successful payment |
| `4929 5736 3812 5985` | Declined | Tests declined payment  |

Additional card details:
- **Expiry:** Any future date (e.g. `12/29`)
- **CVV:** `123`
- **Postcode:** `SW1A 1AA` (if prompted)
- **Cardholder:** `Test User`
- **Email:** `test@example.com`

### Running Tests

```bash
# Test successful payment only
python3 test_payment.py success

# Test declined payment only
python3 test_payment.py declined

# Test both flows (default)
python3 test_payment.py both
```

### What the Tests Do

#### Success Flow (`test_success_flow`)

1. Truncates the `payment_orders` table for a clean run
2. Opens room detail page (`/room/detail/1`) in headless Chromium
3. Clicks the "Pay" button (POST to `/payment/create`)
4. Waits for redirect to Revolut's checkout page
5. Clicks "Pay with card" (if payment method selection is shown)
6. Finds the card-field iframe on Revolut's page
7. Fills card number (`4929420573595709`), expiry (`12/29`), CVV (`123`), postcode, name, email
8. Submits the payment form
9. Waits for redirect back to the app (handles ngrok interstitial if present)
10. Queries the database for the order ID
11. Polls the Revolut API until the order reaches `COMPLETED` state (90s timeout)
12. Verifies the room detail page shows "Payment Successful"

#### Declined Flow (`test_declined_flow`)

Same as above, but:
- Uses room 2 instead of room 1
- Uses declined card (`4929573638125985`)
- Expects `FAILED` state
- Verifies the page shows "Payment Declined"

### Test Infrastructure

| Function                    | Purpose                                          |
|-----------------------------|--------------------------------------------------|
| `load_env()`                | Reads `.env` file for API credentials            |
| `api_request(method, path)` | Direct Revolut API call (for polling)            |
| `db_exec(sql)`              | Runs SQL via `docker compose exec db mysql`      |
| `db_clear_payments()`       | Truncates `payment_orders` for clean test runs   |
| `get_latest_order_for_room(id)` | Queries DB for most recent order             |
| `poll_order_status(id, states)` | Polls Revolut API every 4s until target state |
| `verify_room_ui(id, state)` | Fetches room page HTML and checks for status text |
| `complete_hosted_checkout()` | Full browser automation of the checkout flow    |

### Debugging

The test takes screenshots at key points, saved to the `data/` directory:

| File                       | Captured When                              |
|----------------------------|--------------------------------------------|
| `room{id}_checkout.png`    | After loading Revolut checkout page        |
| `room{id}_filled.png`      | After filling all card details             |
| `room{id}_result.png`      | After payment processing and redirect      |
| `room{id}_no_pay.png`      | Error: Pay button not found                |
| `room{id}_no_card_frame.png` | Error: Card iframe not found             |
| `room{id}_error.png`       | Any unexpected error during checkout       |

### Exit Codes

| Code | Meaning                   |
|------|---------------------------|
| `0`  | All tests passed          |
| `1`  | One or more tests failed  |

---

## Switching to Production

To move from sandbox to production:

1. **Get production API keys** from [Revolut Business](https://business.revolut.com) > APIs > Merchant API

2. **Update `.env`:**
   ```
   REVOLUT_API_URL=https://merchant.revolut.com
   REVOLUT_API_SECRET_KEY=sk_live_...
   REVOLUT_API_PUBLIC_KEY=pk_live_...
   REVOLUT_WEBHOOK_SECRET=wsk_live_...
   REVOLUT_ENVIRONMENT=prod
   APP_PUBLIC_URL=https://your-production-domain.com
   ```

3. **Set up the production webhook URL** in the Revolut Business dashboard:
   ```
   https://your-production-domain.com/payment/webhook
   ```

4. **Review security:**
   - Ensure HTTPS is configured on the production server
   - Remove `display_exceptions => true` from `Application/config/module.config.php`
   - Set PHP `display_errors = Off` (already set in Dockerfile)
   - Ensure `.env` is not accessible via the web server
