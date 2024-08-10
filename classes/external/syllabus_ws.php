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
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED)
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

        // Call the API to delete the file
        $api_response = cria::delete_content_from_bot($cria_file_id);
        $DB->set_field('block_aia_settings', 'cria_file_id', 0, ['id' => $courserecord->id]);
        // Handle the API response
        // if ($api_response !== 'true') {
        //     throw new Exception('Failed to delete content via API: ' . $api_response);
        // }

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

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function publish_parameters() {
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
        // Delete file from Moodle file area
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'block_ai_assistant', 'syllabus', $course_id);
        foreach ($files as $file) {
            $file->delete();
        }
        // Delete file from Cria
        $cria_file_id = $courserecord->cria_file_id;
        $api_response = cria::delete_content_from_bot($cria_file_id);
        // Update record with cria_file_id 0
        $DB->set_field('block_aia_settings', 'cria_file_id', 0, ['courseid' => $course_id]);
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

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function training_status_parameters() {
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
    public static function training_status($course_id)
    {
        global $CFG, $USER, $DB, $PAGE;

        //Parameter validation
        $params = self::validate_parameters(
            self::training_status_parameters(),
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
        $data = cria::get_content_training_status($courserecord->cria_file_id);
        $results = [];
        $results[]['training_status_id'] = $data->training_status_id;
        $results[]['training_status'] = $data->training_status;
       file_put_contents('/var/www/moodledata/temp/training_status.json', json_encode($results, JSON_PRETTY_PRINT));
        return  json_encode($data);
    }

    /**
     * Returns method result value
     * @return external_description
     */
    public static function training_status_details()
    {
        $fields = array(
            'training_status_id' => new external_value(PARAM_INT, 'Training status id', false),
            'training_status' => new external_value(PARAM_TEXT, 'HTML Badge for training status', true)
        );
        return new external_single_structure($fields);
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
