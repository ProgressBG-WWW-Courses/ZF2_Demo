# Code Documentation

Full technical reference for the ZF2 Hotel Booking Demo application.

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Project Structure](#project-structure)
3. [Entry Point and Request Flow](#entry-point-and-request-flow)
4. [Configuration](#configuration)
5. [Module: Application](#module-application)
6. [Module: Room](#module-room)
7. [Module: Auth](#module-auth)
8. [Module: Payment](#module-payment)
9. [Docker Infrastructure](#docker-infrastructure)
10. [Dependency Injection and Factories](#dependency-injection-and-factories)
11. [Security Measures](#security-measures)

---

## Architecture Overview

The application is built on **Zend Framework 2 (ZF2) v2.5** and follows the MVC (Model-View-Controller) pattern. It runs on **PHP 7.4** with **Apache** and uses **MariaDB 10.11** for data persistence via **Doctrine ORM 2.7**.

**Tech Stack:**

| Layer        | Technology           |
|--------------|----------------------|
| Framework    | Zend Framework 2.5   |
| Language     | PHP 7.4              |
| Web Server   | Apache with mod_rewrite |
| Database     | MariaDB 10.11        |
| ORM          | Doctrine ORM 2.7 via `doctrine/doctrine-orm-module` |
| Payments     | Revolut Merchant API |
| Containerization | Docker + Docker Compose |

The application consists of four ZF2 modules:
- **Application** -- Homepage and shared layout/error templates
- **Room** -- Hotel room management (listing, search, creation, details) backed by the `rooms` table
- **Auth** -- User authentication with bcrypt passwords and role-based access control, backed by the `users` table
- **Payment** -- Revolut payment integration (order creation, webhooks, status polling) backed by the `payment_orders` table

---

## Project Structure

```
ZF2_Demo/
├── .env                                    # Environment variables (Revolut & DB credentials)
├── composer.json                           # PHP dependencies (ZF2, Doctrine ORM Module)
├── docker-compose.yml                      # MariaDB 10.11 + PHP 7.4 Apache services
├── Dockerfile                              # Custom PHP 7.4 image with PDO, intl, Composer
├── test_payment.py                         # Playwright e2e payment test suite
│
├── config/
│   ├── application.config.php              # Module registry (DoctrineModule, DoctrineORMModule, app modules)
│   └── autoload/
│       ├── db.global.php                   # .env loader + Doctrine ORM connection config
│       └── payment.global.php              # Revolut API credentials
│
├── data/
│   └── sql/
│       ├── 001_payment_orders.sql          # payment_orders table DDL
│       ├── 002_rooms.sql                   # rooms table DDL + seed data (5 rooms)
│       └── 003_users.sql                   # users table DDL + seed data (admin, staff)
│
├── public/
│   ├── index.php                           # Front controller (application entry point)
│   └── .htaccess                           # Apache URL rewriting rules
│
└── module/
    ├── Application/
    │   ├── Module.php                      # Module bootstrap
    │   ├── config/module.config.php        # Routes, controllers, view manager
    │   ├── src/Application/Controller/
    │   │   └── IndexController.php         # Homepage controller
    │   └── view/
    │       ├── layout/layout.phtml         # Shared HTML layout template
    │       ├── application/index/index.phtml
    │       └── error/{404,index}.phtml     # Error page templates
    │
    ├── Room/
    │   ├── Module.php
    │   ├── config/module.config.php        # Routes + Doctrine annotation driver for Room\Entity
    │   ├── src/Room/
    │   │   ├── Controller/RoomController.php
    │   │   ├── Entity/RoomEntity.php       # Doctrine entity mapped to `rooms` table
    │   │   ├── Service/RoomService.php     # Room CRUD via Doctrine EntityManager
    │   │   ├── Form/RoomForm.php           # Room creation form (with CSRF)
    │   │   ├── Form/RoomSearchForm.php     # Room search form
    │   │   ├── InputFilter/RoomFilter.php  # Validation rules for room creation
    │   │   ├── InputFilter/RoomSearchFilter.php  # Validation rules for search
    │   │   ├── Factory/RoomControllerFactory.php  # DI factory for RoomController
    │   │   └── Factory/RoomServiceFactory.php     # DI factory for RoomService
    │   └── view/room/room/
    │       ├── index.phtml                 # Room list table
    │       ├── detail.phtml                # Room details + payment UI + polling script
    │       ├── search.phtml                # Search form and results
    │       ├── create.phtml                # Room creation form
    │       └── about.phtml                 # Static about page
    │
    ├── Auth/
    │   ├── Module.php
    │   ├── config/module.config.php        # Routes + Doctrine annotation driver for Auth\Entity
    │   ├── src/Auth/
    │   │   ├── Controller/AuthController.php    # Login/logout handlers
    │   │   ├── Entity/UserEntity.php            # Doctrine entity mapped to `users` table
    │   │   ├── Service/UserService.php          # User lookup via Doctrine EntityManager
    │   │   ├── Factory/AuthControllerFactory.php
    │   │   └── Factory/UserServiceFactory.php
    │   └── view/auth/auth/
    │       └── login.phtml                 # Login form
    │
    └── Payment/
        ├── Module.php
        ├── config/module.config.php        # Routes + Doctrine annotation driver for Payment\Entity
        ├── src/Payment/
        │   ├── Controller/PaymentController.php  # Payment request handlers
        │   ├── Entity/PaymentOrder.php     # Doctrine entity mapped to `payment_orders` table
        │   ├── Service/PaymentService.php  # Revolut API wrapper + Doctrine DB operations
        │   ├── Factory/PaymentServiceFactory.php
        │   └── Factory/PaymentControllerFactory.php
        └── view/payment/payment/
            ├── success.phtml               # Payment success page
            └── cancel.phtml                # Payment cancellation page
```

---

## Entry Point and Request Flow

### Front Controller: `public/index.php`

All HTTP requests are routed through `public/index.php` via Apache's `mod_rewrite` (configured in `public/.htaccess`). The front controller:

1. Sets the working directory to the project root
2. Loads the Composer autoloader (`vendor/autoload.php`)
3. Boots the ZF2 MVC application with the config from `config/application.config.php`

### Request Flow Diagram

```
Browser Request
      │
      ▼
  Apache (.htaccess rewrites all non-file URLs to index.php)
      │
      ▼
  public/index.php (Front Controller)
      │
      ▼
  Zend\Mvc\Application::init()
      │  ├─ Loads modules (Application, Room, Payment)
      │  ├─ Merges config from config/autoload/*.global.php
      │  └─ Builds ServiceManager with all registered services/factories
      │
      ▼
  Router (matches URL against route definitions)
      │
      ▼
  DispatchListener → Controller::*Action()
      │  ├─ Controller uses injected services (RoomService, PaymentService)
      │  ├─ Business logic executes (DB queries, API calls, etc.)
      │  └─ Returns ViewModel or JsonModel with template variables
      │
      ▼
  ViewRenderer (renders .phtml template with ViewModel data)
      │
      ▼
  Layout (wraps content in layout/layout.phtml)
      │
      ▼
  HTTP Response → Browser
```

---

## Configuration

### `config/application.config.php`

Registers Doctrine modules (must load first) and the four application modules:

```php
'modules' => [
    'DoctrineModule',
    'DoctrineORMModule',
    'Application',
    'Room',
    'Auth',
    'Payment',
],
'module_listener_options' => [
    'module_paths'      => ['./module', './vendor'],
    'config_glob_paths' => ['config/autoload/{,*.}{global,local}.php'],
],
```

### `config/autoload/db.global.php`

Reads the `.env` file (simple loader, no third-party dependency) and configures the Doctrine ORM database connection. Loads alphabetically before other config files, so environment variables are available to all modules.

- **Doctrine connection**: host, port, dbname, user, password, charset
- Falls back to hardcoded defaults if env vars are not set

### `config/autoload/payment.global.php`

Exposes Revolut API credentials under the `payment` key:

- **Revolut API credentials**: `api_url`, `secret_key`, `public_key`, `webhook_secret`
- **Application settings**: `environment`, `public_url`

---

## Module: Application

**Namespace:** `Application\`
**Purpose:** Homepage, shared layout, and error templates.

### Controller: `IndexController`

| Action          | Route  | URL | Method | Description                |
|-----------------|--------|-----|--------|----------------------------|
| `indexAction()` | `home` | `/` | GET    | Displays homepage with route reference table |

### Views

| Template                      | Purpose                              |
|-------------------------------|--------------------------------------|
| `layout/layout.phtml`         | Shared HTML wrapper with navigation bar |
| `application/index/index.phtml` | Homepage content                   |
| `error/404.phtml`             | 404 Not Found page                   |
| `error/index.phtml`           | General error/exception page         |

### Configuration

- Controllers registered as **invokables** (no constructor dependencies)
- View manager uses **template_map** (explicit path per template)

---

## Module: Room

**Namespace:** `Room\`
**Purpose:** Hotel room management -- listing, details, search, and creation.

### Controller: `RoomController`

Receives `RoomService` and `PaymentService` via constructor injection (see `RoomControllerFactory`).

| Action           | Route          | URL               | Method   | Description                          |
|------------------|----------------|-------------------|----------|--------------------------------------|
| `indexAction()`  | `room`         | `/room`           | GET      | Lists all rooms in a table           |
| `detailAction()` | `room/detail`  | `/room/detail/:id`| GET      | Room info + payment form + status    |
| `searchAction()` | `room/search`  | `/room/search`    | GET/POST | Search rooms by type and min price   |
| `createAction()` | `room/create`  | `/room/create`    | GET/POST | Create new room with CSRF protection |
| `aboutAction()`  | `room-about`   | `/room/about`     | GET      | Static about page                    |

### Service: `RoomService`

Encapsulates room business logic. Uses Doctrine `EntityManager` to query and persist room records in the `rooms` table.

**Constructor:** `__construct(EntityManager $em)`

| Method                          | Doctrine Call                                      | Description                                       |
|---------------------------------|---------------------------------------------------|---------------------------------------------------|
| `getAll(): RoomEntity[]`        | `$em->getRepository(...)->findBy([], ['id'=>'ASC'])` | Returns all rooms ordered by ID                  |
| `getById(int $id): ?RoomEntity` | `$em->find('Room\Entity\RoomEntity', $id)`         | Returns room by ID, or null                       |
| `save(RoomEntity $room): void`  | `$em->persist($room); $em->flush();`               | Persists a new room to the database               |
| `search(string $type, int $minPrice): RoomEntity[]` | QueryBuilder with optional `WHERE` clauses | Filters rooms by type and/or minimum price |

### Entity: `RoomEntity`

Doctrine-annotated entity mapped to the `rooms` table.

| Property      | Type    | Doctrine Column               |
|---------------|---------|-------------------------------|
| `$id`         | int     | `@ORM\Id`, auto-generated     |
| `$number`     | string  | `VARCHAR(10)`, unique          |
| `$type`       | string  | `VARCHAR(50)` -- Single/Double/Suite (indexed) |
| `$price`      | float   | `DECIMAL(10,2)`               |
| `$description`| string  | `VARCHAR(255)`                |

**Hydration methods:**
- `exchangeArray(array $data)` -- populates entity from an array (hydrate direction)
- `getArrayCopy(): array` -- converts entity to an array (extract direction)

### Forms and Input Filters

| Class              | Purpose                                              |
|--------------------|------------------------------------------------------|
| `RoomForm`         | Room creation form with fields: number, type, price, description, CSRF token |
| `RoomSearchForm`   | Search form with fields: type, min_price             |
| `RoomFilter`       | Validation rules for room creation                   |
| `RoomSearchFilter` | Validation rules for search (optional fields)        |

### Route Configuration

```
/room                 (Literal, parent, may_terminate=true)
├── /detail/:id       (Segment, child, constraint: id=[0-9]+)
├── /search           (Literal, child)
└── /create           (Literal, child)

/room/about           (Literal, standalone)
```

---

## Module: Auth

**Namespace:** `Auth\`
**Purpose:** User authentication with bcrypt password hashing and role-based access control.

### Controller: `AuthController`

Receives `UserService` via constructor injection (see `AuthControllerFactory`).

| Action           | Route          | URL               | Method   | Description                          |
|------------------|----------------|-------------------|----------|--------------------------------------|
| `loginAction()`  | `auth/login`   | `/auth/login`     | GET/POST | Displays login form / processes login |
| `logoutAction()` | `auth/logout`  | `/auth/logout`    | GET      | Clears session and redirects         |

### Service: `UserService`

Uses Doctrine `EntityManager` to look up user records in the `users` table.

**Constructor:** `__construct(EntityManager $em)`

| Method                              | Doctrine Call                                              | Description                        |
|-------------------------------------|-----------------------------------------------------------|------------------------------------|
| `findByUsername(string $username): ?UserEntity` | `$em->getRepository(...)->findOneBy(['username'=>$username])` | Returns user entity or null |

### Entity: `UserEntity`

Doctrine-annotated entity mapped to the `users` table.

| Property       | Type   | Doctrine Column                   |
|----------------|--------|-----------------------------------|
| `$id`          | int    | `@ORM\Id`, auto-generated        |
| `$username`    | string | `VARCHAR(50)`, unique (indexed)   |
| `$passwordHash`| string | `VARCHAR(255)`, column: `password_hash` |
| `$role`        | string | `VARCHAR(20)` -- guest/staff/admin |

Passwords are verified with `password_verify()` against bcrypt hashes stored in `password_hash`.

---

## Module: Payment

**Namespace:** `Payment\`
**Purpose:** Revolut Merchant API integration for hotel room payments.

### Controller: `PaymentController`

Receives `PaymentService` via constructor injection (see `PaymentControllerFactory`).

| Action            | Route             | URL                         | Method | Description                           |
|-------------------|-------------------|-----------------------------|--------|---------------------------------------|
| `createAction()`  | `payment/create`  | `/payment/create`           | POST   | Creates Revolut order, redirects to checkout |
| `successAction()` | `payment/success` | `/payment/success?order_id=`| GET    | Post-payment redirect handler         |
| `cancelAction()`  | `payment/cancel`  | `/payment/cancel?order_id=` | GET    | User cancelled checkout               |
| `webhookAction()` | `payment/webhook` | `/payment/webhook`          | POST   | Processes Revolut webhook events      |
| `statusAction()`  | `payment/status`  | `/payment/status/:order_id` | GET    | JSON endpoint for frontend polling    |

#### `createAction()` Details

1. Validates POST parameters: `room_id`, `amount`, `currency`, `description`
2. Sanitizes description with `strip_tags()`
3. Builds redirect URL using `APP_PUBLIC_URL` (or falls back to request URL)
4. Calls `PaymentService::createOrder()` to create a Revolut hosted-checkout order
5. Redirects the browser to Revolut's `checkout_url`

#### `webhookAction()` Details

1. Rejects non-POST requests (HTTP 405)
2. Extracts `Revolut-Signature` and `Revolut-Request-Timestamp` headers
3. Verifies HMAC-SHA256 signature via `PaymentService::verifyWebhookSignature()`
4. Parses JSON payload and maps event types to payment states
5. Updates the local database via `PaymentService::updatePaymentState()`

**Webhook event-to-state mapping:**

| Revolut Event               | Local State  |
|------------------------------|-------------|
| `ORDER_COMPLETED`            | COMPLETED   |
| `ORDER_PAYMENT_COMPLETED`    | COMPLETED   |
| `ORDER_PAYMENT_DECLINED`     | FAILED      |
| `ORDER_PAYMENT_FAILED`       | FAILED      |
| `ORDER_PAYMENT_CANCELLED`    | CANCELLED   |
| `ORDER_AUTHORISED`           | AUTHORISED  |

#### `statusAction()` Details

1. Reads payment state from local database
2. If state is PENDING/AUTHORISED and the record is older than 30 seconds, falls back to the Revolut API
3. Returns JSON: `{ success: true, state: "COMPLETED", order_id: "..." }`

### Service: `PaymentService`

Core payment logic -- wraps the Revolut Merchant API and manages local payment records via Doctrine EntityManager.

**Constructor parameters:** `array $config` (Revolut credentials), `EntityManager $em`

| Method | Parameters | Returns | Description |
|--------|-----------|---------|-------------|
| `createOrder()` | `$roomId, $amount, $currency, $description, $redirectUrl` | `PaymentOrder` | Creates Revolut order via API, persists entity to DB |
| `getOrderStatus()` | `$orderId` | `array` | Fetches order from Revolut API, synthesizes FAILED for declined payments |
| `verifyWebhookSignature()` | `$body, $signature, $timestamp` | `bool` | HMAC-SHA256 verification with replay protection |
| `updatePaymentState()` | `$orderId, $state` | `void` | Finds entity, checks terminal states, sets + flushes |
| `getPaymentByOrderId()` | `$orderId` | `?PaymentOrder` | Fetches payment entity from DB |
| `getLatestPaymentForRoom()` | `$roomId` | `?PaymentOrder` | Most recent payment for a room |
| `getPublicKey()` | -- | `string` | Returns the Revolut public key |
| `getPublicUrl()` | -- | `string` | Returns the configured public URL |

#### Private Methods

| Method | Description |
|--------|-------------|
| `apiRequest($method, $path, $body)` | Authenticated HTTPS request to Revolut API (Bearer token, SSL verification, 30s timeout) |
| `validateOrderId($orderId)` | Validates order ID format against `[a-zA-Z0-9_-]+` |

### Entity: `PaymentOrder`

Doctrine-annotated entity mapped to the `payment_orders` table.

| Property       | Type     | DB Column       | Notes                        |
|----------------|----------|-----------------|------------------------------|
| `$id`          | int      | `id`            | Auto-increment primary key   |
| `$orderId`     | string   | `order_id`      | Revolut order ID (unique)    |
| `$roomId`      | int      | `room_id`       | FK to room (indexed)         |
| `$amount`      | float    | `amount`        | DECIMAL(10,2)                |
| `$currency`    | string   | `currency`      | ISO 4217 (3 chars), default GBP |
| `$state`       | string   | `state`         | Payment state (indexed)      |
| `$checkoutUrl` | string   | `checkout_url`  | Revolut hosted checkout URL  |
| `$createdAt`   | datetime | `created_at`    | Record creation timestamp    |
| `$updatedAt`   | datetime | `updated_at`    | Last update timestamp        |

### Route Configuration

```
/payment              (Literal, parent, may_terminate=false)
├── /create           (Literal, child)
├── /success          (Literal, child)
├── /cancel           (Literal, child)
├── /webhook          (Literal, child)
└── /status/:order_id (Segment, child, constraint: [a-zA-Z0-9_-]+)
```

### Frontend Polling (JavaScript in `detail.phtml`)

When a payment is in PENDING or AUTHORISED state, the room detail page includes an inline JavaScript poller:

- **First 30 seconds:** polls `/payment/status/:order_id` every 3 seconds (waiting for webhook)
- **After 30 seconds:** polls every 10 seconds (server-side API fallback kicks in)
- **Maximum duration:** 5 minutes
- **On terminal state:** page auto-reloads to show final payment status

---

## Docker Infrastructure

### `Dockerfile`

Builds a custom PHP 7.4 Apache image:

1. Sets document root to `/var/www/html/public` (ZF2 front controller location)
2. Enables Apache `mod_rewrite` and `AllowOverride All`
3. Redirects Apache error log to `/var/www/html/data/php-errors.log`
4. Installs system dependencies: `libicu-dev`, `unzip`
5. Installs PHP extensions: `pdo`, `pdo_mysql`, `intl`
6. Configures PHP error logging (errors to file, display off)
7. Installs Composer 2 from official image
8. Sets proper file ownership (`www-data`)

### `docker-compose.yml`

| Service | Image           | Host Port | Internal Port | Volumes                            |
|---------|-----------------|-----------|---------------|------------------------------------|
| `app`   | Build from `./` | 8088      | 80            | `.:/var/www/html` (bind mount)     |
| `db`    | mariadb:10.11   | 3309      | 3306          | `db_data` (named volume) + `./data/sql:/docker-entrypoint-initdb.d` |

- The `app` service depends on `db` with `condition: service_healthy`
- MariaDB health check: `healthcheck.sh --connect --innodb_initialized` (5s interval, 10 retries)
- SQL files in `data/sql/` are auto-executed on first MariaDB startup

---

## Dependency Injection and Factories

The application uses ZF2's Service Manager for dependency injection. Controllers receive their dependencies through constructor injection via factories.

### Factory Chain

All services receive a `Doctrine\ORM\EntityManager` instance (provided by `DoctrineORMModule`) instead of raw database connections.

```
ServiceManager
├── Doctrine\ORM\EntityManager (provided by DoctrineORMModule)
│
├── RoomServiceFactory::createService()
│   ├── Fetches Doctrine\ORM\EntityManager
│   └── Returns new RoomService($em)
│
├── UserServiceFactory::createService()
│   ├── Fetches Doctrine\ORM\EntityManager
│   └── Returns new UserService($em)
│
├── PaymentServiceFactory::createService()
│   ├── Reads 'payment' config (Revolut credentials)
│   ├── Fetches Doctrine\ORM\EntityManager
│   └── Returns new PaymentService($config, $em)
│
├── RoomControllerFactory::createService()
│   ├── Fetches RoomService from ServiceManager
│   ├── Fetches PaymentService from ServiceManager
│   └── Returns new RoomController($roomService, $paymentService)
│
├── AuthControllerFactory::createService()
│   ├── Fetches UserService from ServiceManager
│   └── Returns new AuthController($userService)
│
└── PaymentControllerFactory::createService()
    ├── Fetches PaymentService from ServiceManager
    └── Returns new PaymentController($paymentService)
```

### Registration in `module.config.php`

```php
// Services (shared instances)
'service_manager' => [
    'factories' => [
        'RoomService'    => 'Room\Factory\RoomServiceFactory',
        'UserService'    => 'Auth\Factory\UserServiceFactory',
        'PaymentService' => 'Payment\Factory\PaymentServiceFactory',
    ],
],

// Controllers (per-request instances)
'controllers' => [
    'factories' => [
        'Room\Controller\Room'       => 'Room\Factory\RoomControllerFactory',
        'Auth\Controller\Auth'       => 'Auth\Factory\AuthControllerFactory',
        'Payment\Controller\Payment' => 'Payment\Factory\PaymentControllerFactory',
    ],
],
```

### Doctrine Annotation Driver (per module)

Each module registers its entity namespace with Doctrine's annotation driver in its `module.config.php`:

```php
'doctrine' => [
    'driver' => [
        '<module>_annotation_driver' => [
            'class' => 'Doctrine\ORM\Mapping\Driver\AnnotationDriver',
            'paths' => [__DIR__ . '/../src/<Module>/Entity'],
        ],
        'orm_default' => [
            'drivers' => ['<Module>\Entity' => '<module>_annotation_driver'],
        ],
    ],
],
```

---

## Security Measures

### API Communication
- All Revolut API calls use **HTTPS** with SSL certificate verification (`CURLOPT_SSL_VERIFYPEER`, `CURLOPT_SSL_VERIFYHOST`)
- **Bearer token** authentication (secret key never exposed to frontend)
- API version pinned to `2025-12-04` via header

### Webhook Security
- **HMAC-SHA256** signature verification on all webhook payloads
- **Replay attack prevention**: rejects timestamps older than 5 minutes
- **Timing-safe comparison** using `hash_equals()` (prevents timing side-channels)

### Input Validation
- Amount validated as positive number before API calls
- Currency validated against ISO 4217 format (`/^[A-Z]{3}$/`)
- Order ID validated against `[a-zA-Z0-9_-]+`
- Description sanitized with `strip_tags()`
- Form submissions validated via ZF2 InputFilter classes
- CSRF protection on room creation form

### Database Security
- All database access is through **Doctrine ORM** (parameterized queries via DQL/QueryBuilder)
- No raw SQL -- entity repository methods and QueryBuilder prevent SQL injection
- Terminal payment states (COMPLETED, FAILED, CANCELLED) cannot be overwritten

### Output Escaping
- All user-visible data escaped with `$this->escapeHtml()` and `$this->escapeHtmlAttr()`
- JSON output via `JsonModel` (auto-encodes)
