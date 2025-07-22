<?php

namespace block_ai_assistant;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/lib/formslib.php');

class tutorials extends \moodleform
{
    protected function definition()
    {
        global $OUTPUT;

        $formdata = $this->_customdata['formdata'];
        $mform = &$this->_form;

        // Hidden fields
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'blockid');
        $mform->setType('blockid', PARAM_INT);

        // Header
        $mform->addElement(
            'header',
            'tutorial_header',
            get_string('tutorial', 'block_ai_assistant')
        );

        // Name field
        $mform->addElement(
            'text',
            'name',
            get_string('name', 'block_ai_assistant'),
            array('size' => '50', 'maxlength' => '255')
        );
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        // Description field
        $mform->addElement(
            'textarea',
            'description',
            get_string('description', 'block_ai_assistant'),
            array('wrap' => 'virtual', 'rows' => '4', 'cols' => '50')
        );
        $mform->setType('description', PARAM_RAW);

        // Prompt field
        $mform->addElement(
            'textarea',
            'prompt',
            get_string('prompt', 'block_ai_assistant'),
            array('wrap' => 'virtual', 'rows' => '6', 'cols' => '50')
        );
        $mform->setType('prompt', PARAM_RAW);

        // Enabled field
        $mform->addElement(
            'selectyesno',
            'enabled',
            get_string('enabled', 'block_ai_assistant')
        );
        $mform->setType('enabled', PARAM_INT);
        $mform->setDefault('enabled', 0);
        $mform->addHelpButton('enabled', 'enabled', 'block_ai_assistant');

        $this->add_action_buttons();
        $this->set_data($formdata);
    }

    /**
     * Form validation
     */
    public function validation($data, $files)
    {
        $errors = parent::validation($data, $files);

        // Validate name is not empty
        if (empty(trim($data['name']))) {
            $errors['name'] = get_string('required' , 'block_ai_assistant');
        }

        if (empty(trim($data['prompt']))) {
            $errors['prompt'] = get_string('required', 'block_ai_assistant');
        }

        return $errors;
    }
}
