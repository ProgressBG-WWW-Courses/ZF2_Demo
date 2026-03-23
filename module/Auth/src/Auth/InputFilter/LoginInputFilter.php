<?php
// module/Auth/src/Auth/InputFilter/LoginInputFilter.php
namespace Auth\InputFilter;

use Zend\InputFilter\InputFilter;

class LoginInputFilter extends InputFilter
{
    public function __construct()
    {
        // Username: required, strip any HTML tags, trim whitespace
        $this->add(array(
            'name'       => 'username',
            'required'   => true,
            'filters'    => array(
                array('name' => 'StripTags'),
                array('name' => 'StringTrim'),
            ),
            'validators' => array(
                array('name' => 'NotEmpty'),
            ),
        ));

        // Password: required, strip any HTML tags, trim whitespace
        $this->add(array(
            'name'       => 'password',
            'required'   => true,
            'filters'    => array(
                array('name' => 'StripTags'),
                array('name' => 'StringTrim'),
            ),
            'validators' => array(
                array('name' => 'NotEmpty'),
            ),
        ));
    }
}
