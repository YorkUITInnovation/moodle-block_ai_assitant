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


class block_ai_assistant_autotest_ws extends external_api
{
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function delete_parameters()
    {
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, 'Question id', VALUE_REQUIRED),
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED)
            )
        );
    }

    /**
     * Deletes a question from the database
     * @param int $id
     * @param int $course_id
     * @return bool
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws restricted_context_exception
     */
    public static function delete($id, $course_id)
    {
        global $CFG, $USER, $DB, $PAGE;

        //Parameter validation
        $params = self::validate_parameters(
            self::delete_parameters(),
            array(
                'id' => $id,
                'courseid' => $course_id
            )
        );

        //Context validation
        $context = CONTEXT_COURSE::instance($course_id);
        self::validate_context($context);

        $course_record = $DB->get_record('block_aia_autotest', array('id' => $id,'courseid' => $course_id));
        if ($course_record) {
            $DB->delete_records('block_aia_autotest', array('id' => $id));
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
