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
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $em = $e->getApplication()->getEventManager();

        // Priority -100 means it runs LATE in the EVENT_ROUTE phase (after the router).
        $em->attach(MvcEvent::EVENT_ROUTE, array($this, 'checkAccess'), -100);

        // Log MVC dispatch/render exceptions to data/application.log.
        // ZF2 catches these internally and renders an error page without logging —
        // this listener writes the full exception chain so errors are traceable.
        $logFile = __DIR__ . '/../../data/application.log';
        $em->attach(MvcEvent::EVENT_DISPATCH_ERROR, function (MvcEvent $e) use ($logFile) {
            $ex = $e->getParam('exception');
            if (!$ex instanceof \Exception) {
                return;
            }
            $lines = [];
            do {
                $lines[] = sprintf(
                    '%s: %s in %s:%d',
                    get_class($ex),
                    $ex->getMessage(),
                    $ex->getFile(),
                    $ex->getLine()
                );
                $ex = $ex->getPrevious();
            } while ($ex);

            error_log(
                sprintf("[%s] %s\n", date('Y-m-d H:i:s'), implode("\nCaused by: ", $lines)),
                3,
                $logFile
            );
        });
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
                $url = $e->getRouter()->assemble(array(), array(
                    'name'  => 'auth/login',
                    'query' => array('msg' => 'login_required')
                ));
            } else {
                // Logged in but not allowed → show 403 Access Denied page
                $url = $e->getRouter()->assemble(array(), array('name' => 'error-403'));
            }

            $response = $e->getResponse();
            $response->getHeaders()->addHeaderLine('Location', $url);
            $response->setStatusCode(302);

            $e->stopPropagation(true);
            return $response;
        }
    }
}
