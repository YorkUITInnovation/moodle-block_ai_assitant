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
    redirect($CFG->wwwroot . '/blocks/ai_assistant/questions_list.php?courseid=' . $courseid);
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

    // Save file to moodle
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'block_ai_assistant', 'questions', $data->courseid);
    foreach ($files as $file) {
        if (!$file->is_directory()) {
            if ($file->get_filename() == $file_name) {
                $file->delete();
            }
        }
    }
    $context = \context_course::instance($data->courseid);

    $file_content = base64_encode(file_get_contents($full_path));
    $cria_file_id = cria::upload_content_to_bot($data->courseid, $file_name, $file_content, 'ALSYLLABUS');

    // Add record into block_aia_quesiton_files
    $record = new stdClass();
    $record->courseid = $data->courseid;
    $record->name = $file_name;
    $record->cria_fileid = $cria_file_id;
    $record->usermodified = $USER->id;
    $record->timecreated = time();
    $record->timemodified = time();
    $new_file_id = $DB->insert_record('block_aia_question_files', $record);

    //save the uploaded file in the draft area
    file_save_draft_area_files(
        $data->questions_upload,
        $context->id,
        'block_ai_assistant',
        'questions',
        $data->courseid,
        array('subdirs' => 0)
    );


    unlink($full_path);

    // Redirect with success message
    redirect($CFG->wwwroot . '/blocks/ai_assistant/questions_list.php?courseid=' . $courseid);
} else {
    // Show form
    $mform->set_data($formdata);
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/ai_assistant/questions_import.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('autotest', 'block_ai_assistant'));
$PAGE->set_heading(get_string('autotest', 'block_ai_assistant'));

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
