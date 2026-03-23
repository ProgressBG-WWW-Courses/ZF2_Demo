<?php
namespace Auth\Service;

use Doctrine\ORM\EntityManager;
use Auth\Entity\UserEntity;

/**
 * UserService — queries the users table via Doctrine EntityManager.
 */
class UserService
{
    /** @var EntityManager */
    private $em;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * Find a user by username.
     *
     * @param  string $username
     * @return UserEntity|null
     */
    public function findByUsername($username)
    {
        return $this->em->getRepository('Auth\Entity\UserEntity')
                        ->findOneBy(['username' => $username]);
    }
}
