<?php

/**
 * External Web Service Template
 *
 * @package    localwstemplate
 * @copyright  2011 Moodle Pty Ltd (http://moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/config.php");

use block_ai_assistant\course_modules;
use block_ai_assistant\cria;

class block_ai_assistant_course_modules_ws extends external_api
{
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function display_modules_parameters()
    {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED)
            )
        );
    }

    /**
     * Dispalys course modules
     * @param int $course_id
     * @return bool
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws restricted_context_exception
     */
    public static function display_modules($course_id)
    {
        global $OUTPUT;

        //Parameter validation
        $params = self::validate_parameters(
            self::display_modules_parameters(),
            array(
                'courseid' => $course_id
            )
        );

        //Context validation
        $context = \context_course::instance($course_id);
        self::validate_context($context);

        $course_modules = course_modules::get_course_modules($course_id);
        $display = $OUTPUT->render_from_template('block_ai_assistant/course_modules', $course_modules);

        return $display;
    }

    /**
     * Returns method result value
     * @return external_description
     */
    public static function display_modules_returns()
    {
        return new external_value(PARAM_RAW, 'HTML of course modules');
    }


//new webservice to insert module

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function insert_parameters()
    {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
                'selected_modules' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'filename' => new external_value(PARAM_TEXT, 'Filename'),
                            'content' => new external_value(PARAM_RAW, 'Content'),
                            'courseid' => new external_value(PARAM_INT, 'Course id'),
                            'cmid' => new external_value(PARAM_INT, 'CM ID'),
                            'modname' => new external_value(PARAM_TEXT, 'Module Name')
                        )
                    )
                )
            )
        );
    }

    /**
     * inserts course modules
     * @param int $courseid
     * @param array $selected_modules
     * @return bool
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws restricted_context_exception
     */
    public static function insert($courseid, $selected_modules)
    {
        global $DB;

        //Parameter validation
        $params = self::validate_parameters(
            self::insert_parameters(),
            array(
                'courseid' => $courseid,
                'selected_modules' => $selected_modules
            )
        );

        $context = \context_course::instance($courseid);
        self::validate_context($context);

        foreach ($selected_modules as $module) {
            if (isset($module['cmid']) && isset($module['courseid'])) {
                $file_id = course_modules::insert_record((object)$module); // Ensure the data is cast to an object
                // If there is a file id, send the content to cria
                if ($file_id > 0) {
                    file_put_contents('/var/www/moodledata/temp/' . $module['filename'], base64_decode($module['content']));
                    $cria_file_id = cria::upload_content_to_bot($module['courseid'], $module['filename'], $module['content']);
                    if ($cria_file_id > 0) {
                        $DB->set_field('block_aia_course_modules', 'cria_fileid', $cria_file_id, ['id' => $file_id]);
                    }
                }
            }
        }
        return true;
    }

    /**
     * Returns method result value
     * @return external_description
     */
    public static function insert_returns()
    {
        return new external_value(PARAM_BOOL, 'True or false');
    }
}