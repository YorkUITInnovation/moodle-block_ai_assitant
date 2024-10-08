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

use block_ai_assistant\cria;

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
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED)
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

        $question_record = $DB->get_record('block_aia_questions', array('id' => $question_id));
        if ($question_record) {
            // Delete question from cria
            cria::delete_question($question_record->criaquestionid);
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








    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function delete_file_parameters()
    {
        return new external_function_parameters(
            array(
                'questionid' => new external_value(PARAM_INT, 'Question id', false, 0),
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED)
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
    public static function delete_file($question_id, $course_id)
    {
        global $CFG, $USER, $DB, $PAGE;

        //Parameter validation
        $params = self::validate_parameters(
            self::delete_file_parameters(),
            array(
                'questionid' => $question_id,
                'courseid' => $course_id
            )
        );

        //Context validation
        $context = CONTEXT_COURSE::instance($course_id);
        self::validate_context($context);

        $question_file = $DB->get_record('block_aia_question_files', array('id' => $question_id));
        if ($question_file) {
            // Delete fiel from Cria
            cria::delete_content_from_bot($question_file->cria_fileid);
            // Get file storage
            $fs = get_file_storage();
            if ($file = $fs->get_file(
                $context->id,
                'block_ai_assistant',
                'questions',
                $course_id,
                '/',
                $question_file->name)
            ) {
                $file->delete();
            }
            $DB->delete_records('block_aia_question_files', array('id' => $question_id));
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns method result value
     * @return external_description
     */
    public static function delete_file_returns()
    {
        return new external_value(PARAM_BOOL, 'Boolean');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function training_status_parameters() {
        return new external_function_parameters(
            array(
                'questionid' => new external_value(PARAM_INT, 'Question id', false, 0)
            )
        );
    }

    /**
     * @param int $question_id
     * @return bool
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws restricted_context_exception
     */
    public static function training_status($question_id)
    {
        global $CFG, $USER, $DB, $PAGE;

        //Parameter validation
        $params = self::validate_parameters(
            self::training_status_parameters(),
            array(
                'questionid' => $question_id
            )
        );

        $question = $DB->get_record('block_aia_question_files', array('id' => $question_id));

        //Context validation
        //OPTIONAL but in most web service it should present
        $context = CONTEXT_COURSE::instance($question->courseid);
        self::validate_context($context);

        // Get the course record to fetch cria_file_id

        $data = cria::get_content_training_status($question->cria_fileid);

        return  json_encode($data);
    }

    /**
     * Returns method result value
     * @return external_description
     */
    public static function training_status_returns()
    {
        // Return array of training status
        return new external_value(PARAM_RAW, 'JSON Formated data');
    }
}
