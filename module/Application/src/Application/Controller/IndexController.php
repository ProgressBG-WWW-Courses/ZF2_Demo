<?php
namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel([
            'message' => 'Hello World',
            'name' => 'Zend Framework 2',
        ]);
    }

    public function accessDeniedAction()
    {
        $this->getResponse()->setStatusCode(403);
        return new ViewModel();
    }
}
