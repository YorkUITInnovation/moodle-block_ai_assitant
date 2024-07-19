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


class block_ai_assistant_publish_ws extends external_api
{
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function publish_parameters()
    {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course id', false, 0)
            )
        );
    }

    /**
     * @param int $course_id
     * @return bool
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws restricted_context_exception
     */
    public static function publish($course_id)
    {
        global $CFG, $USER, $DB, $PAGE;

        //Parameter validation
        $params = self::validate_parameters(
            self::publish_parameters(),
            array(
                'courseid' => $course_id
            )
        );

        //Context validation
        //OPTIONAL but in most web service it should present
        $context = CONTEXT_COURSE::instance($course_id);
        self::validate_context($context);

        // Get the course record to fetch cria_file_id
        $courserecord = $DB->get_record('block_aia_settings', array('courseid' => $course_id));
        if ($courserecord->published == 1) {
            // Update record with publish 0
            $DB->set_field('block_aia_settings', 'published', 0, ['courseid' => $course_id]);
            return false;
        } else {
            // Update record with publish 1
            $DB->set_field('block_aia_settings', 'published', 1, ['courseid' => $course_id]);
            return true;
        }
    }

    /**
     * Returns method result value
     * @return external_description
     */
    public static function publish_returns()
    {
        return new external_value(PARAM_INT, 'Boolean');
    }
}
