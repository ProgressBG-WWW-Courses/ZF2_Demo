<?php
namespace Room\Service;

/**
 * RoomService — encapsulates all room-related business logic.
 *
 * Registered in ServiceManager as an invokable (no constructor dependencies).
 * Injected into RoomController via RoomControllerFactory (Dependency Injection).
 */
class RoomService
{
    /**
     * Hardcoded room data — moved here from the controller.
     * In a real app this would come from a database via a Repository / TableGateway.
     */
    private $rooms = array(
        1 => array(
            'id'          => 1,
            'number'      => '101',
            'type'        => 'Single',
            'price'       => 50,
            'description' => 'Cozy single room with garden view',
        ),
        2 => array(
            'id'          => 2,
            'number'      => '102',
            'type'        => 'Double',
            'price'       => 80,
            'description' => 'Spacious double room with balcony',
        ),
        3 => array(
            'id'          => 3,
            'number'      => '201',
            'type'        => 'Suite',
            'price'       => 150,
            'description' => 'Luxury suite with sea view and jacuzzi',
        ),
        4 => array(
            'id'          => 4,
            'number'      => '202',
            'type'        => 'Double',
            'price'       => 90,
            'description' => 'Double room with mountain view',
        ),
        5 => array(
            'id'          => 5,
            'number'      => '301',
            'type'        => 'Suite',
            'price'       => 200,
            'description' => 'Presidential suite with private terrace',
        ),
    );

    /**
     * Return all rooms.
     *
     * @return array
     */
    public function getAll()
    {
        return $this->rooms;
    }

    /**
     * Return a single room by its integer key, or null if not found.
     *
     * @param  int $id
     * @return array|null
     */
    public function getById($id)
    {
        return isset($this->rooms[$id]) ? $this->rooms[$id] : null;
    }

    /**
     * Return rooms matching the given filters.
     *
     * @param  string $type      Room type ('Single', 'Double', 'Suite', …). Empty = any.
     * @param  int    $minPrice  Minimum price. 0 = no lower bound.
     * @return array
     */
    public function search($type = '', $minPrice = 0)
    {
        $results = array();

        foreach ($this->rooms as $room) {
            if ($type !== '' && $room['type'] !== $type) {
                continue;
            }
            if ($minPrice > 0 && $room['price'] < $minPrice) {
                continue;
            }
            $results[] = $room;
        }

        return $results;
    }
}
