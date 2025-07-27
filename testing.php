<?php

require_once(__DIR__ . '/../../config.php');
require_once('classes/external/course_modules_ws.php');
global $CFG, $DB, $OUTPUT, $USER, $PAGE;

use block_ai_assistant\webservice;
use block_ai_assistant\course_module_training;
use block_ai_assistant\course_modules;
use block_ai_assistant\cria;
use block_ai_assistant\chat;

$context = context_system::instance();

require_login(1, false);
$PAGE->set_url(new moodle_url('/blocks/learningassist/testing.php', []));
$PAGE->set_title('Testing');
$PAGE->set_heading('Test');
$PAGE->set_pagelayout('standard');
$PAGE->set_context($context);

$courseid = required_param('courseid', PARAM_INT);

echo $OUTPUT->header();

//$history = cria::chat_history('965a7170-5612-4d32-9e87-29cdba344c37');
//
//print_object($history);
//
//$response = cria::chat_send('965a7170-5612-4d32-9e87-29cdba344c37', 'I am a first year student.', '418-391');
//
//print_object($response);
$asset = $DB->get_record('block_aia_tutor_chat_assets', ['assetid' => '9aec2b9e93459f9aa97b9beae8b33519']);

echo '<img id=' . $asset->assetid . ' src="data:' . $asset->mimetype . ';base64,' . $asset->data . '" alt="Asset Image" style="max-width: 100%; height: auto;">';

echo $OUTPUT->footer();

