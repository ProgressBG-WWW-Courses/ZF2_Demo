<?php
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
        
        // Check for manual redirect message (Lecture 24 UX)
        $msg     = $this->params()->fromQuery('msg');
        $message = ($msg === 'login_required') ? 'Please log in to access this page.' : '';

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

                // Step 1: Look up the user by username (returns UserEntity or null)
                $user = $this->userService->findByUsername($username);

                // Step 2: Check the password
                if ($user && password_verify($password, $user->getPasswordHash())) {
                    // SUCCESS! The password matches.

                    // Start a fresh session and regenerate the ID to prevent fixation attacks
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    session_regenerate_id(true);

                    // Store the user's info in the session
                    $_SESSION['user_id']   = $user->getId();
                    $_SESSION['username']  = $user->getUsername();
                    $_SESSION['user_role'] = $user->getRole();

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

        // Delete the session cookie from the browser
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        // Send them back to the login page
        return $this->redirect()->toRoute('auth/login');
    }
}
