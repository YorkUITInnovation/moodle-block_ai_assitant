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
require_once($CFG->dirroot . "/blocks/ai_assistant/classes/forms/questions_edit.php");

global $CFG, $OUTPUT, $USER, $PAGE, $DB;

// Get course id
$courseid = required_param('courseid', PARAM_INT);

$context = context_course::instance($courseid);

require_login($courseid);

$questionid = required_param('questionid', PARAM_INT);

$question = $DB->get_record('block_aia_questions', ['id' => $questionid]);
if (!$question) {
    throw new moodle_exception('question_not_found', 'block_ai_assistant');
}

$formdata = new stdClass();
$formdata->courseid = $courseid;
$formdata->questionid = $question->id;
$formdata->name = $question->name;
$formdata->question = $question->value;
$formdata->answer = array(
    'text' => $question->answer,
    'format' => FORMAT_HTML,
);

// Create form
$mform = new \block_ai_assistant\questions_edit(null, array('formdata' => $formdata));
$mform->set_data($formdata);

if ($mform->is_cancelled()) {
    // Handle form cancel operation, if cancel button is present on form
    redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid);
} else if ($data = $mform->get_data()) {

    $updatedrecord = new stdClass();
    $updatedrecord->id = $questionid;
    $updatedrecord->name = $data->name;
    $updatedrecord->value = $data->question;
    $updatedrecord->answer = $data->answer['text'];
    $DB->update_record('block_aia_questions', $updatedrecord);

    redirect($CFG->wwwroot . '/blocks/ai_assistant/questions_list.php?courseid=' . $courseid, get_string('question_updated_successfully', 'block_ai_assistant'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/ai_assistant/questions_edit.php', ['courseid' => $courseid, 'questionid' => $questionid]));
$PAGE->set_title(get_string('questions', 'block_ai_assistant'));
$PAGE->set_heading(get_string('questions', 'block_ai_assistant'));

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
