<?php

require_once(__DIR__ . '/../../config.php');
require_once('classes/external/course_modules_ws.php');
global $CFG, $DB, $OUTPUT, $USER, $PAGE;

use block_ai_assistant\webservice;
use block_ai_assistant\course_module_training;
use block_ai_assistant\course_modules;
use block_ai_assistant\cria;
use block_ai_assistant\chat;
use block_ai_assistant\markitdown;



require_login(1, false);
$PAGE->set_url(new moodle_url('/blocks/learningassist/testing.php', []));
$PAGE->set_title('Testing');
$PAGE->set_heading('Test');
$PAGE->set_pagelayout('standard');
$PAGE->set_context($context);

$courseid = required_param('courseid', PARAM_INT);
$context = context_course::instance($courseid);

echo $OUTPUT->header();

print_object(cria::get_availability());

echo $OUTPUT->footer();
