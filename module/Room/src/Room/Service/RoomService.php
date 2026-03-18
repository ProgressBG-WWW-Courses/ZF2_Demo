<?php
namespace Room\Service;

use Room\Entity\RoomEntity;

/**
 * RoomService — encapsulates all room-related business logic.
 *
 * Internally stores RoomEntity objects (not plain arrays).
 * exchangeArray() populates each entity from the source data — this is the
 * "hydrate" direction described in Lecture 22.
 *
 * In a real application the EntityManager would be injected here via the
 * constructor (see RoomServiceFactory) and the hardcoded $data array would
 * be replaced by Doctrine repository calls.
 *
 * Lecture 22: Hydrators — exchangeArray / getArrayCopy
 */
class RoomService
{
    /** @var RoomEntity[] */
    private $rooms = array();

    public function __construct()
    {
        // Hardcoded source data — would come from the database in a real app.
        $data = array(
            array('id' => 1, 'number' => '101', 'type' => 'Single', 'price' => 50,  'description' => 'Cozy single room with garden view'),
            array('id' => 2, 'number' => '102', 'type' => 'Double', 'price' => 80,  'description' => 'Spacious double room with balcony'),
            array('id' => 3, 'number' => '201', 'type' => 'Suite',  'price' => 150, 'description' => 'Luxury suite with sea view and jacuzzi'),
            array('id' => 4, 'number' => '202', 'type' => 'Double', 'price' => 90,  'description' => 'Double room with mountain view'),
            array('id' => 5, 'number' => '301', 'type' => 'Suite',  'price' => 200, 'description' => 'Presidential suite with private terrace'),
        );

        // Hydration: fill each RoomEntity from the source array.
        // This is exactly the exchangeArray() pattern from Lecture 22.
        foreach ($data as $row) {
            $room = new RoomEntity();
            $room->exchangeArray($row);          // array → entity object
            $this->rooms[$row['id']] = $room;
        }
    }

    /**
     * Return all rooms as RoomEntity objects.
     *
     * @return RoomEntity[]
     */
    public function getAll()
    {
        return $this->rooms;
    }

    /**
     * Return a single room by ID, or null if not found.
     *
     * @param  int $id
     * @return RoomEntity|null
     */
    public function getById($id)
    {
        return isset($this->rooms[$id]) ? $this->rooms[$id] : null;
    }

    /**
     * Persist a new room (Lecture 21/22).
     *
     * In a real application:
     *   $this->em->persist($room);
     *   $this->em->flush();
     *
     * Demo: adds to the in-memory array (lost after this request ends).
     * The entity arrives already hydrated via exchangeArray() in the controller.
     *
     * @param RoomEntity $room
     */
    public function save(RoomEntity $room)
    {
        $newId = max(array_keys($this->rooms)) + 1;

        // Use getArrayCopy() + exchangeArray() to assign the generated ID
        $data       = $room->getArrayCopy();
        $data['id'] = $newId;
        $room->exchangeArray($data);

        $this->rooms[$newId] = $room;
    }

    /**
     * Return rooms matching the given filters as RoomEntity objects.
     *
     * @param  string $type      Room type ('Single', 'Double', 'Suite'). Empty = any.
     * @param  int    $minPrice  Minimum price. 0 = no lower bound.
     * @return RoomEntity[]
     */
    public function search($type = '', $minPrice = 0)
    {
        $results = array();

        foreach ($this->rooms as $room) {
            if ($type !== '' && $room->getType() !== $type) {
                continue;
            }
            if ($minPrice > 0 && $room->getPrice() < $minPrice) {
                continue;
            }
            $results[] = $room;
        }

        return $results;
    }
}
