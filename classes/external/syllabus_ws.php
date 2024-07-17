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


class block_ai_assistant_syllabus_ws extends external_api
{
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function delete_parameters()
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
    public static function delete($course_id)
    {
        global $CFG, $USER, $DB, $PAGE;

        //Parameter validation
        $params = self::validate_parameters(
            self::delete_parameters(),
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
        if (!$courserecord || !isset($courserecord->cria_file_id)) {
            throw new invalid_parameter_exception('No cria_file_id found for the specified course');
        }

        $cria_file_id = $courserecord->cria_file_id;
        print_object($cria_file_id);

        // Call the API to delete the file
        $api_response = cria::delete_content_from_bot($cria_file_id);
        print_object($api_response);

        // Handle the API response
        if ($api_response !== 'true') {
            throw new Exception('Failed to delete content via API: ' . $api_response);
        }

        $fs = get_file_storage();
        // Get area files
        $files = $fs->get_area_files($context->id, 'block_ai_assistant', 'syllabus', $course_id);

        foreach ($files as $file) {
            $file->delete();
            if ($file->get_filename() != '.') {
                return true;
            }
        }
        // No file was deleted
        return false;
    }

    /**
     * Returns method result value
     * @return external_description
     */
    public static function delete_returns()
    {
        return new external_value(PARAM_INT, 'Boolean');
    }
}
