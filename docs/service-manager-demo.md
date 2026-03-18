# ZF2 Service Manager Demo

## What Changed and Why

The `Room` module originally had its room data hardcoded inside `RoomController::getRooms()`.
This demo extracts that logic into a dedicated **`RoomService`** and wires it into the controller
using ZF2's **ServiceManager** and the **Dependency Injection** (DI) pattern.

---

## New Files

```
module/Room/src/Room/
├── Service/
│   └── RoomService.php          ← business logic lives here now
└── Factory/
    └── RoomControllerFactory.php  ← creates RoomController with its dependencies
```

---

## Two Patterns: ServiceLocator vs Dependency Injection

### ServiceLocator pattern (what we are NOT doing)

The controller reaches into the container and pulls what it needs:

```php
public function indexAction()
{
    $service = $this->getServiceLocator()->get('RoomService'); // controller knows the container
    return new ViewModel(['rooms' => $service->getAll()]);
}
```

**Problem:** the dependency is hidden inside the method body — you cannot tell from the class
signature what the controller needs. Unit testing requires a real or mocked ServiceLocator.

---

### Dependency Injection pattern (what we ARE doing)

Dependencies are declared in the constructor and injected from the outside:

```php
class RoomController extends AbstractActionController
{
    private $roomService;

    public function __construct(RoomService $roomService)   // ← explicit dependency
    {
        $this->roomService = $roomService;
    }
}
```

The controller no longer knows anything about the container. It just uses `$this->roomService`.

---

## How ZF2 Wires It Together

### 1. Register `RoomService` as an invokable

`module/Room/config/module.config.php`:

```php
'service_manager' => array(
    'invokables' => array(
        'RoomService' => 'Room\Service\RoomService',
    ),
),
```

`invokables` means: "instantiate this class directly (no constructor arguments needed)".
ZF2 will call `new RoomService()` on first request and cache the instance.

---

### 2. Register `RoomController` with a factory (instead of invokable)

```php
'controllers' => array(
    'factories' => array(
        'Room\Controller\Room' => 'Room\Factory\RoomControllerFactory',
    ),
),
```

Previously this used `invokables` — ZF2 would call `new RoomController()` with no arguments.
Now that the constructor requires a `RoomService`, we need a factory to do the construction.

---

### 3. The factory fetches the service and injects it

`module/Room/src/Room/Factory/RoomControllerFactory.php`:

```php
class RoomControllerFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $controllerManager)
    {
        // The ControllerManager is a child locator — get the parent (app-level) ServiceManager
        $serviceManager = $controllerManager->getServiceLocator();

        $roomService = $serviceManager->get('RoomService');   // lazy-instantiates RoomService

        return new RoomController($roomService);              // constructor injection
    }
}
```

**Key detail:** ZF2 passes the **ControllerManager** (a child service locator) to `createService()`,
not the top-level ServiceManager. Call `getServiceLocator()` to reach the parent where
`RoomService` is registered.

---

## Request Flow After the Change

```
GET /room
    │
    ▼
Router → Room\Controller\Room, action=index
    │
    ▼
ControllerManager needs 'Room\Controller\Room'
    │  (not in invokables anymore — uses factory)
    ▼
RoomControllerFactory::createService($controllerManager)
    ├── $controllerManager->getServiceLocator()  → parent ServiceManager
    └── $serviceManager->get('RoomService')      → new RoomService()  (invokable)
    │
    ▼
new RoomController($roomService)
    │
    ▼
RoomController::indexAction()
    └── $this->roomService->getAll()  → returns room array
    │
    ▼
ViewModel(['rooms' => [...]])  → rendered by index.phtml
```

---

## RoomService API

| Method | Signature | Returns |
|--------|-----------|---------|
| `getAll` | `getAll()` | All rooms as an indexed array |
| `getById` | `getById(int $id)` | Single room array, or `null` if not found |
| `search` | `search(string $type = '', int $minPrice = 0)` | Filtered room array |

---

## Verification URLs

| URL | Expected result |
|-----|-----------------|
| `http://localhost:8088/room` | Room list — all 5 rooms |
| `http://localhost:8088/room/detail/1` | Detail for room 101 |
| `http://localhost:8088/room/detail/99` | 404 — room not found |
| `http://localhost:8088/room/search?type=Suite&min_price=100` | 2 suite rooms (≥ 100) |
| `http://localhost:8088/room/about` | About page (unchanged) |

---

## Key Concepts Summary

| Concept | Description |
|---------|-------------|
| **ServiceManager** | ZF2's dependency injection container — creates and caches objects |
| **invokable** | A service with no constructor dependencies — `new ClassName()` |
| **factory** | A class (or callable) that constructs a service with dependencies |
| **FactoryInterface** | ZF2 interface with one method: `createService(ServiceLocatorInterface $sm)` |
| **ControllerManager** | A child ServiceLocator; call `getServiceLocator()` to reach the parent |
| **Dependency Injection** | Pass dependencies in via constructor — explicit, testable, decoupled |
| **ServiceLocator pattern** | Pull dependencies from a container inside the class — hidden, harder to test |
