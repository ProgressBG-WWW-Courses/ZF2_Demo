<?php
namespace Room\Form;

use Zend\Form\Form;

/**
 * RoomForm — create / edit a hotel room.
 *
 * This form performs a write operation (INSERT / UPDATE), so it includes
 * a CSRF token. The token is generated automatically by Zend\Form\Element\Csrf
 * and checked automatically inside isValid() — no extra code needed.
 *
 * Contrast with RoomSearchForm: search is read-only so it has no CSRF.
 *
 * Lecture 20: Forms & Input Validation
 */
class RoomForm extends Form
{
    public function __construct()
    {
        parent::__construct('room');

        $this->add(array(
            'type'    => 'Zend\Form\Element\Text',
            'name'    => 'number',
            'options' => array('label' => 'Room Number'),
            'attributes' => array('placeholder' => 'e.g. 101'),
        ));

        $this->add(array(
            'type'    => 'Zend\Form\Element\Select',
            'name'    => 'type',
            'options' => array(
                'label'         => 'Room Type',
                'value_options' => array(
                    'Single' => 'Single',
                    'Double' => 'Double',
                    'Suite'  => 'Suite',
                ),
            ),
        ));

        $this->add(array(
            'type'    => 'Zend\Form\Element\Text',
            'name'    => 'price',
            'options' => array('label' => 'Price per Night (BGN)'),
            'attributes' => array('placeholder' => 'e.g. 150'),
        ));

        $this->add(array(
            'type'    => 'Zend\Form\Element\Textarea',
            'name'    => 'description',
            'options' => array('label' => 'Description'),
        ));

        // CSRF protection — generates a unique hidden token stored in the session.
        // isValid() rejects the form if the token is missing or expired.
        // This prevents cross-site request forgery attacks.
        $this->add(array(
            'type'    => 'Zend\Form\Element\Csrf',
            'name'    => 'csrf',
            'options' => array(
                'csrf_options' => array('timeout' => 600),
            ),
        ));

        $this->add(array(
            'name'       => 'submit',
            'attributes' => array('type' => 'submit', 'value' => 'Add Room'),
        ));
    }
}
