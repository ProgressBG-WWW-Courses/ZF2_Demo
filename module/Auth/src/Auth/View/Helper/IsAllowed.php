<?php
namespace Auth\View\Helper;

use Zend\View\Helper\AbstractHelper;
use Auth\Service\AclService;

class IsAllowed extends AbstractHelper
{
    /** @var AclService */
    private $aclService;

    /** @var string|null */
    private $userRole;

    public function __construct(AclService $aclService, $userRole)
    {
        $this->aclService = $aclService;
        $this->userRole   = $userRole;
    }

    /**
     * Check if current user is allowed to access $resource.
     *
     * @param  string $resource
     * @return bool
     */
    public function __invoke($resource)
    {
        $role = $this->userRole ?: 'guest';
        return $this->aclService->isAllowed($role, $resource);
    }
}
