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

require_once($CFG->dirroot . "/blocks/ai_assistant/classes/forms/syllabus_upload.php");

use block_ai_assistant\cria;


global $CFG, $OUTPUT, $USER, $PAGE, $DB;

// Get course id
$courseid = required_param('courseid', PARAM_INT);

$context = CONTEXT_COURSE::instance($courseid);

require_login($courseid, false);

$formdata = new stdClass();
$formdata->courseid = $courseid;

// Create form
$mform = new \block_ai_assistant\syllabus_upload_form(null, array('formdata' => $formdata));
if ($mform->is_cancelled()) {
    //Handle form cancel operation, if cancel button is present on form
    redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid);
} else if ($data = $mform->get_data()) {
    // Get block settings
    $block_settings = $DB->get_record('block_aia_settings', ['courseid' => $courseid]);
    // Clear existing files in the draft area
    $fs = get_file_storage();
    $existingfiles = $fs->get_area_files($context->id, 'block_ai_assistant', 'syllabus', $data->courseid, 'itemid', false);
    foreach ($existingfiles as $existingfile) {
        $existingfile->delete();
    }
    // delete file from cria
    if ($block_settings->cria_file_id != 0) {
        cria::delete_content_from_bot($block_settings->cria_file_id);
    }

    //save the uploaded file in the draft area
    file_save_draft_area_files(
        $data->syllabus_upload,
        $context->id,
        'block_ai_assistant',
        'syllabus',
        $data->courseid,
        array('subdirs' => 0, 'maxfiles' => 1)
    );

    $filepath = cria::copy_file_to_temp_folder($context->id, $courseid);
    $prepare_file = cria::get_upload_content_to_bot_config($filepath);
    $file_id = cria::upload_content_to_bot($courseid, $prepare_file->file_name, $prepare_file->file_content);
    $DB->set_field('block_aia_settings', 'cria_file_id', $file_id, ['courseid' => $courseid]);
    redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid);
} else {
    // Show form
    $mform->set_data($formdata);
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/ai_assistant/syllabus_upload.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('syllabus', 'block_ai_assistant'));
$PAGE->set_heading(get_string('syllabus', 'block_ai_assistant'));

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
