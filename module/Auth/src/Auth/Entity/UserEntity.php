<?php
namespace Auth\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * UserEntity — represents a user account.
 *
 * Mapped to the "users" database table via Doctrine ORM annotations.
 *
 * @ORM\Entity
 * @ORM\Table(name="users", indexes={
 *     @ORM\Index(name="idx_username", columns={"username"})
 * })
 */
class UserEntity
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    /** @ORM\Column(type="string", length=50, unique=true) */
    protected $username;

    /** @ORM\Column(name="password_hash", type="string", length=255) */
    protected $passwordHash;

    /** @ORM\Column(type="string", length=20) */
    protected $role;

    // ── Hydration ─────────────────────────────────────────────────────────────

    public function exchangeArray(array $data)
    {
        $this->id           = isset($data['id'])            ? (int) $data['id']            : null;
        $this->username     = isset($data['username'])      ?       $data['username']      : null;
        $this->passwordHash = isset($data['password_hash']) ?       $data['password_hash'] : null;
        $this->role         = isset($data['role'])          ?       $data['role']          : null;
    }

    public function getArrayCopy()
    {
        return array(
            'id'            => $this->id,
            'username'      => $this->username,
            'password_hash' => $this->passwordHash,
            'role'          => $this->role,
        );
    }

    // ── Getters ───────────────────────────────────────────────────────────────

    public function getId()           { return $this->id; }
    public function getUsername()     { return $this->username; }
    public function getPasswordHash() { return $this->passwordHash; }
    public function getRole()         { return $this->role; }

    // ── Setters ───────────────────────────────────────────────────────────────

    public function setUsername($v)     { $this->username     = $v; }
    public function setPasswordHash($v) { $this->passwordHash = $v; }
    public function setRole($v)         { $this->role         = $v; }
}
