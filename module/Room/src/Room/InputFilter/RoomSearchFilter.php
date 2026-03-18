<?php
namespace Room\InputFilter;

use Zend\InputFilter\InputFilter;

/**
 * RoomSearchFilter — defines the validation RULES for each search field.
 *
 * Filters run first (they TRANSFORM data: trim whitespace, strip HTML tags).
 * Validators run second (they CHECK the cleaned data and accept or reject it).
 *
 * Kept separate from the form so the same rules can be reused
 * anywhere that needs to validate room search input (e.g. an API endpoint).
 *
 * Lecture 20: Forms & Input Validation
 */
class RoomSearchFilter extends InputFilter
{
    public function __construct()
    {
        // Rules for the 'type' dropdown
        $this->add(array(
            'name'     => 'type',
            'required' => false,       // Allowed to be empty (means "show all types")
            'filters'  => array(
                array('name' => 'StripTags'),   // Remove any HTML tags
                array('name' => 'StringTrim'),  // Remove leading/trailing whitespace
            ),
        ));

        // Rules for the 'min_price' text input
        $this->add(array(
            'name'     => 'min_price',
            'required' => false,       // Optional — 0 means no lower bound
            'filters'  => array(
                array('name' => 'StringTrim'),
            ),
            'validators' => array(
                array(
                    // Only digits allowed — reject "abc" or "50.5 EUR"
                    'name'    => 'Regex',
                    'options' => array('pattern' => '/^\d*$/'),
                ),
            ),
        ));
    }
}
