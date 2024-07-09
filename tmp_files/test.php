<?php



use block_ai_assistant\webservice;

require_once("../../../config.php");


global $CFG, $OUTPUT, $USER, $PAGE, $DB;

$context = CONTEXT_SYSTEM::instance();

require_login(1, false);



$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/ai_assistant/test.php'));
$PAGE->set_title('Test');
$PAGE->set_heading('Test');

echo $OUTPUT->header();
$method = 'cria_get_bot_name';
$data = array('bot_id' => 1);
print_object(webservice::exec($method, $data));
echo $OUTPUT->footer();
