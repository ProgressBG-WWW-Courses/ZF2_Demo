<?php
namespace Room\Service;

use Doctrine\ORM\EntityManager;
use Room\Entity\RoomEntity;

/**
 * RoomService — encapsulates all room-related business logic.
 *
 * Uses Doctrine's EntityManager to query and persist RoomEntity objects.
 *
 * Lecture 21: Doctrine ORM in ZF2
 * Lecture 22: Hydrators (exchangeArray / getArrayCopy)
 */
class RoomService
{
    /** @var EntityManager */
    private $em;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * Return all rooms as RoomEntity objects.
     *
     * @return RoomEntity[]
     */
    public function getAll()
    {
        return $this->em->getRepository('Room\Entity\RoomEntity')
                        ->findBy([], ['id' => 'ASC']);
    }

    /**
     * Return a single room by ID, or null if not found.
     *
     * @param  int $id
     * @return RoomEntity|null
     */
    public function getById($id)
    {
        return $this->em->find('Room\Entity\RoomEntity', (int) $id);
    }

    /**
     * Persist a new room to the database (Lecture 21/22).
     *
     * The entity arrives already hydrated via exchangeArray() in the controller.
     *
     * @param RoomEntity $room
     */
    public function save(RoomEntity $room)
    {
        $this->em->persist($room);
        $this->em->flush();
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
        $qb = $this->em->createQueryBuilder();
        $qb->select('r')
           ->from('Room\Entity\RoomEntity', 'r');

        if ($type !== '') {
            $qb->andWhere('r.type = :type')
               ->setParameter('type', $type);
        }
        if ($minPrice > 0) {
            $qb->andWhere('r.price >= :minPrice')
               ->setParameter('minPrice', (int) $minPrice);
        }

        $qb->orderBy('r.id', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
