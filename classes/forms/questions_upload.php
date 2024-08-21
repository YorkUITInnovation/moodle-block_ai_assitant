<?php

namespace block_ai_assistant;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/config.php');

class questions_upload_form extends \moodleform
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
            'questions',
            get_string('questions', 'block_ai_assistant')
        );

        // Add html element for instructions
        $mform->addElement(
            'html',
            '<div class="alert alert-info">' . get_string('questions_instructions', 'block_ai_assistant') . '</div>'
        );

        $mform->addElement(
            'filepicker',
            'questions_upload',
            get_string('questions', 'block_ai_assistant'),
            null,
            array(
                'accepted_types' => array('.docx'),

            )
        );

        $mform->setType(
            'questions_upload',
            PARAM_FILE
        );

        $mform->addRule(
            'questions_upload',
            get_string('required', 'block_ai_assistant'),
            'required'
        );

        // Adde help header
        $mform->addElement(
            'header',
            'help',
            get_string('help', 'block_ai_assistant')
        );
        // Header should be closed
        $mform->setExpanded('help', false);

        // Add html element for help text
        $mform->addElement(
            'html',
            $OUTPUT->render_from_template('block_ai_assistant/document_template_question', [])
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
