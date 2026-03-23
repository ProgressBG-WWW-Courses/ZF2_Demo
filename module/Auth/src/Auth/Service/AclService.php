<?php
// module/Auth/src/Auth/Service/AclService.php
namespace Auth\Service;

use Zend\Permissions\Acl\Acl;

/**
 * AclService — manages roles, resources, and permission rules.
 *
 * Role hierarchy:  guest → staff → manager → admin
 * Each level inherits all permissions from the level below it.
 */
class AclService
{
    /** @var Acl */
    private $acl;

    public function __construct()
    {
        $this->acl = new Acl();
        $this->buildRoles();
        $this->buildResources();
        $this->buildRules();
    }

    private function buildRoles()
    {
        // guest = not logged in (or minimal access)
        $this->acl->addRole('guest');

        // staff inherits everything guest can do, PLUS their own permissions
        $this->acl->addRole('staff', 'guest');

        // manager inherits everything staff can do
        $this->acl->addRole('manager', 'staff');

        // admin inherits everything manager can do
        $this->acl->addRole('admin', 'manager');
    }

    private function buildResources()
    {
        // Each resource corresponds to a route name in our application
        $resources = array(
            'home', 'error/403',
            'auth', 'auth/login', 'auth/logout',
            'room', 'room/detail', 'room/search', 'room/create', 'room-about',
        );

        foreach ($resources as $resource) {
            $this->acl->addResource($resource);
        }
    }

    private function buildRules()
    {
        // Public access — everyone can reach these pages
        $this->acl->allow('guest', array('home', 'error/403', 'auth', 'auth/login', 'auth/logout'));
        $this->acl->allow('guest', array('room', 'room/detail', 'room/search', 'room-about'));

        // Staff can also create rooms
        $this->acl->allow('staff', 'room/create');

        // Admin gets everything (null = all resources)
        $this->acl->allow('admin');
    }

    /**
     * Check if a given role is allowed to access a resource.
     *
     * @param  string $role     The user's role (e.g. 'staff')
     * @param  string $resource The route name (e.g. 'room/create')
     * @return bool
     */
    public function isAllowed($role, $resource)
    {
        // If we didn't register this resource, deny by default.
        // "Secure by default" — anything not explicitly allowed is forbidden.
        if (!$this->acl->hasResource($resource)) {
            return false;
        }

        return $this->acl->isAllowed($role, $resource);
    }
}
