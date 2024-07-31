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

global $CFG, $OUTPUT, $USER, $PAGE, $DB;

// Get course id
$courseid = required_param('courseid', PARAM_INT);

$context = context_course::instance($courseid);
require_login($courseid, false);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/ai_assistant/document_templates.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('document_templates', 'block_ai_assistant'));
$PAGE->set_heading(get_string('document_templates', 'block_ai_assistant'));
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('block_ai_assistant/document_templates', ['courseid' => $courseid]);
echo $OUTPUT->footer();
