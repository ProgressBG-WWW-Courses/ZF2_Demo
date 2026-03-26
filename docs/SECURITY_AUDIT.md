# Security Audit — Payment & Application

Findings from a security review of the Revolut payment integration and related application components.

---

## Payment Module Findings

### 1. Webhook Signature Bypass (Critical — Fixed)

**File:** `module/Payment/src/Payment/Controller/PaymentController.php` line 149

**Issue:** If the `Revolut-Signature` header was absent, the webhook was processed without any signature verification. The `if ($signature)` guard meant that an attacker could forge POST requests to `/payment/webhook` and mark any order as COMPLETED, FAILED, or CANCELLED — without actually paying.

**Impact:** Financial — an attacker could complete bookings without payment by sending crafted webhooks without a signature header.

**Fix:** Webhook now requires a valid signature on every request. Requests missing the `Revolut-Signature` or `Revolut-Request-Timestamp` headers are rejected with HTTP 401.

---

### 2. Client-Side Price Tampering (High — Fixed)

**File:** `module/Room/view/room/room/detail.phtml` line 100, `module/Payment/src/Payment/Controller/PaymentController.php` line 33

**Issue:** The payment amount was submitted from a hidden HTML form field (`<input type="hidden" name="amount" ...>`). A user could modify the DOM or intercept the POST request and change the amount to any value (e.g. 0.01 EUR instead of 120.00 EUR). The server accepted whatever amount was submitted without cross-checking against the actual room price.

**Impact:** Financial — a user could pay an arbitrarily low amount for any room.

**Fix:** `PaymentController::createAction()` now loads the room entity from the database and uses its authoritative price, ignoring the submitted `amount` field entirely.

---

### 3. Missing Order ID Validation in Status Endpoint (Medium — Fixed)

**File:** `module/Payment/src/Payment/Controller/PaymentController.php` line 199

**Issue:** The `statusAction()` endpoint accepted the `order_id` route parameter without format validation. While Doctrine's parameterized queries prevent SQL injection, the unvalidated value was passed to the Revolut API as a URL path segment (line 217→131), potentially allowing SSRF-adjacent path traversal if the order ID contained encoded path characters.

The `PaymentService::validateOrderId()` method existed but was only called in `getOrderStatus()`, not in the controller before the DB lookup.

**Fix:** `statusAction()` now validates the order ID format against `[a-zA-Z0-9_-]+` before any processing.

---

### 4. Unescaped Price Output (Low — Fixed)

**File:** `module/Room/view/room/room/detail.phtml` lines 16, 100, 106

**Issue:** Room price was output with `<?php echo $this->room->getPrice(); ?>` without `escapeHtml()`. Although the price is stored as `DECIMAL(10,2)` in the database and validated on input, this was inconsistent with the escaping pattern used for all other dynamic values on the page.

**Fix:** Price output now uses `escapeHtml()` consistently.

---

### 5. No CSRF Token on Payment Form (Low — Accepted)

**File:** `module/Room/view/room/room/detail.phtml` line 98

**Issue:** The payment creation form does not include a CSRF token. The room creation form (`RoomForm`) uses ZF2's `Csrf` form element, but the payment form is a plain HTML form.

**Mitigations already in place:**
- `SameSite=Lax` session cookie prevents cross-origin form submissions
- The `payment/create` route requires `staff` role (ACL-protected)
- The form submits via POST

**Risk assessment:** With `SameSite=Lax` and the ACL gate, CSRF exploitation requires the attacker to be on the same origin. This is acceptable for a demo/teaching application. For production, a CSRF token should be added.

---

### 6. Cancel Endpoint Trusts Client-Provided Order ID (Low — Accepted)

**File:** `module/Payment/src/Payment/Controller/PaymentController.php` line 108

**Issue:** Any authenticated staff user can cancel any payment order by knowing/guessing the `order_id` query parameter on `/payment/cancel`. The endpoint does not verify that the order belongs to the requesting user.

**Mitigations:** The ACL requires `staff` role. Terminal states cannot be overwritten, so completed payments cannot be cancelled.

**Risk assessment:** In a multi-user production system this would need user-to-order ownership checks. Acceptable for a single-tenant demo.

---

## Application-Wide Review

### Authentication & Session

| Area | Status | Notes |
|------|--------|-------|
| Password hashing | OK | bcrypt via `password_hash()` / `password_verify()` |
| Session fixation | OK | `session_regenerate_id(true)` on login |
| Session cookie flags | OK | HttpOnly, SameSite=Lax |
| Session validators | OK | HttpUserAgent + RemoteAddr |
| Logout | OK | Session destroyed, cookie deleted |
| `cookie_secure` | Note | Set to `false` — must be `true` for production HTTPS |

### Authorization (ACL)

| Area | Status | Notes |
|------|--------|-------|
| Default deny | OK | Unregistered resources denied ("Secure by Default") |
| Role hierarchy | OK | guest → staff → manager → admin with proper inheritance |
| Enforcement point | OK | `EVENT_ROUTE` listener at priority -100 |
| Webhook exemption | OK | `payment/webhook` correctly allowed for guest (Revolut must reach it) |
| View-level checks | OK | `$this->isAllowed()` hides UI elements based on role |

### Input Validation

| Area | Status | Notes |
|------|--------|-------|
| Room creation | OK | InputFilter with StripTags, StringTrim, StringLength, InArray, Regex |
| Login form | OK | InputFilter with StripTags, StringTrim, NotEmpty, CSRF |
| Payment creation | OK (fixed) | Server-side price from DB; currency regex; description sanitized |
| Search form | OK | InputFilter on type and min_price |

### Output Escaping

| Area | Status | Notes |
|------|--------|-------|
| Room detail page | OK (fixed) | All dynamic values now use escapeHtml/escapeHtmlAttr |
| Room list page | OK | Uses escapeHtml for all fields |
| JSON responses | OK | ZF2 JsonModel auto-encodes |
| Login form | OK | Error message not user-controlled |

### Database

| Area | Status | Notes |
|------|--------|-------|
| SQL injection | OK | All queries via Doctrine ORM (parameterized) |
| Raw SQL | OK | No raw SQL anywhere in application code |
| Terminal state protection | OK | Prevents webhook replay from corrupting payment state |

### API Communication

| Area | Status | Notes |
|------|--------|-------|
| HTTPS | OK | Both sandbox and production URLs are HTTPS |
| SSL verification | OK | `CURLOPT_SSL_VERIFYPEER=true`, `CURLOPT_SSL_VERIFYHOST=2` |
| Bearer token | OK | Secret key in Authorization header, never exposed to frontend |
| API version pinning | OK | `Revolut-Api-Version: 2025-12-04` header on every request |
| Webhook HMAC | OK (fixed) | Now mandatory — unsigned requests rejected |
| Replay protection | OK | 5-minute timestamp window |
| Timing-safe comparison | OK | `hash_equals()` for signature check |

---

## Summary

| # | Finding | Severity | Status |
|---|---------|----------|--------|
| 1 | Webhook signature bypass | Critical | Fixed |
| 2 | Client-side price tampering | High | Fixed |
| 3 | Missing order_id validation in status endpoint | Medium | Fixed |
| 4 | Unescaped price output | Low | Fixed |
| 5 | No CSRF on payment form | Low | Accepted (mitigated by SameSite + ACL) |
| 6 | Cancel endpoint lacks ownership check | Low | Accepted (demo scope) |
