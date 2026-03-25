<?php
namespace Auth\Factory;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Auth\View\Helper\IsAllowed;

class IsAllowedFactory implements FactoryInterface
{
    /**
     * Create the IsAllowed view helper.
     *
     * In ZF2, the helper manager is a child of the main Service Manager.
     */
    public function createService(ServiceLocatorInterface $viewHelperManager)
    {
        $sm = $viewHelperManager->getServiceLocator();

        $aclService = $sm->get('AclService');

        // Access the current user's role from the session.
        // We assume session_start() has already run in Module::onBootstrap().
        $userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'guest';

        return new IsAllowed($aclService, $userRole);
    }
}
