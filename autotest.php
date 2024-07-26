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

require_once($CFG->dirroot . "/blocks/ai_assistant/classes/forms/autotest.php");

use block_ai_assistant\cria;


global $CFG, $OUTPUT, $USER, $PAGE, $DB;

// Get course id
$courseid = required_param('courseid', PARAM_INT);
$intentid = 0; // Default value


$courserecord = $DB->get_record('block_aia_settings', array('courseid' => $courseid));
if ($courserecord) {
    $bot_name = $courserecord->bot_name;
    $intentid = (int)explode('-', $bot_name)[1];
}

$context = CONTEXT_COURSE::instance($courseid);

require_login(1, false);

$formdata = new stdClass();
$formdata->courseid = $courseid;

// Create form
$mform = new \block_ai_assistant\autotest_form(null, array('formdata' => $formdata));
if ($mform->is_cancelled()) {
    //Handle form cancel operation, if cancel button is present on form
    redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid);
} else if ($data = $mform->get_data()) {

    $fs = get_file_storage();
    $existingfiles = $fs->get_area_files($context->id, 'block_ai_assistant', 'autotest_questions', $data->courseid, 'itemid', false);
    foreach ($existingfiles as $existingfile) {
        $existingfile->delete();
    }

    file_save_draft_area_files(
        $data->autotest_questions,
        $context->id,
        'block_ai_assistant',
        'autotest_questions',
        $data->courseid,
        array('subdirs' => 0, 'maxfiles' => 1)
    );

    // //check if the file is .xlsx or .docx, if .docx call cria::get_question_json_format
    $files = $fs->get_area_files($context->id, 'block_ai_assistant', 'autotest_questions', $data->courseid, 'itemid', false);
    $file = reset($files);
    // $extension = pathinfo($file->get_filename(), PATHINFO_EXTENSION);
    // if ($extension == 'docx') {
    //     $jsonQuestionObj = cria::get_question_json_format($file->get_content());
    //     cria::create_questions_from_json($jsonQuestionObj, $intentid, $data->courseid);
    // } else {
        cria::autotest_qa_from_xlsx($file, $intentid, $data->courseid);
    // }

    // Redirect with success message
    redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid, get_string('file_uploaded_successfully', 'block_ai_assistant'), null, \core\output\notification::NOTIFY_SUCCESS);
} else {
    // Show form
    $mform->set_data($formdata);
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/ai_assistant/autotest.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('Autotest', 'block_ai_assistant'));
$PAGE->set_heading(get_string('Autotest', 'block_ai_assistant'));

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
