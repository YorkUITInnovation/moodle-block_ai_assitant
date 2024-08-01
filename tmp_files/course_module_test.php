<?php


use block_ai_assistant\webservice;
use block_ai_assistant\cria;
use block_ai_assistant\course_modules;

require_once("../../../config.php");


global $CFG, $OUTPUT, $USER, $PAGE, $DB;

$courseid = optional_param('courseid', 32, PARAM_INT);

require_login(1, false);

$context = context_course::instance($courseid);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/ai_assistant/modtest.php'));
$PAGE->set_title('Test');
$PAGE->set_heading('Test');
$PAGE->requires->css(new moodle_url('/blocks/ai_assistant/tmp_files/styles.css'));
$PAGE->requires->js_call_amd('block_ai_assistant/select');
$config = get_config('block_ai_assistant');
echo $OUTPUT->header();

$course_modules = (course_modules::get_course_modules($courseid));
// print_object($course_modules);
echo $OUTPUT->render_from_template('block_ai_assistant/course_modules', $course_modules);

//$content = base64_decode('')

echo $OUTPUT->footer();
