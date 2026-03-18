<?php
namespace Room\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Room\Service\RoomService;

/**
 * RoomController — handles all /room/* routes.
 *
 * ── Two patterns for accessing services in ZF2 ──────────────────────────────
 *
 * 1. ServiceLocator pattern (anti-pattern for testability):
 *       $service = $this->getServiceLocator()->get('RoomService');
 *    The controller reaches out and pulls what it needs.
 *    Simple, but hides dependencies and makes unit-testing harder.
 *
 * 2. Dependency Injection pattern (used here):
 *       The service is passed in via the constructor by RoomControllerFactory.
 *    Dependencies are explicit, the controller is easier to test in isolation.
 *
 * ZF2 wires these together in module.config.php:
 *   - RoomService      → registered as an invokable in service_manager
 *   - RoomController   → registered with a factory in controllers
 * ────────────────────────────────────────────────────────────────────────────
 */
class RoomController extends AbstractActionController
{
    /** @var RoomService */
    private $roomService;

    /**
     * Constructor — receives dependencies via Dependency Injection.
     * Called by RoomControllerFactory, not directly by user code.
     *
     * @param RoomService $roomService
     */
    public function __construct(RoomService $roomService)
    {
        $this->roomService = $roomService;
    }

    /**
     * GET /room
     *
     * Lists all rooms. Demonstrates:
     * - ViewModel (passing data to the template)
     * - url()->fromRoute() (generating URLs in PHP)
     */
    public function indexAction()
    {
        return new ViewModel(array(
            'rooms' => $this->roomService->getAll(),
        ));
    }

    /**
     * GET /room/detail/:id
     *
     * Shows a single room. Demonstrates:
     * - params()->fromRoute('id') — reading a value from the URL
     * - redirect()->toRoute() — sending the user to another page
     * - Setting a 404 response status
     */
    public function detailAction()
    {
        // Read the :id parameter from the URL
        $id   = (int) $this->params()->fromRoute('id', 0);
        $room = $this->roomService->getById($id);

        // If room not found, show "not found" in the template
        if ($room === null) {
            // Set HTTP 404 status code
            $this->getResponse()->setStatusCode(404);
        }

        return new ViewModel(array(
            'room' => $room,
            'id'   => $id,
        ));
    }

    /**
     * GET /room/search?type=Suite&min_price=100
     *
     * Filters rooms by query parameters. Demonstrates:
     * - params()->fromQuery() — reading URL query parameters
     * - Default values for optional parameters
     */
    public function searchAction()
    {
        // Read query parameters with defaults
        $type     = $this->params()->fromQuery('type', '');
        $minPrice = (int) $this->params()->fromQuery('min_price', 0);

        return new ViewModel(array(
            'rooms'    => $this->roomService->search($type, $minPrice),
            'type'     => $type,
            'minPrice' => $minPrice,
        ));
    }

    /**
     * GET /room/about
     *
     * A simple static page. Demonstrates:
     * - The simplest possible action
     * - Standalone Literal route (not a child route)
     */
    public function aboutAction()
    {
        return new ViewModel();
    }
}
