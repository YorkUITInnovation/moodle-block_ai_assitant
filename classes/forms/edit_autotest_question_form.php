<?php

namespace block_ai_assistant;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/config.php');

class edit_autotest_question_form extends \moodleform
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
            'autotest_questions',
            get_string('autotest_questions', 'block_ai_assistant')
        );

       // Add text element for section
        $mform->addElement(
            'text',
            'section',
            get_string('section', 'block_ai_assistant'),
            array('size' => 50)
        );
        // SetType for section
        $mform->setType(
            'section',
            PARAM_TEXT
        );
        // Add required field for section
        $mform->addRule(
            'section',
            get_string('required', 'block_ai_assistant'),
            'required',
            null,
            'client'
        );
        // Add texarea element for questions
        $mform->addElement(
            'textarea',
            'questions',
            get_string('questions', 'block_ai_assistant'),
            array('rows' => 5, 'cols' => 50)
        );
        // SetType for questions
        $mform->setType(
            'questions',
            PARAM_TEXT
        );
        $mform->addRule(
            'questions',
            get_string('required', 'block_ai_assistant'),
            'required',
            null,
            'client'
        );
        // Add texarea element for human_answer
        $mform->addElement(
            'textarea',
            'human_answer',
            get_string('answer', 'block_ai_assistant'),
            array('rows' => 5, 'cols' => 50)
        );
        $mform->addRule(
            'human_answer',
            get_string('required', 'block_ai_assistant'),
            'required',
            null,
            'client'
        );

        $this->add_action_buttons();
        $this->set_data($formdata);
    }

    public function validation($data, $files)
    {
        global $DB;
        $errors = parent::validation($data, $files);
        return $errors;
    }
}
