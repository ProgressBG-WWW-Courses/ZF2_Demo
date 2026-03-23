<?php
// module/Auth/src/Auth/Form/LoginForm.php
namespace Auth\Form;

use Zend\Form\Form;

class LoginForm extends Form
{
    public function __construct()
    {
        parent::__construct('login');

        // The username field — a regular text input
        $this->add(array(
            'type'    => 'Zend\Form\Element\Text',
            'name'    => 'username',
            'options' => array('label' => 'Username'),
        ));

        // The password field — shows dots instead of characters
        $this->add(array(
            'type'    => 'Zend\Form\Element\Password',
            'name'    => 'password',
            'options' => array('label' => 'Password'),
        ));

        // CSRF protection — prevents attackers from submitting fake login forms
        $this->add(array(
            'type'    => 'Zend\Form\Element\Csrf',
            'name'    => 'csrf',
            'options' => array('csrf_options' => array('timeout' => 600)),
        ));

        // The submit button
        $this->add(array(
            'name'       => 'submit',
            'attributes' => array('type' => 'submit', 'value' => 'Log In'),
        ));
    }
}
