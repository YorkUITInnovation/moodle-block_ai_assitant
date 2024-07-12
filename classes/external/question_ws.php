<?php

/**
 * External Web Service Template
 *
 * @package    localwstemplate
 * @copyright  2011 Moodle Pty Ltd (http://moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->libdir . "/externallib.php");
require_once("$CFG->dirroot/config.php");


class block_ai_assistant_question_ws extends external_api
{
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function delete_parameters()
    {
        return new external_function_parameters(
            array(
                'questionid' => new external_value(PARAM_INT, 'Question id', false, 0),
                'courseid' => new external_value(PARAM_INT, 'Course id', false, 0)
            )
        );
    }

    /**
     * Deletes a question from the database
     * @param int $question_id
     * @param int $course_id
     * @return bool
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws restricted_context_exception
     */
    public static function delete($question_id, $course_id)
    {
        global $CFG, $USER, $DB, $PAGE;

        //Parameter validation
        $params = self::validate_parameters(
            self::delete_parameters(),
            array(
                'questionid' => $question_id,
                'courseid' => $course_id
            )
        );

        //Context validation
        $context = CONTEXT_COURSE::instance($course_id);
        self::validate_context($context);

        $course_record = $DB->get_record('block_aia_questions', array('courseid' => $course_id));
        if ($course_record) {
            $DB->delete_records('block_aia_questions', array('id' => $question_id));
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns method result value
     * @return external_description
     */
    public static function delete_returns()
    {
        return new external_value(PARAM_BOOL, 'Boolean');
    }
}
