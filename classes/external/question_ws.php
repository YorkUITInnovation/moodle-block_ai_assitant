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
     * Extract document name from stored label suffix: "... [doc:<name>]".
     *
     * @param string $label
     * @return string
     */
    private static function extract_document_name_from_label(string $label): string
    {
        if (preg_match('/\[doc:([^\]]+)\]\s*$/', $label, $matches)) {
            return trim((string)$matches[1]);
        }
        // Backward compatibility: older rows stored plain filename without [doc:*] suffix.
        return trim($label);
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function delete_parameters()
    {
        return new external_function_parameters(
            array(
                'questionid' => new external_value(PARAM_INT, 'Question id', VALUE_REQUIRED),
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
                'questionid' => new external_value(PARAM_INT, 'Question id', VALUE_DEFAULT, 0),
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

        $question_file = null;
        if ((int)$question_id > 0) {
            $question_file = $DB->get_record('block_aia_question_files', array('id' => (int)$question_id));
        }
        if (!$question_file) {
            $question_file = $DB->get_record('block_aia_question_files', array('courseid' => (int)$course_id));
        }

        if ($question_file) {
            // Delete fiel from Cria
            if (!empty($question_file->cria_fileid) && (int)$question_file->cria_fileid > 0) {
                cria::delete_content_from_bot($question_file->cria_fileid);
            } else {
                $doc_name = self::extract_document_name_from_label((string)$question_file->name);
                if ($doc_name !== '') {
                    cria::delete_content_document_name_from_bot((int)$course_id, $doc_name, 'documents');
                }
            }
            // Get file storage
            $fs = get_file_storage();
            $storedfilename = (string)$question_file->name;
            if (preg_match('/\s*\[doc:[^\]]+\]\s*$/', $storedfilename)) {
                $storedfilename = trim((string)preg_replace('/\s*\[doc:[^\]]+\]\s*$/', '', $storedfilename));
            }

            if ($file = $fs->get_file(
                $context->id,
                'block_ai_assistant',
                'questions',
                $course_id,
                '/',
                $storedfilename)
            ) {
                $file->delete();
            }
            $DB->delete_records('block_aia_question_files', array('id' => $question_id));
            return true;
        } else {
            // Idempotent delete: already absent should still be treated as success.
            return true;
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
                'questionid' => new external_value(PARAM_INT, 'Question id', VALUE_REQUIRED)
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
        if (!$question) {
            $data = new \stdClass();
            $data->training_status_id = 4;
            $data->training_status = '';
            return json_encode($data);
        }

        //Context validation
        //OPTIONAL but in most web service it should present
        $context = CONTEXT_COURSE::instance($question->courseid);
        self::validate_context($context);

        // Get the course record to fetch cria_file_id

        // Prefer document-name marker for Criabot flow; legacy numeric IDs are fallback.
        $doc_name = self::extract_document_name_from_label((string)$question->name);
        if ($doc_name !== '') {
            $data = new \stdClass();
            $data->training_status_id = 1;
            $data->training_status = '<div class="badge badge-success">'
                . get_string('trained', 'block_ai_assistant') . '</div>';
        } else if (!empty($question->cria_fileid) && (int)$question->cria_fileid > 0) {
            $data = cria::get_content_training_status($question->cria_fileid);
        } else {
            $data = new \stdClass();
            $data->training_status_id = 0;
            $data->training_status = '<div class="badge badge-warning">'
                . get_string('pending', 'block_ai_assistant') . '</div>';
        }

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
