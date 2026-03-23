<?php
namespace Room\Form;

use Zend\Form\Form;

/**
 * RoomSearchForm — defines what fields the search form has.
 *
 * This class only describes the SHAPE of the form: which fields exist
 * and what element types they are (Select, Text, Submit...).
 * Validation rules live separately in RoomSearchFilter.
 *
 * Lecture 20: Forms & Input Validation
 */
class RoomSearchForm extends Form
{
    public function __construct()
    {
        parent::__construct('room-search');

        // A dropdown for room type
        $this->add(array(
            'type'    => 'Zend\Form\Element\Select',
            'name'    => 'type',
            'options' => array(
                'label'         => 'Room Type',
                'value_options' => array(
                    ''       => 'All Types',
                    'Single' => 'Single',
                    'Double' => 'Double',
                    'Suite'  => 'Suite',
                ),
            ),
        ));

        // A text input for minimum price
        $this->add(array(
            'type'    => 'Zend\Form\Element\Text',
            'name'    => 'min_price',
            'options' => array(
                'label' => 'Min Price (EUR)',
            ),
            'attributes' => array(
                'placeholder' => 'e.g. 100',
            ),
        ));

        // Submit button
        $this->add(array(
            'name'       => 'submit',
            'attributes' => array(
                'type'  => 'submit',
                'value' => 'Search Rooms',
            ),
        ));
    }
}
