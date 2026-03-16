<?php
namespace Room\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;


class RoomController extends AbstractActionController
{
    /**
     * Hardcoded room data — no database needed for this demo.
     * In a real app (like kittbg.com), this data comes from a database.
     */
    private function getRooms()
    {
        return array(
            1 => array(
                'id'    => 1,
                'number' => '101',
                'type'  => 'Single',
                'price' => 50,
                'description' => 'Cozy single room with garden view',
            ),
            2 => array(
                'id'    => 2,
                'number' => '102',
                'type'  => 'Double',
                'price' => 80,
                'description' => 'Spacious double room with balcony',
            ),
            3 => array(
                'id'    => 3,
                'number' => '201',
                'type'  => 'Suite',
                'price' => 150,
                'description' => 'Luxury suite with sea view and jacuzzi',
            ),
            4 => array(
                'id'    => 4,
                'number' => '202',
                'type'  => 'Double',
                'price' => 90,
                'description' => 'Double room with mountain view',
            ),
            5 => array(
                'id'    => 5,
                'number' => '301',
                'type'  => 'Suite',
                'price' => 200,
                'description' => 'Presidential suite with private terrace',
            ),
        );
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
        // get data
        $rooms = $this->getRooms();
       

        return new ViewModel(array(
            'rooms' => $rooms,
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
        $id = (int) $this->params()->fromRoute('id', 0);

        $rooms = $this->getRooms();

        // If room not found, show "not found" in the template
        if (!isset($rooms[$id])) {
            // Set HTTP 404 status code
            $this->getResponse()->setStatusCode(404);

            return new ViewModel(array(
                'room' => null,
                'id'   => $id,
            ));
        }

        return new ViewModel(array(
            'room' => $rooms[$id],
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

        $rooms = $this->getRooms();
        $results = array();

        foreach ($rooms as $room) {
            // Filter by type (if provided)
            if ($type !== '' && $room['type'] !== $type) {
                continue;
            }
            // Filter by minimum price (if provided)
            if ($minPrice > 0 && $room['price'] < $minPrice) {
                continue;
            }
            $results[] = $room;
        }

        return new ViewModel(array(
            'rooms'    => $results,
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
