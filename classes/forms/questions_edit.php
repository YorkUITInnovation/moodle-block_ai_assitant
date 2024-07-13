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
            get_string('edit_questions', 'block_ai_assistant')
        );
        $attributes = array('size' => '20');
        $mform->addElement('text', 'name', get_string('name', 'block_ai_assistant'), $attributes);
        $mform->addElement('textarea', 'question', get_string("question", "block_ai_assistant"), 'wrap="virtual" rows="2" cols="20"');
        $editor_options = array(
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'maxbytes' => $CFG->maxbytes,
            'trusttext' => true,
            'subdirs' => true
        );

        $mform->addElement('editor', 'answer', get_string('answer', 'block_ai_assistant'), null, $editor_options);
        $mform->setType('answer', PARAM_RAW);
        $options = [
            'option1' => 'YES',
            'option2' => 'NO'
        ];

        $mform->addElement('select', 'FIELDNAME', get_string('letAIGenerate', 'block_ai_assistant'), $options);
        $mform->setDefault('letAIGenerate', 'option1');
        $mform->addElement('textarea', 'keywords', get_string("keywords", "block_ai_assistant"), 'wrap="virtual" rows="1" cols="70"');
        $mform->addElement('textarea', 'related_question', get_string("related_question", "block_ai_assistant"), 'wrap="virtual" rows="2" cols="20"');
        $this->add_action_buttons();
    }
}
