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

require_once($CFG->dirroot . "/blocks/ai_assistant/classes/forms/edit_autotest_question_form.php");


global $CFG, $OUTPUT, $USER, $PAGE, $DB;

// Get course id
$courseid = required_param('courseid', PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);

$context = CONTEXT_COURSE::instance($courseid);

require_login(1, false);

if (!$formdata = $DB->get_record('block_aia_autotest', array('id' => $id))) {
    $formdata = new stdClass();
    $formdata->courseid = $courseid;
}

// Create form
$mform = new \block_ai_assistant\edit_autotest_question_form(null, array('formdata' => $formdata));
if ($mform->is_cancelled()) {
    //Handle form cancel operation, if cancel button is present on form
    redirect($CFG->wwwroot . '/blocks/ai_assistant/autotest.php?courseid=' . $courseid);
} else if ($data = $mform->get_data()) {
    $data->timemodified = time();
    $data->usermodified = $USER->id;
    if ($data->id) {
        $DB->update_record('block_aia_autotest', $data);
    } else {
        $data->usercreated = $USER->id;
        $DB->insert_record('block_aia_autotest', $data);
    }

    // Redirect with success message
    redirect($CFG->wwwroot . '/blocks/ai_assistant/autotest.php?courseid=' . $courseid);
} else {
    // Show form
    $mform->set_data($formdata);
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/ai_assistant/edit_autotest_question.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('Autotest', 'block_ai_assistant'));
$PAGE->set_heading(get_string('Autotest', 'block_ai_assistant'));

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
