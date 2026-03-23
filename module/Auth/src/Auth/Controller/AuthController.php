<?php
// module/Auth/src/Auth/Controller/AuthController.php
namespace Auth\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Auth\Form\LoginForm;
use Auth\InputFilter\LoginInputFilter;
use Auth\Service\UserService;

class AuthController extends AbstractActionController
{
    /** @var UserService */
    private $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function loginAction()
    {
        // If the user is already logged in, send them to the home page.
        if (isset($_SESSION['user_id'])) {
            return $this->redirect()->toRoute('home');
        }

        $form    = new LoginForm();
        $message = '';

        // Check: did the user submit the form? (POST request)
        if ($this->getRequest()->isPost()) {

            // Attach the validation rules
            $form->setInputFilter(new LoginInputFilter());

            // Feed the submitted data into the form
            $form->setData($this->getRequest()->getPost());

            // Validate: are both fields filled in? Is the CSRF token valid?
            if ($form->isValid()) {
                $data     = $form->getData();
                $username = $data['username'];
                $password = $data['password'];

                // Step 1: Look up the user by username
                $user = $this->userService->findByUsername($username);

                // Step 2: Check the password
                if ($user && password_verify($password, $user['password_hash'])) {
                    // SUCCESS! The password matches.

                    // Regenerate the session ID to prevent session fixation attacks
                    session_regenerate_id(true);

                    // Store the user's info in the session
                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['username']  = $user['username'];
                    $_SESSION['user_role'] = $user['role'];

                    // Send them to the home page
                    return $this->redirect()->toRoute('home');
                }

                // If we got here, the username/password was wrong
                $message = 'Invalid username or password.';
            }
        }

        // Show the login page with the form
        return new ViewModel(array(
            'form'    => $form,
            'message' => $message,
        ));
    }

    public function logoutAction()
    {
        // Destroy the session completely — erase all session data
        $_SESSION = array();
        session_destroy();

        // Send them back to the login page
        return $this->redirect()->toRoute('auth/login');
    }
}
