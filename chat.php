<?php
require_once("../../config.php");

use block_ai_assistant\course_modules;
use block_ai_assistant\chat;

global $CFG, $OUTPUT, $USER, $PAGE, $DB;

require_once("$CFG->dirroot/blocks/ai_assistant/classes/external/chat.php");

$courseid = required_param('courseid', PARAM_INT);
$cmid = required_param('cmid', PARAM_INT);
$tutorialid = optional_param('tutorialid', 0, PARAM_INT);
$name = optional_param('name', '', PARAM_RAW);
$chatid = optional_param('chatid', '', PARAM_RAW);

require_login($courseid, false);

$context = context_course::instance($courseid);

$chat_session = (object)block_ai_assistant_chat_ws::start(
    $courseid,
    $tutorialid,
    $cmid,
    $name,
    $USER->id,
    $chatid
)[0];


$chat_header = $chat_session->tutorial_name . ': ' . $chat_session->name;



$messages = json_decode($chat_session->messages, true);
$data = [
    'courseid' => $courseid,
    'botname' => $chat_session->bot_name,
    'tutorialid' => $tutorialid,
    'chatheader' => $chat_header,
    'chatid' => $chat_session->chat_id,
    'messages' => $messages,
    'userid' => $USER->id,
    'saved_chats' => chat::get_saved_chats($courseid, $USER->id),
];

$PAGE->set_url(new moodle_url('/blocks/learningassist/chat.php', []));
$PAGE->set_title(get_string('learning_assist_chat', 'block_learningassist'));
$PAGE->set_heading($chat_header);
$PAGE->set_pagelayout('standard');
$PAGE->set_context($context);
$PAGE->requires->js_call_amd('block_ai_assistant/chat', 'sendMessage');
$PAGE->requires->js_call_amd('block_ai_assistant/chat', 'initChatMenu');
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('block_ai_assistant/chat_interface', $data);

echo $OUTPUT->footer();