<?php

require_once("../../config.php");

global $CFG, $DB, $OUTPUT, $USER, $PAGE;



$courseid = required_param('courseid', PARAM_INT);

$context = context_course::instance($courseid);

require_login($courseid, false);

$PAGE->set_url(new moodle_url('/blocks/ai_assistant/instructor_user_guide.php', []));
$PAGE->set_title(get_string('user_guide', 'block_ai_assistant'));
$PAGE->set_heading('');
$PAGE->set_pagelayout('standard');
$PAGE->set_context($context);

$user_guide = file_get_contents($CFG->wwwroot . '/blocks/ai_assistant/doc/instructor_user_guide.md');
echo $OUTPUT->header();
echo markdown_to_html($user_guide, [
    'context' => $context,
    'filter' => true,
    'trusted' => true,
]);


echo $OUTPUT->footer();
