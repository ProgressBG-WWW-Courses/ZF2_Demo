<?php
namespace Application;

use Zend\Mvc\MvcEvent;

class Module
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }

    /**
     * Attach the ACL check to every request.
     *
     * This runs BEFORE any controller action — it's a security gate.
     * Priority 100 means it runs early in the EVENT_ROUTE phase.
     */
    public function onBootstrap(MvcEvent $e)
    {
        $e->getApplication()->getEventManager()->attach(
            MvcEvent::EVENT_ROUTE,
            array($this, 'checkAccess'),
            100
        );
    }

    /**
     * Two-layer security gate:
     *  1. Are you logged in? (authentication)
     *  2. Do you have permission? (authorization via ACL)
     */
    public function checkAccess(MvcEvent $e)
    {
        $routeMatch = $e->getRouteMatch();
        if (!$routeMatch) {
            return;  // No route matched — let ZF2 handle the 404
        }

        $routeName = $routeMatch->getMatchedRouteName();

        // What role does the current user have?
        // If they're not logged in, they're a "guest"
        $userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'guest';

        // Get the AclService from the Service Manager
        $sm         = $e->getApplication()->getServiceManager();
        $aclService = $sm->get('AclService');

        // Check: is this role allowed to access this route?
        if (!$aclService->isAllowed($userRole, $routeName)) {

            if ($userRole === 'guest') {
                // Not logged in → redirect to login page
                $url = $e->getRouter()->assemble(array(), array('name' => 'auth/login'));
            } else {
                // Logged in but not allowed → show 403 Access Denied page
                $url = $e->getRouter()->assemble(array(), array('name' => 'error/403'));
            }

            $response = $e->getResponse();
            $response->getHeaders()->addHeaderLine('Location', $url);
            $response->setStatusCode(302);

            $e->stopPropagation(true);
            return $response;
        }
    }
}
