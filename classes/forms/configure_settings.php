<?php

namespace block_ai_assistant;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/config.php');


class configure_settings extends \moodleform
{
    protected function definition()
    {
        global $CFG, $OUTPUT;

        $formdata = $this->_customdata['formdata'];
        $mform = &$this->_form;

        $mform->addElement(
            'hidden',
            'id'
        );

        $mform->setType(
            'id',
            PARAM_INT
        );

        $mform->addElement(
            'hidden',
            'courseid'
        );

        $mform->setType(
            'courseid',
            PARAM_INT
        );



        $mform->addElement(
            'header',
            'Configure Settings',
            get_string('configure_settings', 'block_ai_assistant')
        );
        $attributes = array('size' => '20');
        $mform->addElement('text', 'subtitle', get_string('subtitle', 'block_ai_assistant'), $attributes);
        $mform->addElement('textarea', 'welcome_message', get_string("welcome_message", "block_ai_assistant"), 'wrap="virtual" rows="2" cols="20"');
        $mform->addElement('textarea', 'no_context_message', get_string("no_context_message", "block_ai_assistant"), 'wrap="virtual" rows="2" cols="20"');
        $options =array(
            1 => get_string('bottom_left', 'block_ai_assistant'),
            2 => get_string('bottom_right', 'block_ai_assistant'),
            3 => get_string('top_left', 'block_ai_assistant'),
            4 => get_string('top_right', 'block_ai_assistant'),
        );
        $mform->addElement('select', 'embed_position', get_string('embed_position', "block_ai_assistant"), $options);
        
    
        $this->add_action_buttons();

       
    }

}
    

