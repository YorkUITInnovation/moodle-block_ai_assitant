<?php


use block_ai_assistant\webservice;
use block_ai_assistant\cria;

require_once("../../../config.php");


global $CFG, $OUTPUT, $USER, $PAGE, $DB;

$context = context_system::instance();

require_login(1, false);


$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/ai_assistant/test.php'));
$PAGE->set_title('Test');
$PAGE->set_heading('Test');
$config = get_config('block_ai_assistant');
echo $OUTPUT->header();
$image_data = cria::get_ai_assistant_logo();

file_put_contents('/var/www/moodledata/temp/ai_assistant_logo.png', base64_decode($image_data->filecontent));
print_object($image_data);

echo $OUTPUT->footer();
