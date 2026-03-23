<?php
// module/Auth/src/Auth/Service/UserService.php
namespace Auth\Service;

/**
 * UserService — hardcoded user data for the demo.
 *
 * In a real application this would query the database (via Doctrine or Zend\Db).
 * For the demo we use the same approach as RoomService — hardcoded data.
 */
class UserService
{
    /** @var array[] */
    private $users = array();

    public function __construct()
    {
        // Hardcoded users — passwords generated with password_hash()
        // admin123 → bcrypt hash | staff123 → bcrypt hash
        $this->users = array(
            array(
                'id'            => 1,
                'username'      => 'admin',
                'password_hash' => '$2y$10$tGYnEZ8oH0tF.k.hctBz8e20J2.kgZgog/JlD4YXYSAfGteM7imTa',
                'role'          => 'admin',
            ),
            array(
                'id'            => 2,
                'username'      => 'staff',
                'password_hash' => '$2y$10$q4bou2CWVN9AvOypoRdyX.psZKgLJ5kX4WM6geqkLPZsw6Rb7W/8q',
                'role'          => 'staff',
            ),
        );
    }

    /**
     * Find a user by username.
     *
     * @param  string $username
     * @return array|null
     */
    public function findByUsername($username)
    {
        foreach ($this->users as $user) {
            if ($user['username'] === $username) {
                return $user;
            }
        }
        return null;
    }
}
