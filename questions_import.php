<?php

/**
 * This file is part of Cria.
 * Cria is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * Cria is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with Cria. If not, see <https://www.gnu.org/licenses/>.
 *
 * @package    local_cria
 * @author     Patrick Thibaudeau
 * @copyright  2024 onwards York University (https://yorku.ca)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once("../../config.php");

require_once($CFG->dirroot . "/blocks/ai_assistant/classes/forms/questions_upload.php");

use block_ai_assistant\cria;
use block_ai_assistant\import;

/**
 * Extract document name from stored label suffix: "... [doc:<name>]".
 *
 * @param string $label
 * @return string
 */
function block_ai_assistant_extract_doc_name(string $label): string {
    if (preg_match('/\[doc:([^\]]+)\]\s*$/', $label, $matches)) {
        return trim((string)$matches[1]);
    }
    return '';
}

/**
 * Resolve a stable question document name for Criabot flows.
 *
 * @param mixed $upload_result
 * @param int $legacy_cria_id
 * @param string $file_name
 * @return string
 */
function block_ai_assistant_resolve_question_doc_name($upload_result, int $legacy_cria_id, string $file_name): string {
    if ($legacy_cria_id !== 0) {
        return '';
    }

    $doc_name = trim((string)$upload_result);
    if ($doc_name !== '') {
        return $doc_name;
    }

    // Some Criabot deployments return an empty upload identifier even after
    // successful /documents/upload; use filename as deterministic fallback.
    return trim($file_name);
}


global $CFG, $OUTPUT, $USER, $PAGE, $DB;

// Get course id
$courseid = required_param('courseid', PARAM_INT);
$intentid = 0; // Default value

$intent_id = cria::get_intent_id($courseid);

$context = CONTEXT_COURSE::instance($courseid);

require_login($courseid, false);

$formdata = new stdClass();
$formdata->courseid = $courseid;

// Create form
$mform = new \block_ai_assistant\questions_upload_form(null, array('formdata' => $formdata));
if ($mform->is_cancelled()) {
    //Handle form cancel operation, if cancel button is present on form
    redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid);
} else if ($data = $mform->get_data()) {
    // Check to see if cria directory exists
    $path = $CFG->dataroot . '/temp/cria';
    if (!is_dir($path)) {
        mkdir($path);
    }
    // Check to see if directory for this intent exists
    $path = $CFG->dataroot . '/temp/cria/' . $data->courseid;
    if (!is_dir($path)) {
        mkdir($path);
    }

    // Save file to directory
    $file_name = $mform->get_new_filename('questions_upload');
    $full_path = $path . '/' . $file_name;
    $success = $mform->save_file('questions_upload', $full_path, true);

    $IMPORT = new import($full_path);

    // Delete existing file
    $fs = get_file_storage();
    $files = $fs->get_area_files(
        $context->id,
        'block_ai_assistant',
        'questions',
        $data->courseid
    );
    foreach ($files as $file) {
        if (!$file->is_directory()) {
            if ($file->delete()) {
                // Existing question file deleted.
            }
        }
    }
    $context = \context_course::instance($data->courseid);

    $file_content = base64_encode(file_get_contents($full_path));

    // See if file already exists
    if ($record = $DB->get_record('block_aia_question_files', array('courseid' => $data->courseid))) {
        // Delete file on Cria
        if ($record->cria_fileid != 0) {
            cria::delete_content_from_bot($record->cria_fileid);
        } else {
            $doc_name = block_ai_assistant_extract_doc_name((string)$record->name);
            if ($doc_name !== '') {
                cria::delete_content_document_name_from_bot((int)$data->courseid, $doc_name, 'documents');
            }
        }
        // Create file on Cria
        $cria_file_id = cria::upload_content_to_bot($data->courseid, $file_name, $file_content, 'GENERIC');
        $legacy_cria_id = 0;
        if (is_int($cria_file_id) || (is_string($cria_file_id) && ctype_digit($cria_file_id))) {
            $legacy_cria_id = (int)$cria_file_id;
        }
        $doc_name = block_ai_assistant_resolve_question_doc_name($cria_file_id, $legacy_cria_id, $file_name);
        $record->name = $file_name;
        if ($doc_name !== '') {
            $record->name = substr(trim($file_name . ' [doc:' . $doc_name . ']'), 0, 255);
        }
        $record->cria_fileid = $legacy_cria_id;
        $record->usermodified = $USER->id;
        $record->timemodified = time();
        $DB->update_record('block_aia_question_files', $record);
    } else {
        // Create file on Cria
        $cria_file_id = cria::upload_content_to_bot($data->courseid, $file_name, $file_content, 'GENERIC');
        $legacy_cria_id = 0;
        if (is_int($cria_file_id) || (is_string($cria_file_id) && ctype_digit($cria_file_id))) {
            $legacy_cria_id = (int)$cria_file_id;
        }
        $doc_name = block_ai_assistant_resolve_question_doc_name($cria_file_id, $legacy_cria_id, $file_name);
        // Add record into block_aia_quesiton_files
        $record = new stdClass();
        $record->courseid = $data->courseid;
        $record->name = $file_name;
        if ($doc_name !== '') {
            $record->name = substr(trim($file_name . ' [doc:' . $doc_name . ']'), 0, 255);
        }
        $record->cria_fileid = $legacy_cria_id;
        $record->usermodified = $USER->id;
        $record->timecreated = time();
        $record->timemodified = time();
        $new_file_id = $DB->insert_record('block_aia_question_files', $record);
    }

    $file_record = array(
        'contextid' => $context->id,
        'component' => 'block_ai_assistant',
        'filearea' => 'questions',
        'itemid' => $data->courseid,
        'filepath' => '/',
        'filename' => $file_name
    );

    $file = $fs->create_file_from_pathname($file_record, $full_path);

    unlink($full_path);

    // Redirect with success message
    redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid);
} else {
    // Show form
    $mform->set_data($formdata);
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/ai_assistant/questions_import.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('upload_questions', 'block_ai_assistant'));
$PAGE->set_heading(get_string('upload_questions', 'block_ai_assistant'));

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
