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
require_once("$CFG->dirroot/blocks/ai_assistant/classes/tables/questions_table.php");
global $CFG, $OUTPUT, $USER, $PAGE, $DB;

// Get course id
$courseid = required_param('courseid', PARAM_INT);

$context = context_course::instance($courseid);

require_login($courseid, false);

$download = optional_param('download', '', PARAM_ALPHA);

$PAGE->requires->js_call_amd('block_ai_assistant/questions_list', 'init',[]);

$table = new questions_table('id');
$table->is_downloading($download, 'auto_test_download', 'auto_test');
// Work out the sql for the table.
$table->set_sql(
    'id,courseid,name,value,answer',
    "{block_aia_questions}",
    'courseid=' . $courseid
);
if (!$table->is_downloading()) {
    // Only print headers if not asked to download data
    // Print the page header
    $PAGE->set_url(new moodle_url('/blocks/ai_assistant/questions_list.php', ['courseid' => $courseid]));
    $PAGE->set_title(get_string('questions', 'block_ai_assistant'));
    $PAGE->set_heading(get_string('questions', 'block_ai_assistant'));
    $PAGE->navbar->add('Downloading data', new moodle_url('/blocks/ai_assistant/question_list.php', ['courseid' => $courseid]));
    echo $OUTPUT->header();
} else {
    $PAGE->set_context($context);
    $PAGE->set_url(new moodle_url('/blocks/ai_assistant/questions_list.php', ['courseid' => $courseid]));
    $PAGE->set_title(get_string('questions', 'block_ai_assistant'));
    $PAGE->set_heading(get_string('questions', 'block_ai_assistant'));
    echo $OUTPUT->header();
}

$table->define_baseurl("$CFG->wwwroot/blocks/ai_assistant/questions_list.php?courseid=$courseid");
echo $OUTPUT->render_from_template('block_ai_assistant/questions_list_buttons', ['courseid' => $courseid]);
$table->out(40, true);

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}
