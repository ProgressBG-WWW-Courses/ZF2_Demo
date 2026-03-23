# ZF2 Demo — Step-by-Step Guide

## Overview

This project demonstrates a **Zend Framework 2** application with four modules:

- **Application** — "Hello World" entry point (Lab 7 / Lab 8)
- **Room** — Hotel room listing with advanced routing (Lab 8 / Lab 9)
- **Auth** — User authentication with bcrypt passwords and role-based access
- **Payment** — Revolut payment integration for room bookings

All data is persisted in MariaDB via **Doctrine ORM**. This guide covers the foundational Application and Room modules. See [CODE_DOCUMENTATION.md](CODE_DOCUMENTATION.md) for the full technical reference.

> **PHP Version Note**: ZF2 requires PHP 5.6–7.4. This project provides a Docker container with PHP 7.4 + Apache.

---

## Requirements

| Tool | Windows | Linux |
|------|---------|-------|
| Docker | [Docker Desktop](https://www.docker.com/products/docker-desktop/) (WSL2 backend recommended) | Docker Engine + Docker Compose plugin |
| Git | Git for Windows | git |
| Browser | Any | Any |

> **Windows note**: Docker Desktop automatically uses WSL2 on modern Windows 10/11. Make sure virtualization is enabled in BIOS.

---

## Project Structure

```
ZF2_Demo/
├── .env                             # Environment variables (Revolut & DB credentials)
├── composer.json                    # Composer dependencies (ZF2, Doctrine ORM Module)
├── composer.lock
├── Dockerfile                       # PHP 7.4 + Apache image
├── docker-compose.yml               # Two services: app (port 8088) + db (port 3309)
├── .gitattributes                   # Enforces LF line endings on all platforms
├── config/
│   ├── application.config.php      # Lists modules to load (Doctrine + app modules)
│   └── autoload/
│       ├── db.global.php           # .env loader + Doctrine ORM connection config
│       └── payment.global.php      # Revolut API credentials
├── data/
│   ├── php-errors.log              # PHP/Apache error log (created at runtime)
│   └── sql/                        # Auto-executed on first DB startup
│       ├── 001_payment_orders.sql
│       ├── 002_rooms.sql
│       └── 003_users.sql
├── public/
│   ├── index.php                   # Front controller — all requests enter here
│   └── .htaccess                   # Rewrites all requests to index.php
└── module/
    ├── Application/                 # Lab 7/8: Hello World module
    │   ├── Module.php
    │   ├── config/module.config.php
    │   ├── src/Application/Controller/IndexController.php
    │   └── view/
    │       ├── layout/layout.phtml
    │       ├── application/index/index.phtml
    │       └── error/{404,index}.phtml
    ├── Room/                        # Lab 8/9: Hotel room module
    │   ├── Module.php
    │   ├── config/module.config.php
    │   ├── src/Room/
    │   │   ├── Controller/RoomController.php
    │   │   ├── Entity/RoomEntity.php
    │   │   ├── Service/RoomService.php
    │   │   └── Factory/...
    │   └── view/room/room/
    │       ├── index.phtml
    │       ├── detail.phtml
    │       ├── search.phtml
    │       └── about.phtml
    ├── Auth/                        # User authentication module
    │   └── ...
    └── Payment/                     # Revolut payment module
        └── ...
```

---

## Step 1: Clone the Repository

```bash
git clone <repo-url>
cd ZF2_Demo
```

> **Windows note**: The `.gitattributes` file enforces LF line endings on checkout, so files like `.htaccess` and PHP scripts will have correct Unix line endings even on Windows.

---

## Step 2: Start the Docker Container

```bash
docker compose up -d
```

Verify the container is running:

```bash
docker compose ps
```

You should see two containers: `app` (PHP/Apache on port 8088) and `db` (MariaDB on port 3309), both with status `running`.

> **Windows note**: Use the same `docker compose` command (no hyphen). Docker Desktop includes Compose v2.
>
> **Linux note**: If you installed the legacy standalone `docker-compose` (v1), use `docker-compose` (with hyphen). Compose v2 (`docker compose`) is recommended.

---

## Step 3: Install Dependencies with Composer

Run `composer install` **inside** the container:

```bash
docker compose exec app composer install
```

This downloads Zend Framework 2, Doctrine ORM Module, and all dependencies into `vendor/` and generates `vendor/autoload.php`.

> **Note**: Composer is pre-installed in the Docker image. You do not need Composer on your host machine.

To verify the install succeeded:

```bash
docker compose exec app ls vendor/zendframework
```

---

## Step 4: Access the Application

Open your browser and go to:

```
http://localhost:8088/
```

You should see the **Hotel Demo** homepage with a navigation bar linking to all routes.

---

## Available Routes

| URL | Module | Action | Description |
|-----|--------|--------|-------------|
| `/` | Application | `index` | Hello World / route reference |
| `/room` | Room | `index` | List all rooms |
| `/room/detail/1` | Room | `detail` | Room detail + payment (`:id` segment route) |
| `/room/search` | Room | `search` | Search by type/price (query params) |
| `/room/create` | Room | `create` | Create new room (CSRF-protected form) |
| `/room/about` | Room | `about` | About page (standalone literal route) |
| `/auth/login` | Auth | `login` | User login form |
| `/payment/create` | Payment | `create` | Create Revolut payment order (POST) |

See [GETTING_STARTED.md](GETTING_STARTED.md) for the full route reference.

---

## Step 5: Understand the Key Files

### 5.1 `public/index.php` — Front Controller

Every HTTP request enters here:

```php
chdir(dirname(__DIR__));        // Set working dir to project root
require 'vendor/autoload.php';  // Load ZF2 + app classes via Composer

Zend\Mvc\Application::init(
    require 'config/application.config.php'
)->run();
```

`Zend\Mvc\Application::init()` reads the module list, bootstraps the ServiceManager, and dispatches the request.

---

### 5.2 `public/.htaccess` — Route All Requests to `index.php`

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} -s [OR]
RewriteCond %{REQUEST_FILENAME} -l [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^.*$ - [L]
RewriteRule ^.*$ index.php [L]
```

Apache's `mod_rewrite` sends every non-file request to `index.php`. `mod_rewrite` and `AllowOverride All` are already configured in the Docker image.

---

### 5.3 `config/application.config.php` — Module Registry

```php
return array(
    'modules' => array(
        'DoctrineModule',
        'DoctrineORMModule',
        'Application',
        'Room',
        'Auth',
        'Payment',
    ),
    'module_listener_options' => array(
        'module_paths' => array('./module', './vendor'),
        'config_glob_paths' => array('config/autoload/{,*.}{global,local}.php'),
    ),
);
```

---

### 5.4 `Module.php` — Module Bootstrap

Every ZF2 module has a `Module` class with two methods:

```php
public function getConfig()
{
    return include __DIR__ . '/config/module.config.php';
}

public function getAutoloaderConfig()
{
    return array(
        'Zend\Loader\StandardAutoloader' => array(
            'namespaces' => array(
                __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
            ),
        ),
    );
}
```

- `getConfig()` — merges module config into the global config.
- `getAutoloaderConfig()` — tells ZF2 where to find the module's PHP classes.

---

### 5.5 Routing — `module.config.php`

**Literal route** (exact URL match):

```php
'home' => array(
    'type'    => 'Zend\Mvc\Router\Http\Literal',
    'options' => array(
        'route'    => '/',
        'defaults' => array(
            'controller' => 'Application\Controller\Index',
            'action'     => 'index',
        ),
    ),
),
```

**Segment route** (URL with parameters):

```php
'detail' => array(
    'type'    => 'Zend\Mvc\Router\Http\Segment',
    'options' => array(
        'route'       => '/detail/:id',
        'constraints' => array('id' => '[0-9]+'),
        'defaults'    => array('action' => 'detail'),
    ),
),
```

**Parent route with children** — child routes extend the parent URL:

```php
'room' => array(
    'type'          => 'Zend\Mvc\Router\Http\Literal',
    'options'       => array('route' => '/room', 'defaults' => ...),
    'may_terminate' => true,
    'child_routes'  => array(
        'detail' => ...,   // matches /room/detail/:id
        'search' => ...,   // matches /room/search
    ),
),
```

---

### 5.6 Controllers

```php
class IndexController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel(['message' => 'Hello World']);
    }
}
```

- Extend `AbstractActionController`.
- Method name convention: `{action}Action`.
- Return a `ViewModel` with data for the view template.

Reading route and query parameters in controllers:

```php
// Route parameter: /room/detail/:id
$id = (int) $this->params()->fromRoute('id', 0);

// Query parameter: /room/search?type=Suite&min_price=100
$type     = $this->params()->fromQuery('type', '');
$minPrice = (int) $this->params()->fromQuery('min_price', 0);
```

---

### 5.7 Views

```php
<!-- index.phtml -->
<h1><?php echo $this->escapeHtml($message); ?></h1>
```

- Variables from `ViewModel` are available as `$varName`.
- `$this->escapeHtml()` sanitizes output (XSS protection).
- ZF2 wraps every action view with `layout/layout.phtml` and injects it as `$this->content`.

Generate URLs from route names in templates:

```php
<a href="<?php echo $this->url('room'); ?>">Rooms</a>
<a href="<?php echo $this->url('room/detail', ['id' => $room['id']]); ?>">Detail</a>
```

---

## Step 6: How ZF2 Processes a Request

```
Browser: GET /room/detail/2
    │
    ▼
.htaccess → rewrites to index.php
    │
    ▼
index.php → Zend\Mvc\Application::init()->run()
    │
    ▼
Router matches /room/detail/2 → Room\Controller\Room, action=detail, id=2
    │
    ▼
RoomController::detailAction() → reads $id=2, returns ViewModel(['room' => ...])
    │
    ▼
View layer renders detail.phtml → wraps with layout.phtml
    │
    ▼
Browser receives the HTML response
```

---

## Viewing Error Logs

PHP and Apache errors are logged to `data/php-errors.log` in the project root (mounted from the container):

**Linux / macOS:**
```bash
tail -f data/php-errors.log
```

**Windows (PowerShell):**
```powershell
Get-Content data\php-errors.log -Wait
```

Or via Docker:
```bash
docker compose logs -f zf2
```

---

## Stopping the Container

```bash
docker compose down
```

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| `vendor/` missing | Run `docker compose exec app composer install` |
| 404 Not Found | Ensure `.htaccess` is present; `mod_rewrite` is enabled in the Docker image |
| 500 / blank page | Check `data/php-errors.log` or run `docker compose logs app` |
| Class not found | Verify namespace and directory structure match exactly |
| Port 8088 already in use | Stop the conflicting process, or change the port in `docker-compose.yml` |
| Windows: container won't start | Ensure Docker Desktop is running and WSL2 integration is enabled |
| Container name unknown | Run `docker compose ps` to see the actual container name |

---

## Key ZF2 Concepts Summary

| Concept | Description |
|---------|-------------|
| **Module** | Self-contained unit with its own controllers, routes, and views |
| **ServiceManager** | Dependency injection container that creates and shares objects |
| **Router** | Maps URLs to controller + action pairs |
| **Literal route** | Matches an exact URL string |
| **Segment route** | Matches a URL with parameters (`:id`) |
| **Child routes** | Extend a parent route URL (e.g. `/room` → `/room/detail/:id`) |
| **Controller** | Handles a request and returns a `ViewModel` or `Response` |
| **ViewModel** | Carries data from the controller to the view template |
| **Layout** | Shared HTML wrapper injected around every action's view output |
| **view helper** | Methods callable as `$this->...()` inside `.phtml` files |
