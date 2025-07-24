<?php
require_once("../../config.php");

use block_ai_assistant\course_modules;
use block_ai_assistant\cria;

global $CFG, $OUTPUT, $USER, $PAGE, $DB;

$course_id = required_param('courseid', PARAM_INT);
$cmid = optional_param('cmid', PARAM_INT);
$tutorial_id = optional_param('tutorialid', PARAM_TEXT);
$chat_id = optional_param('chatid', '', PARAM_TEXT);

require_login($course_id, false);

$context = context_course::instance($course_id);

// Get tutorial type.
$tutorial = $DB->get_record('block_aia_tutorials', ['id' => $tutorial_id], '*', MUST_EXIST);
$mod = course_modules::get_module_from_cmid($cmid);

// Getting Module content.
$chat_header = $tutorial->name . ': ' . $mod[0]->name;

$bot_name = $DB->get_field('block_aia_settings', 'bot_name', ['courseid' => $course_id]);

// Start a chat session if it doesn't exist.
if (empty($chat_id)) {
    $chat_id = cria::chat_start();
    // Add the chat ID to the database.
    $DB->insert_record('block_aia_tutorial_chats', [
        'courseid' => $course_id,
        'tutorialid' => $tutorial_id,
        'chatid' => $chat_id,
        'userid' => $USER->id,
        'name' => $chat_header,
        'timecreated' => time(),
    ]);
    $initial_prompt = str_replace('[topic]', $mod[0]->name, $tutorial->prompt);
    $messages = [
        [
            'is_human' => false,
            'message' => cria::chat_send($chat_id, $initial_prompt, $bot_name),
        ]
    ];
} else {
    // Get Chat history.
    $chat_history = cria::chat_history($chat_id);
    $messages = [];
    if (isset($chat_history->history)){
        $history = $chat_history->history;
        for ($i = 0; $i < count($history); $i++) {
            if ($i > 1) {
                if ($history[$i]['role'] == 'user') {
                    $is_human = true;
                } else {
                    $is_human = false;
                }
                $messages[] = [
                    'is_human' => $is_human,
                    'message' => $history[$i]['blocks'][0]['text'],
                ];
            }
        }
    } else {
        $chat_id = cria::chat_start();
        // Add the chat ID to the database.
        $DB->insert_record('block_aia_tutorial_chats', [
            'courseid' => $course_id,
            'tutorialid' => $tutorial_id,
            'chatid' => $chat_id,
            'userid' => $USER->id,
            'timecreated' => time(),
        ]);
        $initial_prompt = str_replace('[topic]', $mod[0]->name, $tutorial->prompt);
        $messages = [
            [
                'is_human' => false,
                'message' => cria::chat_send($chat_id, $initial_prompt, $bot_name),
            ]
        ];
    }
}

$data = [
    'courseid' => $course_id,
    'botname' => $bot_name,
    'tutorialid' => $tutorial_id,
    'chatheader' => $chat_header,
    'chatid' => $chat_id,
    'messages' => $messages,
];

$PAGE->set_url(new moodle_url('/blocks/learningassist/chat.php', []));
$PAGE->set_title(get_string('learning_assist_chat', 'block_learningassist'));
$PAGE->set_heading($tutorial->name . ': ' . $mod[0]->name);
$PAGE->set_pagelayout('standard');
$PAGE->set_context($context);
$PAGE->requires->js_call_amd('block_ai_assistant/chat', 'sendMessage');
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('block_ai_assistant/chat_interface', $data);

echo $OUTPUT->footer();