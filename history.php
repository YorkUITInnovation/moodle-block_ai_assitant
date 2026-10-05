<?php
/**
 * Chat history and bulk delete management
 */

require_once("../../config.php");

global $CFG, $OUTPUT, $USER, $PAGE, $DB;

$courseid = required_param('courseid', PARAM_INT);
$context = context_course::instance($courseid);
require_login($courseid);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/ai_assistant/history.php', ['courseid' => $courseid]));
$PAGE->set_pagelayout('standard');
$historytitle = get_string('chat_history_title', 'block_ai_assistant');
$PAGE->set_title($historytitle);
$PAGE->set_heading($historytitle);

// Load bulk delete JavaScript
$PAGE->requires->js_call_amd('block_ai_assistant/chat_history', 'init', [$courseid]);

// Get all saved chats for this user
$chats = $DB->get_records_sql(
    "SELECT tc.id, tc.name, tc.chatid, tc.tutorialid, tc.cmid, tc.timecreated, t.name as tutorial_name
     FROM {block_aia_tutorial_chats} tc
     JOIN {block_aia_tutorials} t ON tc.tutorialid = t.id
     WHERE tc.courseid = ? AND tc.userid = ?
     ORDER BY tc.timecreated DESC",
    [$courseid, $USER->id]
);

$data = [
    'courseid' => $courseid,
    'chats' => array_values($chats),
    'haschats' => count($chats) > 0,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('block_ai_assistant/chat_history_list', $data);
echo $OUTPUT->footer();
