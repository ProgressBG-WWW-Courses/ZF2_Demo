<?php
namespace Room\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Room\Service\RoomService;

class RoomApiController extends AbstractActionController
{
    /** @var RoomService */
    private $roomService;

    public function __construct(RoomService $roomService)
    {
        $this->roomService = $roomService;
    }

    // GET /api/rooms — list all rooms
    public function indexAction()
    {
        try {
            $rooms = array_map(
                function ($room) { return $room->getArrayCopy(); },
                $this->roomService->getAll()
            );

            return new JsonModel([
                'success' => true,
                'count'   => count($rooms),
                'rooms'   => $rooms,
            ]);
        } catch (\Exception $e) {
            $this->getResponse()->setStatusCode(500);
            return new JsonModel([
                'success' => false,
                'error'   => 'Failed to load rooms',
            ]);
        }
    }

    // GET /api/rooms/:id — get details for a specific room
    public function getAction()
    {
        $id = (int) $this->params()->fromRoute('id', 0);

        if ($id === 0) {
            $this->getResponse()->setStatusCode(400);
            return new JsonModel([
                'success' => false,
                'error'   => 'Invalid room ID',
            ]);
        }

        try {
            $room = $this->roomService->getById($id);

            if ($room === null) {
                throw new \RuntimeException("Room #$id not found");
            }

            return new JsonModel([
                'success' => true,
                'room'    => $room->getArrayCopy(),
            ]);
        } catch (\RuntimeException $e) {
            $this->getResponse()->setStatusCode(404);
            return new JsonModel([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
