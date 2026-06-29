<?php
require_once("../../config.php");

use block_ai_assistant\course_modules;
use block_ai_assistant\chat;

global $CFG, $OUTPUT, $USER, $PAGE, $DB;

require_once("$CFG->dirroot/blocks/ai_assistant/classes/external/chat.php");

/**
 * Normalize tutorial labels for cleaner chat headers.
 */
function block_ai_assistant_normalize_tutorial_label(string $label): string {
    $value = trim($label);
    $map = [
        'Quiz Me ON...' => 'Quiz Coach',
        'My Tutor' => 'Study Tutor',
        'Mon tuteur' => 'Tuteur d\'étude',
        'Quiz sur...' => 'Coach quiz',
    ];
    return $map[$value] ?? $value;
}

$courseid = required_param('courseid', PARAM_INT);
$cmid = required_param('cmid', PARAM_INT);
$tutorialid = optional_param('tutorialid', 0, PARAM_INT);
$name = optional_param('name', '', PARAM_RAW);
$chatid = optional_param('chatid', '', PARAM_RAW);

require_login($courseid, false);

$context = context_course::instance($courseid);

// Get the use picture for the current user.
$user_picture = new \user_picture($USER);
//print_object($user_picture->get_url($PAGE));

$chat_session = (object)block_ai_assistant_chat_ws::start(
    $courseid,
    $tutorialid,
    $cmid,
    $name,
    $USER->id,
    $chatid
)[0];

// Get tutorialchatid
$tutorialchatid = $DB->get_field(
    'block_aia_tutorial_chats',
    'id',
    ['userid' => $USER->id, 'courseid' => $courseid, 'tutorialid' => $tutorialid, 'cmid' => $cmid]
);

$tutorial_label = block_ai_assistant_normalize_tutorial_label((string)($chat_session->tutorial_name ?? 'AI Assistant'));

$messages = json_decode($chat_session->messages, true);
$data = [
    'courseid' => $courseid,
    'botname' => $chat_session->bot_name,
    'tutorialid' => $tutorialid,
    'tutorialchatid' => $tutorialchatid,
    'chatheader' => $tutorial_label,
    'chatid' => $chat_session->chat_id,
    'messages' => $messages,
    'userid' => $USER->id,
    'saved_chats' => chat::get_saved_chats($courseid, $USER->id),
    'profileimageurl' => $user_picture->get_url($PAGE)->out(),
];

// set_context must come before set_title/set_heading in Moodle.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/ai_assitant/chat.php', []));
$PAGE->set_pagelayout('standard');
$chatpagetitle = $tutorial_label . ' · ' . get_string('tutorial_chat_title_suffix', 'block_ai_assistant');
$PAGE->set_title($chatpagetitle);
$PAGE->set_heading($chatpagetitle);
$PAGE->requires->js_call_amd('block_ai_assistant/chat', 'sendMessage');
$PAGE->requires->js_call_amd('block_ai_assistant/chat', 'initChatMenu');
$PAGE->requires->js_call_amd('block_ai_assistant/learning_assistant', 'init');
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('block_ai_assistant/chat_interface', $data);

echo $OUTPUT->footer();