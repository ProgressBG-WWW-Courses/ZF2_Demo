# ZF2 Hello World - Step-by-Step Guide

## Overview

This guide walks you through creating a minimal "Hello World" application using Zend Framework 2 (ZF2).

> **PHP Version Note**: ZF2 requires PHP 5.6–7.4. Use the **php74** Docker container (port `8082`) provided in this project.

---

## Project Structure

```
Lab7/ZF2/
├── composer.json                                  # Composer dependencies
├── public/
│   ├── index.php                                  # Single entry point (front controller)
│   └── .htaccess                                  # Rewrite all requests to index.php
├── config/
│   └── application.config.php                     # Lists modules to load
└── module/
    └── Application/                               # The Application module
        ├── Module.php                             # Module bootstrap class
        ├── config/
        │   └── module.config.php                  # Routes, controllers, views
        ├── src/
        │   └── Application/
        │       └── Controller/
        │           └── IndexController.php        # Handles the "/" route
        └── view/
            ├── layout/
            │   └── layout.phtml                   # HTML wrapper for all pages
            ├── application/
            │   └── index/
            │       └── index.phtml                # "Hello World" view
            └── error/
                ├── 404.phtml
                └── index.phtml
```

---

## Step 1: Prerequisites

Start the Docker environment from the project root:

```bash
docker compose up -d php74
```

Verify that the container is running:

```bash
docker ps | grep php_labs_74
```

---

## Step 2: Install Dependencies with Composer

ZF2 is installed via Composer. Run the install command **inside** the php74 container:

```bash
docker exec -it php_labs_74 bash -c "cd /var/www/html/Lab7/ZF2 && composer install"
```

This downloads `zendframework/zendframework` (~2.5) into `Lab7/ZF2/vendor/`.

> Composer is already available in the container (installed in `Dockerfile.php74`).

---

## Step 3: Understand the Key Files

### 3.1 `composer.json` — Declare Dependencies

```json
{
    "require": {
        "zendframework/zendframework": "^2.5"
    }
}
```

Running `composer install` generates `vendor/autoload.php` which loads all ZF2 classes automatically.

---

### 3.2 `public/index.php` — Front Controller

Every HTTP request enters here:

```php
chdir(dirname(__DIR__));            // Set working dir to project root
require 'vendor/autoload.php';      // Load ZF2 + app classes

Zend\Mvc\Application::init(
    require 'config/application.config.php'
)->run();
```

`Zend\Mvc\Application::init()` reads the module list, bootstraps the ServiceManager, and dispatches the request.

---

### 3.3 `public/.htaccess` — Route All Requests to `index.php`

Apache's `mod_rewrite` sends every request to `index.php`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} -s [OR]
RewriteCond %{REQUEST_FILENAME} -l [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^.*$ - [NC,L]
RewriteRule ^.*$ index.php [NC,L]
```

`mod_rewrite` is already enabled in the php74 Docker image.

---

### 3.4 `config/application.config.php` — Module Registry

Tells ZF2 which modules to load:

```php
return array(
    'modules' => array('Application'),
    'module_listener_options' => array(
        'module_paths' => array('./module', './vendor'),
    ),
);
```

---

### 3.5 `module/Application/Module.php` — Module Bootstrap

Every ZF2 module has a `Module` class:

```php
namespace Application;

class Module
{
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
}
```

- `getConfig()` — merges module config into the global config.
- `getAutoloaderConfig()` — tells ZF2 where to find the module's PHP classes.

---

### 3.6 `module/Application/config/module.config.php` — Routes & Views

Three sections matter for Hello World:

**Controllers** — Register the controller in the service manager:
```php
'controllers' => array(
    'invokables' => array(
        'Application\Controller\Index' => 'Application\Controller\IndexController',
    ),
),
```

**Router** — Map the URL `/` to the controller and action:
```php
'router' => array(
    'routes' => array(
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
    ),
),
```

**View Manager** — Map template names to `.phtml` files:
```php
'view_manager' => array(
    'template_map' => array(
        'layout/layout'           => __DIR__ . '/../view/layout/layout.phtml',
        'application/index/index' => __DIR__ . '/../view/application/index/index.phtml',
    ),
),
```

---

### 3.7 `IndexController.php` — The Action

```php
namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel(array(
            'message' => 'Hello, World!',
        ));
    }
}
```

- Extends `AbstractActionController` to get routing and request helpers.
- Returns a `ViewModel` with data variables passed to the view template.
- ZF2 convention: method name = `{action}Action`, e.g., `indexAction` handles `action=index`.

---

### 3.8 `view/application/index/index.phtml` — The View

```php
<h1><?php echo $this->escapeHtml($message); ?></h1>
<p>Welcome to your first <strong>Zend Framework 2</strong> application.</p>
```

- `$message` is the variable passed from `indexAction()` via `ViewModel`.
- `$this->escapeHtml()` is a view helper that sanitizes output (XSS protection).
- ZF2 automatically wraps this with `layout/layout.phtml` and injects it as `$this->content`.

---

### 3.9 `view/layout/layout.phtml` — The Layout

```html
<!DOCTYPE html>
<html>
<head><title>ZF2 Hello World</title></head>
<body>
    <?php echo $this->content; ?>
</body>
</html>
```

`$this->content` is the rendered output of the action's view template, injected by ZF2's view layer.

---

## Step 4: How ZF2 Processes a Request

```
Browser: GET /Lab7/ZF2/public/
    │
    ▼
.htaccess → rewrites to index.php
    │
    ▼
index.php → Zend\Mvc\Application::init()->run()
    │
    ▼
Router matches "/" → Application\Controller\Index, action=index
    │
    ▼
IndexController::indexAction() → returns ViewModel(['message' => 'Hello, World!'])
    │
    ▼
View layer renders index.phtml → wraps with layout.phtml
    │
    ▼
Browser receives the HTML response
```

---

## Step 5: Access the Application

Open your browser and navigate to:

```
http://localhost:8082/Lab7/ZF2/public/
```

You should see:

```
Hello, World!
Welcome to your first Zend Framework 2 application.
```

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| `vendor/` folder missing | Run `composer install` inside the container |
| 404 Not Found | Ensure `mod_rewrite` is enabled and `.htaccess` is present |
| 500 / blank page | Check Apache error logs: `docker logs php_labs_74` |
| Class not found | Verify namespace and directory structure match exactly |

---

## Key ZF2 Concepts Summary

| Concept | Description |
|---------|-------------|
| **Module** | A self-contained unit of functionality with its own controllers, routes, and views |
| **ServiceManager** | Dependency injection container that creates and shares objects |
| **Router** | Maps URLs to controller + action pairs |
| **Controller** | Handles a request and returns a `ViewModel` or `Response` |
| **ViewModel** | Carries data from the controller to the view template |
| **Layout** | A shared HTML wrapper injected around every action's view output |
