<?php

namespace block_ai_assistant;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/config.php');


class questions_list extends \moodleform
{
    protected function definition()
    {
        global $CFG, $OUTPUT, $DB;

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
            'Questions',
            get_string('questions', 'block_ai_assistant')
        );

        $questions = $DB->get_records('block_aia_questions', array('courseid' => $formdata->courseid));

        $templatecontext = (object)[
            'questions' => array_values(array_map(function ($question) use ($formdata) {
                return (object)[
                    'id' => $question->id,
                    'courseid' => $formdata->courseid,
                    'question' => $question->name,
                    'answer' => $question->answer,
                    'edit_question' => new \moodle_url('/blocks/ai_assistant/edit_question.php', array('courseid' => $formdata->courseid, 'questionid' => $question->id))
                ];
            }, $questions))
        ];

        $html = $OUTPUT->render_from_template('block_ai_assistant/questions_list', $templatecontext);

        $mform->addElement('html', $html);
    }
}
