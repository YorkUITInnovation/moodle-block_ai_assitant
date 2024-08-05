<?php

namespace block_ai_assistant;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/config.php');


class questions_edit extends \moodleform
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
            'hidden',
            'questionid'
        );

        $mform->setType(
            'questionid',
            PARAM_INT
        );

        $mform->addElement(
            'header',
            'Edit Questions',
            get_string('edit_question', 'block_ai_assistant')
        );
        $attributes = array('size' => '20');
        $mform->addElement('text', 'name', get_string('name', 'block_ai_assistant'), $attributes);
        $mform->setType('name', PARAM_TEXT);
        $mform->addElement('text', 'value', get_string("question", "block_ai_assistant"), 'wrap="virtual" rows="2" cols="20"');
        $mform->setType('value', PARAM_TEXT);

        $mform->addElement('textarea', 'answer', get_string('answer', 'block_ai_assistant'), 'wrap="virtual" rows="4" cols="20"');
        $mform->setType('answer', PARAM_RAW);
        $options = [
            'option1' => 'YES',
            'option2' => 'NO'
        ];

        $this->add_action_buttons();
        $this->set_data($formdata);
    }
}
