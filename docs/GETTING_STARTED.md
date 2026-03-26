# Getting Started Guide

A step-by-step guide to install, configure, and run the ZF2 Hotel Booking Demo application.

---

## Prerequisites

| Software       | Version   | Purpose                          |
|----------------|-----------|----------------------------------|
| Docker Desktop | 20+       | Runs the PHP and MariaDB containers |
| Docker Compose | v2+       | Orchestrates multi-container setup |
| Git            | 2.x       | Clone the repository             |
| ngrok          | Latest    | Exposes localhost for Revolut webhook/redirect callbacks |
| Web Browser    | Any modern | Access the application           |

Optional (for running e2e tests):

| Software   | Version | Purpose                        |
|------------|---------|--------------------------------|
| Python     | 3.8+    | Runs the Playwright test suite |
| Playwright | Latest  | Browser automation for e2e tests |

### ngrok setup

The project uses an ngrok **static domain** for stable public URLs. Static domains are tied to an ngrok account — each developer needs their own:

1. Sign up at [ngrok.com](https://ngrok.com) (free tier is sufficient)
2. Install ngrok and authenticate: `ngrok config add-authtoken <your-token>`
3. Go to **Domains** in the ngrok dashboard and claim a free static domain
4. Update `NGROK_DOMAIN` in `scripts/dev_start.sh` and `APP_PUBLIC_URL` in `.env` with your domain

---

## 1. Clone the Repository

```bash
git clone <repository-url>
cd ZF2_Demo
```

---

## 2. Configure Environment Variables (.env)

```
# Revolut Merchant API (sandbox)
REVOLUT_API_URL=https://sandbox-merchant.revolut.com
REVOLUT_API_PUBLIC_KEY=pk_...        # Client-side public key
REVOLUT_API_SECRET_KEY=sk_...        # Server-side secret key
REVOLUT_WEBHOOK_SECRET=wsk_...       # Webhook signature verification
REVOLUT_ENVIRONMENT=sandbox

# Public URL for Revolut redirect callbacks (ngrok static domain)
APP_PUBLIC_URL=https://unmajestic-decussately-teresia.ngrok-free.dev

# Database
DB_HOST=db
DB_PORT=3306
DB_NAME=hotel_db
DB_USER=hotel_user
DB_PASSWORD=hotel_pass
DB_ROOT_PASSWORD=rootpass
```

> **Note:** `APP_PUBLIC_URL` must be a publicly reachable URL for Revolut to redirect the browser back after checkout. The project uses a static ngrok domain so this value stays consistent across sessions. Each developer must replace this with their own ngrok static domain (see [ngrok setup](#ngrok-setup) above).

---

## 3. Start the Dev Environment

The easiest way to start everything is with the dev script:

```bash
bash scripts/dev_start.sh
```

This script does the following in one step:
1. Starts the `db` and `app` Docker containers
2. Launches ngrok with the static domain (`unmajestic-decussately-teresia.ngrok-free.dev`) on port `8088`
3. Prints the public ngrok URL once the tunnel is established

### Starting services manually

If you prefer to start services individually:

```bash
# Start Docker containers
docker compose up -d

# Start ngrok with the static domain
ngrok http --domain=unmajestic-decussately-teresia.ngrok-free.dev 8088
```

### Services overview

| Service | Image            | Host Port | Description                     |
|---------|------------------|-----------|---------------------------------|
| `app`   | PHP 7.4 + Apache | `8088`    | ZF2 application                 |
| `db`    | MariaDB 10.11    | `3309`    | Database with auto-initialized schema |

The `app` service waits for the `db` health check to pass before starting.

Verify both containers are running:

```bash
docker compose ps
```

---

## 4. Install PHP Dependencies

```bash
docker compose exec app composer install
```

This installs Zend Framework 2, Doctrine ORM Module, and all dependencies into the `vendor/` directory inside the container (shared via volume mount).

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

### 7.1 Ensure ngrok is running

If you started the environment with `scripts/dev_start.sh`, ngrok is already running. Otherwise start it manually:

```bash
ngrok http --domain=unmajestic-decussately-teresia.ngrok-free.dev 8088
```

You can verify the tunnel at http://localhost:4040.

### 7.2 Configure the Revolut webhook URL

In the [Revolut Business Sandbox](https://sandbox-business.revolut.com):
1. Go to **APIs** > **Merchant API**
2. Set the webhook URL to: `https://unmajestic-decussately-teresia.ngrok-free.dev/payment/webhook`

To verify your current webhook configuration:

```bash
bash scripts/revolut_get_webhooks_list.sh
```

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

## 8. Running E2E Tests

The project includes a Playwright-based test script for the payment flow:

```bash
# Test successful payment
python3 test_payment.py success

# Test declined payment
python3 test_payment.py declined

# Test both flows
python3 test_payment.py both
```

> **Note:** The app, database, and ngrok must all be running before executing the tests.

---

## 9. Dev Scripts Reference

| Script | Purpose |
|--------|---------|
| `scripts/dev_start.sh` | Starts Docker containers + ngrok tunnel in one command |
| `scripts/revolut_get_webhooks_list.sh` | Queries the Revolut sandbox API for configured webhooks |

---

## 10. Viewing Error Logs

PHP and Apache errors are logged to `data/php-errors.log`:

```bash
# Follow logs in real-time
tail -f data/php-errors.log

# Or from inside the container
docker compose exec app tail -f /var/www/html/data/php-errors.log
```

---

## 11. Stopping the Application

```bash
# Stop containers (preserves database data)
docker compose down

# Stop and remove database volume (full reset)
docker compose down -v
```

> **Note:** Using `docker compose down -v` removes the database volume. On the next `docker compose up -d`, all tables (`payment_orders`, `rooms`, `users`) will be re-created and seeded automatically from the SQL files in `data/sql/`.

---

## 12. Troubleshooting

| Problem | Solution |
|---------|----------|
| Port 8088 already in use | Change the port mapping in `docker-compose.yml` (e.g. `"8089:80"`) |
| Port 3309 already in use | Change the db port mapping in `docker-compose.yml` |
| `composer install` fails | Run inside the container: `docker compose exec app composer install` |
| Page shows blank/500 error | Check `data/php-errors.log` for details |
| "Class not found" errors | Run `docker compose exec app composer dump-autoload` |
| Payment redirect fails | Ensure `APP_PUBLIC_URL` in `.env` matches the ngrok static domain |
| Webhook not received | Run `scripts/revolut_get_webhooks_list.sh` to verify config; ensure ngrok is running |
| Database connection refused | Wait for db health check: `docker compose ps` should show db as healthy |
| Container won't start | Run `docker compose logs app` or `docker compose logs db` to see errors |
| Database schema missing | Remove volume and restart: `docker compose down -v && docker compose up -d` |
| ngrok auth error | Run `ngrok config add-authtoken <your-token>` — each developer needs their own ngrok account |
| ngrok domain error | The static domain in `scripts/dev_start.sh` is account-specific — claim your own at ngrok.com and update `NGROK_DOMAIN` + `APP_PUBLIC_URL` |
