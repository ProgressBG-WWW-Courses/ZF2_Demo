<?php
namespace Room\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Room\Service\RoomService;
use Room\Form\RoomSearchForm;
use Room\Form\RoomForm;
use Room\InputFilter\RoomSearchFilter;
use Room\InputFilter\RoomFilter;
use Room\Entity\RoomEntity;
use Payment\Service\PaymentService;

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

    /** @var PaymentService */
    private $paymentService;

    /**
     * Constructor — receives dependencies via Dependency Injection.
     * Called by RoomControllerFactory, not directly by user code.
     *
     * @param RoomService    $roomService
     * @param PaymentService $paymentService
     */
    public function __construct(RoomService $roomService, PaymentService $paymentService)
    {
        $this->roomService    = $roomService;
        $this->paymentService = $paymentService;
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

        // Payment state is updated by the webhook handler.
        // The frontend poller (/payment/status/:order_id) handles any delay,
        // falling back to the Revolut API after 30 seconds if needed.

        // Fetch latest payment status for this room
        $payment = $this->paymentService->getLatestPaymentForRoom($id);

        return new ViewModel(array(
            'room'    => $room,
            'id'      => $id,
            'payment' => $payment,
        ));
    }

    /**
     * GET /room/search  — shows the empty search form
     * POST /room/search — validates submitted data, returns filtered results
     *
     * Demonstrates (Lecture 20):
     * - ZF2 Form class (defines form fields)
     * - InputFilter class (defines validation rules)
     * - isPost() / setData() / isValid() / getData() flow
     * - Form passed to view for rendering with form helpers
     */
    public function searchAction()
    {
        // Create the form and attach its validation rules
        $form = new RoomSearchForm();
        $form->setInputFilter(new RoomSearchFilter());

        $rooms    = array();
        $searched = false;

        if ($this->getRequest()->isPost()) {
            // Step 1: hand the submitted data to the form
            $form->setData($this->getRequest()->getPost());

            // Step 2: run filters (clean the data) then validators (check it)
            if ($form->isValid()) {
                // Step 3: getData() returns the FILTERED, CLEAN values — not raw $_POST
                $data  = $form->getData();
                $rooms = $this->roomService->search($data['type'], (int) $data['min_price']);

                // Lecture 22 — Hydration demo:
                // In a CREATE/EDIT form the field names would match the entity properties
                // exactly. After isValid() you'd do:
                //
                //   $room = new RoomEntity();
                //   $room->exchangeArray($form->getData());  // array → entity
                //   $em->persist($room);
                //   $em->flush();
                //
                // Search forms often have different field names (e.g. "min_price" vs
                // the entity's "price"), so direct $data access is clearer here.
            }
            $searched = true;
        }

        // Always pass the form to the view.
        // If the form had errors, it carries the error messages — formRow() shows them.
        return new ViewModel(array(
            'form'     => $form,
            'rooms'    => $rooms,
            'searched' => $searched,
        ));
    }

    /**
     * GET  /room/create — show the empty create form
     * POST /room/create — validate, then save or re-display with errors
     *
     * Demonstrates (Lecture 20):
     * - CSRF token: RoomForm includes Zend\Form\Element\Csrf
     *   isValid() automatically rejects the submission if the token is wrong
     * - Same isPost / setData / isValid / getData flow as searchAction
     */
    public function createAction()
    {
        $form = new RoomForm();
        $form->setInputFilter(new RoomFilter());

        if ($this->getRequest()->isPost()) {
            $form->setData($this->getRequest()->getPost());

            if ($form->isValid()) {
                $data = $form->getData();

                // Hydrate a new entity from the clean form data (Lecture 22)
                $room = new RoomEntity();
                $room->exchangeArray($data);

                // Persist via the service (Lecture 21/22)
                $this->roomService->save($room);

                // Redirect to the room list — prevents double-submit on refresh
                return $this->redirect()->toRoute('room');
            }
        }

        return new ViewModel(array('form' => $form));
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
