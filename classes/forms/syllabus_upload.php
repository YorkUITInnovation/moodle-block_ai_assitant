<?php


namespace block_ai_assistant;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/config.php');

class syllabus_upload_form extends \moodleform
{

    protected function definition()
    {
        global $CFG, $OUTPUT;

        $formdata = $this->_customdata['formdata'];
        // Create form object
        $mform = &$this->_form;

        $mform->addElement(
            'hidden',
            'id'
        );
        $mform->setType(
            'id',
            PARAM_INT
        );
        // Intent id
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
            'syllabus',
            get_string('syllabus', 'block_ai_assistant')
        );

        $mform->addElement(
            'filepicker',
            'syllabus_upload',
            get_string('syllabus', 'block_ai_assistant'),
            null,
            array(
                'accepted_types' => array('.docx', '.xlsx')
            )
        );

        $mform->setType(
            'syllabus_upload',
            PARAM_FILE
        );

        $mform->addRule(
            'syllabus_upload',
            get_string('required', 'block_ai_assistant'),
            'required'
        );

        $this->add_action_buttons();
        $this->set_data($formdata);

    }


    // Perform some extra moodle validation
    public function validation($data, $files)
    {
        global $DB;

        $errors = parent::validation($data, $files);

        return $errors;
    }

}
