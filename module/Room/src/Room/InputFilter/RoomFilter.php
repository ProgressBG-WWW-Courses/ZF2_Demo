<?php
namespace Room\InputFilter;

use Zend\InputFilter\InputFilter;

/**
 * RoomFilter — validation rules for creating / editing a room.
 *
 * Lecture 20: Forms & Input Validation
 */
class RoomFilter extends InputFilter
{
    public function __construct()
    {
        $this->add(array(
            'name'     => 'number',
            'required' => true,
            'filters'  => array(
                array('name' => 'StripTags'),
                array('name' => 'StringTrim'),
            ),
            'validators' => array(
                array('name' => 'NotEmpty'),
                array('name' => 'StringLength', 'options' => array('min' => 1, 'max' => 10)),
            ),
        ));

        $this->add(array(
            'name'     => 'type',
            'required' => true,
            'filters'  => array(
                array('name' => 'StripTags'),
                array('name' => 'StringTrim'),
            ),
            'validators' => array(
                array('name' => 'NotEmpty'),
                array(
                    'name'    => 'InArray',
                    'options' => array('haystack' => array('Single', 'Double', 'Suite')),
                ),
            ),
        ));

        $this->add(array(
            'name'     => 'price',
            'required' => true,
            'filters'  => array(
                array('name' => 'StringTrim'),
            ),
            'validators' => array(
                array('name' => 'NotEmpty'),
                array(
                    'name'    => 'Regex',
                    'options' => array('pattern' => '/^\d+(\.\d{1,2})?$/'),
                ),
            ),
        ));

        $this->add(array(
            'name'     => 'description',
            'required' => false,
            'filters'  => array(
                array('name' => 'StripTags'),
                array('name' => 'StringTrim'),
            ),
        ));
    }
}
