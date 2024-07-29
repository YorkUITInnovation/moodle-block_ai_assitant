<?php


use block_ai_assistant\webservice;
use block_ai_assistant\cria;

require_once("../../../config.php");


global $CFG, $OUTPUT, $USER, $PAGE, $DB;

$courseid = optional_param('courseid', 1, PARAM_INT);

require_login(1, false);

$context = context_course::instance($courseid);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/ai_assistant/test.php'));
$PAGE->set_title('Test');
$PAGE->set_heading('Test');
$config = get_config('block_ai_assistant');
echo $OUTPUT->header();
if (!$settings = $DB->get_record('block_aia_settings', ['courseid' => $courseid])) {
    \core\notification::error('Settings not found');
} else {
    $bot_name = explode('-', $settings->bot_name);
    $bot_id = str_replace('"', '', $bot_name[0]);
    $chat_id = cria::get_chat_id();
    $prompt = 'Who teaches the course?';

    $response = cria::get_gpt_response($chat_id, $bot_id, $prompt);

    print_object(json_decode($response));
    echo 'Answwer: ' . json_decode($response)->message;
}



echo $OUTPUT->footer();
