<?php

namespace block_ai_assistant;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/config.php');

class autotest_form extends \moodleform
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
            'autotest_questions_header',
            get_string('autotest_questions', 'block_ai_assistant')
        );

        $mform->addElement(
            'filepicker',
            'autotest_questions',
            get_string('questions', 'block_ai_assistant'),
            null,
            array(
                'accepted_types' => array('.xlsx')
            )
        );

        $mform->setType(
            'autotest_questions',
            PARAM_FILE
        );

        $mform->addRule(
            'autotest_questions',
            get_string('required', 'block_ai_assistant'),
            'required'
        );

        // Add a delete questions selectyesno
        $mform->addElement(
            'selectyesno',
            'delete_questions',
            get_string('delete_questions', 'block_ai_assistant')
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
