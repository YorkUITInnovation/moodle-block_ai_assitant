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
echo  cria::start_session(52, 'o1to-ztDif8ygsXmr6V8D3S5VArGTc48HE6LrbZZv2g','{"idnumber":"123456","firstname": "Patrick"}');



echo $OUTPUT->footer();
