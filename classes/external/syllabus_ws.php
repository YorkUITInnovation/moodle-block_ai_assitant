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

        // Get the course record to fetch cria_file_id or stored document name.
        $courserecord = $DB->get_record('block_aia_settings', array('courseid' => $course_id));
        if ($courserecord) {
            $remote_deleted = false;
            if (isset($courserecord->cria_file_id) && (int)$courserecord->cria_file_id > 0) {
                // Call the API to delete the file by legacy numeric ID.
                cria::delete_content_from_bot($courserecord->cria_file_id);
                $remote_deleted = true;
            }

            // Fallback for newer document-name paths when numeric ID is absent.
            $docname = trim((string)($courserecord->syllabus_document_name ?? ''));
            if ($docname !== '') {
                $by_name_deleted = cria::delete_content_document_name_from_bot((int)$course_id, $docname, 'documents');
                $remote_deleted = $remote_deleted || $by_name_deleted;
            }

            // Reset local linkage fields after delete attempt.
            $DB->set_field('block_aia_settings', 'cria_file_id', 0, ['id' => $courserecord->id]);
            $DB->set_field('block_aia_settings', 'syllabus_document_name', '', ['id' => $courserecord->id]);
            $DB->set_field('block_aia_settings', 'syllabus_trained', 0, ['id' => $courserecord->id]);
        }

        $fs = get_file_storage();
        // Get area files
        $files = $fs->get_area_files($context->id, 'block_ai_assistant', 'syllabus', $course_id);

        $deleted = false;
        foreach ($files as $file) {
            $file->delete();
            if ($file->get_filename() != '.') {
                $deleted = true;
            }
        }
        // Idempotent delete: return true even when nothing existed.
        return true;
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

        // Get the course record to fetch cria_file_id.
        $courserecord = $DB->get_record('block_aia_settings', array('courseid' => $course_id));

        $data = new \stdClass();
        $data->training_status_id = 4;
        $data->training_status = '';

        $criafileid = 0;
        $syllabus_doc_name = '';
        if ($courserecord && isset($courserecord->cria_file_id)) {
            $criafileid = (int)$courserecord->cria_file_id;
        }
        if ($courserecord && isset($courserecord->syllabus_document_name)) {
            $syllabus_doc_name = trim((string)$courserecord->syllabus_document_name);
        }

        if ($criafileid > 0) {
            $data = cria::get_content_training_status($criafileid);
        } else if ($syllabus_doc_name !== '') {
            $data->training_status_id = 1;
            $data->training_status = '<div class="badge badge-success">'
                . get_string('trained', 'block_ai_assistant') . '</div>';
        } else {
            $fs = get_file_storage();
            $files = $fs->get_area_files($context->id, 'block_ai_assistant', 'syllabus', $course_id, 'itemid', false);
            $haslocalsyllabus = false;
            foreach ($files as $file) {
                if (!$file->is_directory()) {
                    $haslocalsyllabus = true;
                    break;
                }
            }

            if ($haslocalsyllabus) {
                $data->training_status_id = 0;
                $data->training_status = '<div class="badge badge-warning">'
                    . get_string('pending', 'block_ai_assistant') . '</div>';
            }
        }

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
