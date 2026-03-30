# Setup and Deployment Guide

A step-by-step guide to install, configure, and run the ZF2 Hotel Booking Demo application in **development** (localhost with Docker) and **production** (VPS) environments.

---

## Table of Contents

- [Part 1: Development (localhost)](#part-1-development-localhost)
  - [Prerequisites](#prerequisites)
  - [1. Clone the Repository](#1-clone-the-repository)
  - [2. Configure Environment Variables](#2-configure-environment-variables)
  - [3. Start the Dev Environment](#3-start-the-dev-environment)
  - [4. Install PHP Dependencies](#4-install-php-dependencies)
  - [5. Access the Application](#5-access-the-application)
  - [6. Making a Test Payment](#6-making-a-test-payment)
  - [7. Running E2E Tests](#7-running-e2e-tests)
  - [8. Stopping the Application](#8-stopping-the-application)
- [Part 2: Production (VPS)](#part-2-production-vps)
  - [VPS Requirements](#vps-requirements)
  - [Option A: Docker Deployment + Nginx](#option-a-docker-deployment-nginx)
  - [Option B: Docker Deployment + Apache](#option-b-docker-deployment-apache)
  - [Option C: Native Installation](#option-c-native-installation)
  - [Production Security Hardening](#production-security-hardening)
  - [SSL/TLS with Let's Encrypt](#ssltls-with-lets-encrypt)
  - [Revolut Production Setup](#revolut-production-setup)
  - [Backups](#backups)
  - [Monitoring](#monitoring)
- [Reference](#reference)
  - [Available Routes](#available-routes)
  - [Default User Accounts](#default-user-accounts)
  - [Dev Scripts](#dev-scripts)
  - [Error Logs](#error-logs)
  - [Troubleshooting](#troubleshooting)

---

# Part 1: Development (localhost)

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

The project uses an ngrok **static domain** for stable public URLs. Static domains are tied to an ngrok account -- each developer needs their own:

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

## 2. Configure Environment Variables

Copy the template and fill in values:

```bash
cp .env.example .env
```

Edit `.env`:

```
# Database (defaults work out of the box with Docker)
DB_HOST=db
DB_PORT=3306
DB_NAME=hotel_db
DB_USER=hotel_user
DB_PASSWORD=hotel_pass
DB_ROOT_PASSWORD=rootpass

# Revolut Merchant API (sandbox)
REVOLUT_ENVIRONMENT=sandbox
REVOLUT_API_URL=https://sandbox-merchant.revolut.com
REVOLUT_API_SECRET_KEY=sk_...        # from Revolut Business Sandbox dashboard
REVOLUT_API_PUBLIC_KEY=pk_...        # (not used in hosted checkout)
REVOLUT_WEBHOOK_SECRET=wsk_...       # from Revolut webhook configuration

# Public URL for Revolut redirect callbacks (your ngrok static domain)
APP_PUBLIC_URL=https://your-domain.ngrok-free.dev
```

> **Note:** `APP_PUBLIC_URL` must be publicly reachable for Revolut to redirect the browser after checkout. Each developer must use their own ngrok static domain (see [ngrok setup](#ngrok-setup) above).

---

## 3. Start the Dev Environment

The quickest way:

```bash
bash scripts/dev_start.sh
```

This script:
1. Starts the `db` and `app` Docker containers
2. Launches ngrok with your static domain on port `8088`
3. Prints the public ngrok URL once the tunnel is established

### Starting services manually

```bash
# Start Docker containers
docker compose up -d

# Start ngrok with your static domain
ngrok http --domain=your-domain.ngrok-free.dev 8088
```

### Services overview

| Service | Image            | Host Port | Description                     |
|---------|------------------|-----------|---------------------------------|
| `app`   | PHP 7.4 + Apache | `8088`    | ZF2 application                 |
| `db`    | MariaDB 10.11    | `3309`    | Database with auto-initialized schema |

The `app` service waits for the `db` health check to pass before starting. On first boot, MariaDB automatically runs all `.sql` files from `data/sql/` to create tables and seed data.

Verify both containers are running:

```bash
docker compose ps
```

---

## 4. Install PHP Dependencies

```bash
docker compose exec app composer install
```

This installs Zend Framework 2, Doctrine ORM Module, and all dependencies into the `vendor/` directory (shared via volume mount).

---

## 5. Access the Application

Open your browser:

```
http://localhost:8088/
```

You should see the homepage with a navigation bar and route reference table. Log in with one of the [default accounts](#default-user-accounts) to access protected routes.

---

## 6. Making a Test Payment

### 6.1 Ensure ngrok is running

If you started with `scripts/dev_start.sh`, ngrok is already running. Otherwise:

```bash
ngrok http --domain=your-domain.ngrok-free.dev 8088
```

Verify the tunnel at http://localhost:4040.

### 6.2 Configure the Revolut webhook URL

In the [Revolut Business Sandbox](https://sandbox-business.revolut.com):
1. Go to **APIs** > **Merchant API**
2. Set the webhook URL to: `https://your-domain.ngrok-free.dev/payment/webhook`

Verify configuration:

```bash
bash scripts/revolut_get_webhooks_list.sh
```

### 6.3 Complete a payment

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

## 7. Optional: Running E2E Tests

The project includes a Playwright-based test for the payment flow:

```bash
# Test successful payment
python3 test_payment.py success

# Test declined payment
python3 test_payment.py declined

# Test both flows
python3 test_payment.py both
```

> The app, database, and ngrok must all be running before executing the tests.

---

## 8. Stopping the Application

```bash
# Stop containers (preserves database data)
docker compose down

# Stop and remove database volume (full reset)
docker compose down -v
```

Using `docker compose down -v` removes the database volume. On the next `docker compose up -d`, all tables will be re-created and seeded from the SQL files in `data/sql/`.

---

# Part 2: Production (VPS)

This section covers deploying the application to a Linux VPS with a real domain name, SSL, and production Revolut credentials. Two deployment options are provided: Docker (recommended — consistent, portable, easier to replicate) and native installation (traditional approach, no Docker required).

## VPS Requirements

| Requirement        | Minimum                 |
|--------------------|-------------------------|
| OS                 | Ubuntu 22.04+ / Debian 12+ |
| RAM                | 1 GB                    |
| Disk               | 10 GB                   |
| Domain             | A domain pointing to the VPS IP (A record) |
| Root/sudo access   | Required for initial setup |

---

## Option A: Docker Deployment + Nginx

The most recommended production path -- uses the same Docker setup as development and `nginx` as reverse proxy, which handles SSL and forwards plain HTTP to the Docker `app` container. The container stack is identical to development — no SSL configuration inside Docker.

### Architecture

```
Internet
    │
    ▼
Nginx (port 80/443)            ← host OS, SSL termination via Certbot
    │
    ▼
127.0.0.1:8088 (Docker)
    ├── app container: Apache + PHP 7.4  → serves ZF2 app
    └── db container:  MariaDB 10.11     → database
```



### 1. Install Docker

```bash
# Ubuntu/Debian
sudo apt update && sudo apt install -y ca-certificates curl gnupg
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
  https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt update && sudo apt install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
sudo usermod -aG docker $USER
```

Log out and back in for the group change to take effect.

### 2. Clone and configure

```bash
cd /var/www
git clone https://github.com/ProgressBG-WWW-Courses/ZF2_Demo ZF2_Demo
# transfer ownership to the current user
sudo chown -R $USER:$USER /var/www/ZF2_Demo 
cd ZF2_Demo
cp .env.example .env
```

Edit `.env` for production:

```
# Database -- use strong passwords in production
DB_HOST=db
DB_PORT=3306
DB_NAME=hotel_db
DB_USER=hotel_user
DB_PASSWORD=<strong-random-password>
DB_ROOT_PASSWORD=<strong-random-password>

# Revolut Merchant API (production)
REVOLUT_ENVIRONMENT=prod
REVOLUT_API_URL=https://merchant.revolut.com
REVOLUT_API_SECRET_KEY=sk_live_...
REVOLUT_API_PUBLIC_KEY=pk_live_...
REVOLUT_WEBHOOK_SECRET=wsk_live_...

# Your production domain (no trailing slash)
APP_PUBLIC_URL=https://yourdomain.com
```

### 3. Adjust port binding

For production behind a reverse proxy, bind only to localhost. Edit `docker-compose.yml`:

```yaml
services:
  app:
    ports:
      - "127.0.0.1:8088:80"   # only accessible via reverse proxy
  db:
    ports: []                  # no external access to DB
```

### 4. Build and start

Build the Docker image and start the containers:
```bash
docker compose up -d --build
```

Install PHP dependencies:
```bash
docker compose exec app composer install --no-dev --optimize-autoloader
```

### 5. Set up Nginx reverse proxy with SSL

#### Why a reverse proxy?

The Docker `app` container already runs Apache internally, so you might wonder why we add Nginx in front of it. The reason is **SSL certificate management**.

To add HTTPS directly to the containerized Apache, you would need to mount certificate files into the container, install Certbot inside it, and handle certificate renewal across the container boundary -- every time you rebuild or restart the container, this setup risks breaking. Containers are meant to be disposable; SSL state is not.

By running Nginx on the **host**, you get a clean separation:

```
Internet                   VPS host                 Docker
───────── ──► port 443 ──► Nginx (SSL termination) ──► 127.0.0.1:8088 ──► Apache (PHP app)
                           manages certificates          serves ZF2
                           via Certbot                   no SSL awareness needed
```

- **Certbot** runs on the host, obtains certificates from Let's Encrypt, and auto-renews them via a systemd timer -- no container involvement at all
- **Nginx** handles HTTPS termination and forwards plain HTTP to the container
- **The container stays identical to development** -- same Dockerfile, same config, no SSL modifications
- If you rebuild/replace the container, SSL continues working untouched

This is the standard pattern for running Docker applications on a single VPS.

#### Install Nginx and Certbot

These run on the host OS, outside Docker:

```bash
sudo apt install -y nginx certbot python3-certbot-nginx
```

#### Create the Nginx virtual host

Create `/etc/nginx/sites-available/zf2demo`:

```nginx
server {
    listen 80;
    server_name yourdomain.com;

    # Forward all requests to the Docker container.
    # Nginx talks plain HTTP to the container on localhost --
    # SSL termination happens here at the Nginx layer.
    location / {
        proxy_pass http://127.0.0.1:8088;

        # Pass the original client info to the app.
        # Without these headers, the ZF2 app would see every request
        # as coming from 127.0.0.1 (Nginx itself).
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;

        # Tells the app the original request was HTTPS,
        # so it can generate correct URLs and set secure cookies.
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

**What the `proxy_set_header` directives do:**

| Header | Purpose |
|--------|---------|
| `Host` | Preserves the original domain name so the app knows which site was requested |
| `X-Real-IP` | The actual client IP address (not Nginx's `127.0.0.1`) |
| `X-Forwarded-For` | Chain of proxy IPs the request passed through -- used by the session `RemoteAddr` validator |
| `X-Forwarded-Proto` | Whether the client connected via `http` or `https` -- the app needs this to generate correct redirect URLs and to know when `cookie_secure` should apply |

#### Enable the site and test the config

```bash
# Create a symlink to enable the site
sudo ln -s /etc/nginx/sites-available/zf2demo /etc/nginx/sites-enabled/

# Remove the default Nginx welcome page (it would conflict on port 80)
sudo rm -f /etc/nginx/sites-enabled/default

# start nginx
sudo systemctl start nginx

# Validate the config syntax before reloading
sudo nginx -t && sudo systemctl reload nginx
```

#### Troubleshooting: Nginx fails to start

If nginx fails to start, it is likely that apache2 is running and taking over port 80.
You can check with:
```bash
sudo ss -tlnp | grep :80
```

Stop and disable apache2 if it is running on port 80, as nginx will take over
```bash
sudo systemctl stop apache2
sudo systemctl disable apache2
```


At this point, `http://yourdomain.com` should reach the ZF2 app (plain HTTP, no SSL yet).

#### Troubleshooting: 502 Bad Gateway

Check that the `app` container is running and that the port binding is correct:

```bash
docker ps
```

If the container is running but you get 502, check the container logs:

```bash
cd /var/www/ZF2_Demo
docker compose logs app --tail=30
```

Container logs show everything the PHP/Apache container has output — errors, warnings, and request logs. Specifically you'd see:

- Apache errors — if the app failed to start or has config issues
- PHP errors — fatal errors, missing files, failed DB connection
- Composer issues — if dependencies weren't installed
- Request logs — each HTTP request and its response code





#### Obtain an SSL certificate with Certbot

```bash
sudo certbot --nginx -d yourdomain.com
```

You'll be asked a few questions:

- Email address (for renewal notices) - mandatory
- Agree to the terms of service
- Whether to share your email with the EFF (optional)

Certbot will:
1. Verify you own the domain (via an HTTP challenge on port 80)
2. Obtain a free SSL certificate from Let's Encrypt
3. Automatically modify your Nginx config to:
   - Listen on port 443 with SSL
   - Reference the certificate and private key files
   - Redirect all HTTP (port 80) traffic to HTTPS
4. Install a systemd timer that renews the certificate automatically before it expires (every 60-90 days)

After Certbot finishes, your Nginx config will look approximately like this (managed by Certbot -- you don't need to edit this manually):

```nginx
server {
    server_name yourdomain.com;

    location / {
        proxy_pass http://127.0.0.1:8088;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    listen 443 ssl;                                          # added by Certbot
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;
}

server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$host$request_uri;                    # added by Certbot
}
```

#### Verify auto-renewal

```bash
sudo systemctl status certbot.timer
sudo certbot renew --dry-run
```

If the dry run succeeds, certificates will renew automatically with no manual intervention.

### 6. Configure the Revolut webhook

In [Revolut Business](https://business.revolut.com):
1. Go to **APIs** > **Merchant API**
2. Set the webhook URL to: `https://yourdomain.com/payment/webhook`
3. Copy the webhook signing secret into `REVOLUT_WEBHOOK_SECRET` in `.env` on the VPS

### 7. Verify

```bash
curl -I https://yourdomain.com
docker compose ps
docker compose logs app --tail=20
```

---

## Option B: Docker Deployment Alongside an Existing Apache Site

Use this option when the VPS already runs a native Apache installation serving another site (e.g. kittbg.com), and you want to add ZF2_Demo in Docker without disrupting the existing setup.

### Architecture
```
Internet
    │
    ▼
Native Apache (port 80/443)
    ├── kittbg.com        → served directly by Apache
    └── demo.kittbg.com   → proxied to Docker container on port 8088
                                        │
                                        ▼
                              Docker (Apache + PHP 7.4)
                              ZF2_Demo on 127.0.0.1:8088
```

No Nginx is needed. The existing native Apache handles SSL and acts as a reverse proxy for the Docker container.

### Prerequisites

- Apache `proxy` modules enabled:
```bash
sudo a2enmod proxy proxy_http headers
sudo systemctl restart apache2
```

- Docker installed (see [Install Docker](#1-install-docker) in Option A)

### 1. Clone and configure
```bash
cd /var/www
sudo git clone https://github.com/ProgressBG-WWW-Courses/ZF2_Demo ZF2_Demo
sudo chown -R $USER:$USER /var/www/ZF2_Demo  # transfer ownership to the current user
cd ZF2_Demo
cp .env.example .env
```

Edit `.env`:
```
DB_HOST=db
DB_PORT=3306
DB_NAME=hotel_db
DB_USER=hotel_user
DB_PASSWORD=<strong-random-password>
DB_ROOT_PASSWORD=<strong-random-password>

REVOLUT_ENVIRONMENT=prod
REVOLUT_API_URL=https://merchant.revolut.com
REVOLUT_API_SECRET_KEY=sk_live_...
REVOLUT_API_PUBLIC_KEY=pk_live_...
REVOLUT_WEBHOOK_SECRET=wsk_live_...

APP_PUBLIC_URL=https://demo.kittbg.com
```

### 2. Adjust port binding

Edit `docker-compose.yml` so the container is only accessible from localhost:
```yaml
services:
  app:
    ports:
      - "127.0.0.1:8088:80"   # only accessible via reverse proxy
  db:
    ports: []                  # no external access to DB
```

### 3. Start the containers
```bash
docker compose up -d --build
docker compose exec app composer install --no-dev --optimize-autoloader
```

> **Do not skip the composer step** — the app will return a 500 error until dependencies are installed.

Verify both containers are running:
```bash
docker compose ps
```

### 4. Add an Apache virtual host

Create `/etc/apache2/sites-available/zf2demo.conf`:
```apache
<VirtualHost *:80>
    ServerName demo.kittbg.com

    ProxyPreserveHost On
    ProxyPass / http://127.0.0.1:8088/
    ProxyPassReverse / http://127.0.0.1:8088/

    # Pass original client info to the app
    RequestHeader set X-Forwarded-Proto "http"
    RequestHeader set X-Real-IP "%{REMOTE_ADDR}s"
</VirtualHost>
```

Enable the site:
```bash
sudo a2ensite zf2demo.conf
sudo apache2ctl configtest && sudo systemctl reload apache2
```

Verify the app is reachable at `http://demo.kittbg.com`.

### 5. Obtain an SSL certificate

Prerequisites: Install `certbot` and the Apache plugin:

```bash
sudo apt install python3-certbot-apache -y
```

Now run certbot:
```bash
sudo certbot --apache -d demo.kittbg.com
```

Certbot will automatically modify the virtual host to add SSL and redirect HTTP to HTTPS. After it completes, update the `RequestHeader` in the virtual host to reflect HTTPS:
```apache
RequestHeader set X-Forwarded-Proto "https"
```

Then reload Apache:
```bash
sudo systemctl reload apache2
```

### 6. Configure the Revolut webhook

In [Revolut Business](https://business.revolut.com) (production):
1. Go to **APIs** > **Merchant API**
2. Set the webhook URL to: `https://demo.kittbg.com/payment/webhook`
3. Copy the webhook signing secret and update `REVOLUT_WEBHOOK_SECRET` in `.env`

### 7. Verify
```bash
curl -I https://demo.kittbg.com
docker compose ps
docker compose logs app --tail=20
```


## Option C: Native Installation

Install all services directly on the VPS without Docker. Gives full control over the stack.

### Architecture

```
Internet
    │
    ▼
Apache (port 80/443)           ← host OS, SSL termination via Certbot
    │
    ├── PHP 7.4 (mod_php)      → ZF2 application code
    │
    └── MariaDB (localhost:3306)
```

Everything runs directly on the host OS — no containers. Apache serves PHP via `mod_php` and connects to MariaDB on `localhost`.

### 1. Install system packages

```bash
sudo apt update && sudo apt install -y \
    apache2 \
    mariadb-server \
    php7.4 php7.4-cli php7.4-mysql php7.4-intl php7.4-xml php7.4-mbstring php7.4-curl \
    libapache2-mod-php7.4 \
    unzip curl git certbot python3-certbot-apache
```

> On Ubuntu 22.04+, PHP 7.4 may require the `ondrej/php` PPA:
> ```bash
> sudo add-apt-repository ppa:ondrej/php
> sudo apt update
> ```

### 2. Enable Apache modules

```bash
sudo a2enmod rewrite ssl
sudo systemctl restart apache2
```

### 3. Set up MariaDB

```bash
sudo mysql_secure_installation
```

Create the database and user:

```bash
sudo mysql -u root -p <<'SQL'
CREATE DATABASE hotel_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'hotel_user'@'localhost' IDENTIFIED BY '<strong-random-password>';
GRANT ALL PRIVILEGES ON hotel_db.* TO 'hotel_user'@'localhost';
FLUSH PRIVILEGES;
SQL
```

Import the schema and seed data:

```bash
sudo mysql -u root -p hotel_db < /var/www/ZF2_Demo/data/sql/001_payment_orders.sql
sudo mysql -u root -p hotel_db < /var/www/ZF2_Demo/data/sql/002_rooms.sql
sudo mysql -u root -p hotel_db < /var/www/ZF2_Demo/data/sql/003_users.sql
```

### 4. Deploy the application

```bash
cd /var/www
git clone <repository-url> ZF2_Demo
cd ZF2_Demo
cp .env.example .env
```

Edit `.env` for production:

```
DB_HOST=localhost
DB_PORT=3306
DB_NAME=hotel_db
DB_USER=hotel_user
DB_PASSWORD=<strong-random-password>
DB_ROOT_PASSWORD=<not-needed-for-native>

REVOLUT_ENVIRONMENT=prod
REVOLUT_API_URL=https://merchant.revolut.com
REVOLUT_API_SECRET_KEY=sk_live_...
REVOLUT_API_PUBLIC_KEY=pk_live_...
REVOLUT_WEBHOOK_SECRET=wsk_live_...

APP_PUBLIC_URL=https://yourdomain.com
```

### 5. Install Composer and PHP dependencies

```bash
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
cd /var/www/ZF2_Demo
composer install --no-dev --optimize-autoloader
```

### 6. Set file permissions

```bash
sudo chown -R www-data:www-data /var/www/ZF2_Demo
sudo chmod -R 755 /var/www/ZF2_Demo

# Writable directories for logs and cache
sudo chmod -R 775 /var/www/ZF2_Demo/data
```

### 7. Configure Apache virtual host

Create `/etc/apache2/sites-available/zf2demo.conf`:

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /var/www/ZF2_Demo/public

    <Directory /var/www/ZF2_Demo/public>
        AllowOverride All
        Require all granted
    </Directory>

    # Deny access to sensitive directories
    <DirectoryMatch "^/var/www/ZF2_Demo/(config|data|module|vendor)">
        Require all denied
    </DirectoryMatch>

    ErrorLog ${APACHE_LOG_DIR}/zf2demo-error.log
    CustomLog ${APACHE_LOG_DIR}/zf2demo-access.log combined
</VirtualHost>
```

Enable the site:

```bash
sudo a2ensite zf2demo.conf
sudo a2dissite 000-default.conf
sudo apache2ctl configtest && sudo systemctl reload apache2
```

### 8. Obtain SSL certificate

```bash
sudo certbot --apache -d yourdomain.com
```

Certbot modifies the vhost to add SSL directives and sets up auto-renewal.

### 9. Configure the Revolut webhook

In [Revolut Business](https://business.revolut.com) (production):
1. Go to **APIs** > **Merchant API**
2. Set the webhook URL to: `https://yourdomain.com/payment/webhook`
3. Copy the webhook signing secret and update `REVOLUT_WEBHOOK_SECRET` in `.env`

### 10. Verify

```bash
curl -I https://yourdomain.com
sudo systemctl status apache2
sudo systemctl status mariadb
```

---

## Production Security Hardening

Apply these changes regardless of deployment option.

### Disable exception display

In `module/Application/config/module.config.php`, change:

```php
'display_not_found_reason' => false,
'display_exceptions'       => false,
```

Or better -- create a production-only override file `config/autoload/view.local.php`:

```php
<?php
return [
    'view_manager' => [
        'display_not_found_reason' => false,
        'display_exceptions'       => false,
    ],
];
```

Files matching `*.local.php` are gitignored and override `*.global.php` settings.

### Enable secure session cookies

In `config/autoload/session.global.php` (or a `session.local.php` override):

```php
'cookie_secure' => true,   // requires HTTPS
```

### Restrict `.env` access

The `.env` file is in the project root, outside the `public/` document root, so it is not directly accessible. Verify this is the case. If using native Apache, the `<DirectoryMatch>` rule above blocks access to `config/`, `data/`, `module/`, and `vendor/`.

### Firewall

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'    # or 'Apache Full' for native install
sudo ufw enable
```

### Disable root SSH login

In `/etc/ssh/sshd_config`:

```
PermitRootLogin no
PasswordAuthentication no    # use SSH keys only
```

```bash
sudo systemctl restart sshd
```

### File permissions checklist

| Path                  | Owner       | Permissions | Notes                        |
|-----------------------|-------------|-------------|------------------------------|
| `/var/www/ZF2_Demo`   | `www-data`  | `755`       | Project root                 |
| `data/`               | `www-data`  | `775`       | Logs, writable by app        |
| `.env`                | `www-data`  | `640`       | Credentials -- not world-readable |
| `config/autoload/`    | `www-data`  | `750`       | Config files with secrets    |
| `vendor/`             | `www-data`  | `755`       | Composer dependencies        |

---

## SSL/TLS with Let's Encrypt

Both deployment options use Certbot for free SSL certificates.

### Auto-renewal

Certbot installs a systemd timer for automatic renewal. Verify:

```bash
sudo systemctl status certbot.timer
sudo certbot renew --dry-run
```

### Force HTTPS redirect

Certbot typically adds this automatically. If not, for Nginx add:

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$host$request_uri;
}
```

For Apache, Certbot adds a `RewriteRule` to the vhost. Verify it redirects `http://` to `https://`.

---

## Revolut Production Setup

1. Log in to [Revolut Business](https://business.revolut.com) (production, not sandbox)
2. Go to **APIs** > **Merchant API**
3. Generate production API keys (`sk_live_...`)
4. Set the webhook URL to `https://yourdomain.com/payment/webhook`
5. Copy the webhook signing secret (`wsk_live_...`)
6. Update `.env` on the VPS with production values:
   - `REVOLUT_ENVIRONMENT=prod`
   - `REVOLUT_API_URL=https://merchant.revolut.com`
   - `REVOLUT_API_SECRET_KEY=sk_live_...`
   - `REVOLUT_WEBHOOK_SECRET=wsk_live_...`

> No ngrok is needed in production -- Revolut reaches your server directly via the domain.

---

## Backups

### Database

Set up a daily cron job:

```bash
sudo crontab -e
```

Add:

```
0 3 * * * mysqldump -u hotel_user -p'<password>' hotel_db | gzip > /var/backups/hotel_db_$(date +\%Y\%m\%d).sql.gz
```

For Docker deployments:

```
0 3 * * * docker compose -f /var/www/ZF2_Demo/docker-compose.yml exec -T db \
  mysqldump -u hotel_user -p'<password>' hotel_db | gzip > /var/backups/hotel_db_$(date +\%Y\%m\%d).sql.gz
```

### Application files

```bash
# Simple rsync to a backup location
rsync -az --exclude='vendor/' --exclude='data/php-errors.log' \
  /var/www/ZF2_Demo/ /var/backups/zf2demo/
```

---

## Monitoring

### Health check

A simple uptime check:

```bash
curl -sf https://yourdomain.com/ > /dev/null && echo "OK" || echo "DOWN"
```

### Log monitoring

```bash
# Application errors
tail -f /var/www/ZF2_Demo/data/php-errors.log

# Apache logs (native install)
tail -f /var/log/apache2/zf2demo-error.log

# Docker logs
docker compose -f /var/www/ZF2_Demo/docker-compose.yml logs -f app
```

---

# Reference

## Available Routes

| URL                         | Method   | Min. Role | Description                          |
|-----------------------------|----------|-----------|--------------------------------------|
| `/`                         | GET      | guest     | Homepage with route reference        |
| `/room`                     | GET      | staff     | List all hotel rooms                 |
| `/room/detail/:id`          | GET      | staff     | Room details + payment form          |
| `/room/search`              | GET/POST | staff     | Search rooms by type and price       |
| `/room/create`              | GET/POST | manager   | Create a new room (CSRF protected)   |
| `/room/about`               | GET      | staff     | Static about page                    |
| `/api/rooms`                | GET      | guest     | JSON list of all rooms               |
| `/api/rooms/:id`            | GET      | guest     | JSON detail for a single room        |
| `/auth/login`               | GET/POST | guest     | Login form                           |
| `/auth/logout`              | GET      | any       | Logout (destroys session)            |
| `/payment/create`           | POST     | staff     | Create a Revolut order               |
| `/payment/success`          | GET      | staff     | Post-payment redirect handler        |
| `/payment/cancel`           | GET      | staff     | Payment cancellation handler         |
| `/payment/webhook`          | POST     | guest     | Revolut webhook (HMAC verified)      |
| `/payment/status/:order_id` | GET      | staff     | JSON endpoint for payment polling    |
| `/access-denied`            | GET      | any       | 403 error page                       |

## Default User Accounts

| Username | Password     | Role    |
|----------|-------------|---------|
| admin    | admin123    | admin   |
| manager  | manager123  | manager |
| staff    | staff123    | staff   |
| guest    | guest123    | guest   |

Role hierarchy: `guest` < `staff` < `manager` < `admin`. Each role inherits all permissions of the roles below it.

> **Production:** Change these passwords immediately after deployment, or replace the seed data in `data/sql/003_users.sql` with production credentials before first boot.

## Dev Scripts

| Script | Purpose |
|--------|---------|
| `scripts/dev_start.sh` | Starts Docker containers + ngrok tunnel |
| `scripts/revolut_get_webhooks_list.sh` | Queries the Revolut API for configured webhooks |
| `scripts/revolut_get_webhook_secret.sh` | Lists webhook IDs and their signing secrets |

## Error Logs

| Log | Location |
|-----|----------|
| PHP / Apache errors | `data/php-errors.log` |
| Application log | `data/application.log` |
| Docker container logs | `docker compose logs app` / `docker compose logs db` |

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Port 8088 already in use | Change the port mapping in `docker-compose.yml` (e.g. `"8089:80"`) |
| Port 3309 already in use | Change the db port mapping in `docker-compose.yml` |
| `composer install` fails | Run inside the container: `docker compose exec app composer install` |
| Page shows blank/500 error | Check `data/php-errors.log` for details |
| "Class not found" errors | Run `docker compose exec app composer dump-autoload` |
| Payment redirect fails | Ensure `APP_PUBLIC_URL` in `.env` matches the actual public URL |
| Webhook not received | Run `scripts/revolut_get_webhooks_list.sh` to verify config; ensure ngrok (dev) or domain (prod) is reachable |
| Database connection refused | Wait for db health check: `docker compose ps` should show db as healthy |
| Container won't start | Run `docker compose logs app` or `docker compose logs db` to see errors |
| Database schema missing | Remove volume and restart: `docker compose down -v && docker compose up -d` |
| ngrok auth error | Run `ngrok config add-authtoken <your-token>` -- each developer needs their own ngrok account |
| ngrok domain error | The static domain in `scripts/dev_start.sh` is account-specific -- claim your own at ngrok.com and update `NGROK_DOMAIN` + `APP_PUBLIC_URL` |
| SSL certificate issues | Run `sudo certbot renew --dry-run` to diagnose; check domain DNS A record |
| Apache 403 Forbidden | Verify `AllowOverride All` and `Require all granted` in vhost; check file ownership |
| Revolut webhook returns 401 | Webhook signing secret mismatch -- re-copy `REVOLUT_WEBHOOK_SECRET` from Revolut dashboard |
