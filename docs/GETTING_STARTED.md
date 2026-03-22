# Getting Started Guide

A step-by-step guide to install, configure, and run the ZF2 Hotel Booking Demo application.

---

## Prerequisites

| Software       | Version   | Purpose                          |
|----------------|-----------|----------------------------------|
| Docker Desktop | 20+       | Runs the PHP and MariaDB containers |
| Docker Compose | v2+       | Orchestrates multi-container setup |
| Git            | 2.x       | Clone the repository             |
| Web Browser    | Any modern | Access the application           |

Optional (for running e2e tests):

| Software   | Version | Purpose                        |
|------------|---------|--------------------------------|
| Python     | 3.8+    | Runs the Playwright test suite |
| Playwright | Latest  | Browser automation for e2e tests |
| ngrok      | Latest  | Exposes localhost for Revolut webhook callbacks |

---

## 1. Clone the Repository

```bash
git clone <repository-url>
cd ZF2_Demo
```

---

## 2. Configure Environment Variables

The project includes a `.env` file with sandbox credentials that work out of the box. Review and update if needed:

```
# Revolut Merchant API (sandbox)
REVOLUT_API_URL=https://sandbox-merchant.revolut.com
REVOLUT_API_PUBLIC_KEY=pk_...        # Client-side public key
REVOLUT_API_SECRET_KEY=sk_...        # Server-side secret key
REVOLUT_WEBHOOK_SECRET=wsk_...       # Webhook signature verification
REVOLUT_ENVIRONMENT=sandbox

# Public URL for Revolut redirect callbacks (ngrok URL or production domain)
APP_PUBLIC_URL=https://your-ngrok-url.ngrok-free.dev

# Database
DB_HOST=db
DB_PORT=3306
DB_NAME=hotel_db
DB_USER=hotel_user
DB_PASSWORD=hotel_pass
DB_ROOT_PASSWORD=rootpass
```

> **Note:** `APP_PUBLIC_URL` must be a publicly reachable URL (e.g. via ngrok) for Revolut to redirect the browser back after checkout. For local-only testing without payments, this can be left as-is.

---

## 3. Start Docker Containers

```bash
docker compose up -d
```

This starts two services:

| Service | Image            | Host Port | Description                     |
|---------|------------------|-----------|---------------------------------|
| `app`   | PHP 7.4 + Apache | `8088`    | ZF2 application                 |
| `db`    | MariaDB 10.11    | `3309`    | Database with auto-initialized schema |

The `app` service waits for the `db` health check to pass before starting (ensures the database is ready).

Verify both containers are running:

```bash
docker compose ps
```

Expected output shows both `app` and `db` with status `Up` (or `running`).

---

## 4. Install PHP Dependencies

```bash
docker compose exec app composer install
```

This installs Zend Framework 2 and all dependencies into the `vendor/` directory inside the container (shared via volume mount).

---

## 5. Access the Application

Open your browser and navigate to:

```
http://localhost:8088/
```

You should see the homepage with a navigation bar and route reference table.

---

## 6. Available Routes

| URL                  | Method | Description                          |
|----------------------|--------|--------------------------------------|
| `/`                  | GET    | Homepage with route reference        |
| `/room`              | GET    | List all hotel rooms                 |
| `/room/detail/:id`   | GET    | Room details + payment form (e.g. `/room/detail/1`) |
| `/room/search`       | GET/POST | Search rooms by type and price     |
| `/room/create`       | GET/POST | Create a new room (with CSRF)      |
| `/room/about`        | GET    | Static about page                    |
| `/payment/create`    | POST   | Creates a Revolut order              |
| `/payment/success`   | GET    | Post-payment redirect handler        |
| `/payment/cancel`    | GET    | Payment cancellation handler         |
| `/payment/webhook`   | POST   | Revolut webhook endpoint             |
| `/payment/status/:order_id` | GET | JSON endpoint for payment status polling |

---

## 7. Making a Test Payment

To test the Revolut payment flow:

### 7.1 Set up ngrok (required for webhooks)

Revolut needs a public URL to send webhook notifications and redirect the browser back after checkout.

```bash
ngrok http 8088
```

Copy the HTTPS forwarding URL (e.g. `https://abc123.ngrok-free.dev`) and update `APP_PUBLIC_URL` in your `.env` file:

```
APP_PUBLIC_URL=https://abc123.ngrok-free.dev
```

### 7.2 Configure the Revolut webhook URL

In the [Revolut Business Sandbox](https://sandbox-business.revolut.com):
1. Go to **APIs** > **Merchant API**
2. Set the webhook URL to: `https://your-ngrok-url.ngrok-free.dev/payment/webhook`

### 7.3 Complete a payment

1. Navigate to `http://localhost:8088/room/detail/1`
2. Click the **Pay** button
3. You'll be redirected to Revolut's hosted checkout page
4. Use a sandbox test card:
   - **Success:** `4929 4205 7359 5709`
   - **Declined:** `4929 5736 3812 5985`
   - Expiry: any future date (e.g. `12/29`)
   - CVV: `123`
5. After payment, you'll be redirected back to the room detail page showing the payment status

---

## 8. Viewing Error Logs

PHP and Apache errors are logged to `data/php-errors.log`:

```bash
# Follow logs in real-time
tail -f data/php-errors.log

# Or from inside the container
docker compose exec app tail -f /var/www/html/data/php-errors.log
```

---

## 9. Stopping the Application

```bash
# Stop containers (preserves database data)
docker compose down

# Stop and remove database volume (full reset)
docker compose down -v
```

> **Note:** Using `docker compose down -v` removes the database volume. On the next `docker compose up -d`, the `payment_orders` table will be re-created automatically from `data/sql/001_payment_orders.sql`.

---

## 10. Troubleshooting

| Problem | Solution |
|---------|----------|
| Port 8088 already in use | Change the port mapping in `docker-compose.yml` (e.g. `"8089:80"`) |
| Port 3309 already in use | Change the db port mapping in `docker-compose.yml` |
| `composer install` fails | Run inside the container: `docker compose exec app composer install` |
| Page shows blank/500 error | Check `data/php-errors.log` for details |
| "Class not found" errors | Run `docker compose exec app composer dump-autoload` |
| Payment redirect fails | Ensure `APP_PUBLIC_URL` in `.env` matches your ngrok URL |
| Webhook not received | Verify webhook URL is set in Revolut dashboard and ngrok is running |
| Database connection refused | Wait for db health check: `docker compose ps` should show db as healthy |
| Container won't start | Run `docker compose logs app` or `docker compose logs db` to see errors |
| Database schema missing | Remove volume and restart: `docker compose down -v && docker compose up -d` |
