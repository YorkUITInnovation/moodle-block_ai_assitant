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

require_once($CFG->dirroot . "/blocks/ai_assistant/classes/forms/questions_list.php");


global $CFG, $OUTPUT, $USER, $PAGE, $DB;

$PAGE->requires->js_call_amd('block_ai_assistant/delete_question', 'init');

// Get course id
$courseid = required_param('courseid', PARAM_INT);

$context = CONTEXT_COURSE::instance($courseid);

require_login(1, false);

$formdata = new stdClass();
$formdata->courseid = $courseid;

// Create form
$mform = new \block_ai_assistant\questions_list(null, array('formdata' => $formdata));
$mform->display();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/ai_assistant/questions_list.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('questions', 'block_ai_assistant'));
$PAGE->set_heading(get_string('questions', 'block_ai_assistant'));

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
