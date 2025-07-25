<?php
require_once("../../config.php");

use block_ai_assistant\course_modules;

global $CFG, $OUTPUT, $USER, $PAGE, $DB;

require_once("$CFG->dirroot/blocks/ai_assistant/classes/external/chat.php");

$course_id = required_param('courseid', PARAM_INT);
$cmid = required_param('cmid', PARAM_INT);
$tutorial_id = required_param('tutorialid', PARAM_INT);
$name = required_param('name', PARAM_RAW);
$chat_id = optional_param('chatid', '', PARAM_RAW);

require_login($course_id, false);

$context = context_course::instance($course_id);

$chat_session = (object)block_ai_assistant_chat_ws::start(
    $course_id,
    $tutorial_id,
    $name,
    $USER->id,
    $chat_id
)[0];



$chat_header = $chat_session->tutorial_name . ': ' . $chat_session->name;

$messages = json_decode(json_decode($chat_session->messages, true), true);
$data = [
    'courseid' => $course_id,
    'botname' => $chat_session->bot_name,
    'tutorialid' => $tutorial_id,
    'chatheader' => $chat_header,
    'chatid' => $chat_session->chat_id,
    'messages' => $messages,
];

$PAGE->set_url(new moodle_url('/blocks/learningassist/chat.php', []));
$PAGE->set_title(get_string('learning_assist_chat', 'block_learningassist'));
$PAGE->set_heading($chat_header);
$PAGE->set_pagelayout('standard');
$PAGE->set_context($context);
$PAGE->requires->js_call_amd('block_ai_assistant/chat', 'sendMessage');
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('block_ai_assistant/chat_interface', $data);

echo $OUTPUT->footer();