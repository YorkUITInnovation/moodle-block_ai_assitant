<?php

require_once("../../config.php");

global $CFG, $OUTPUT, $USER, $PAGE, $DB;

$courseid = optional_param('courseid', 0, PARAM_INT);
if (!$courseid) {
    // Fallback for cases where the request loses query params (e.g., form submits, mislinked entrypoints).
    // If we can infer the course from page/referrer context, use it; otherwise, redirect safely.
    require_login();
    global $COURSE;
    if (!empty($COURSE) && !empty($COURSE->id) && (int)$COURSE->id > 1) {
        $courseid = (int)$COURSE->id;
    } else {
        $referer = isset($_SERVER['HTTP_REFERER']) ? (string)$_SERVER['HTTP_REFERER'] : '';
        if ($referer !== '' && preg_match('/[?&]id=(\d+)/', $referer, $matches)) {
            $courseid = (int)$matches[1];
        }
    }

    if (!$courseid) {
        redirect(
            new moodle_url('/my/'),
            'Missing course context for Gradebook. Open Gradebook from a course page.',
            2,
            \core\output\notification::NOTIFY_WARNING
        );
    } else {
        redirect(new moodle_url('/blocks/ai_assistant/gradebook.php', ['courseid' => $courseid]));
    }
} else {
    require_login($courseid, false);
}

$context = context_course::instance($courseid);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/ai_assistant/gradebook.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('gradebook', 'block_ai_assistant'));
$PAGE->set_heading(get_string('gradebook', 'block_ai_assistant'));
$PAGE->set_pagelayout('standard');

$settings = $DB->get_record('block_aia_settings', ['courseid' => $courseid]);
$botname = $settings ? (string)($settings->bot_name ?? '') : '';

$user_picture = new \user_picture($USER);

$data = [
    'courseid' => $courseid,
    'userid' => $USER->id,
    'botname' => $botname,
    'profileimageurl' => $user_picture->get_url($PAGE)->out(),
];

$PAGE->requires->js_call_amd('block_ai_assistant/gradebook', 'init');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('block_ai_assistant/gradebook_chat', $data);
echo $OUTPUT->footer();

