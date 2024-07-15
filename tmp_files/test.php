<?php


use block_ai_assistant\webservice;
use block_ai_assistant\cria;

require_once("../../../config.php");


global $CFG, $OUTPUT, $USER, $PAGE, $DB;

$context = CONTEXT_SYSTEM::instance();

require_login(1, false);


$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/ai_assistant/test.php'));
$PAGE->set_title('Test');
$PAGE->set_heading('Test');
$config = get_config('block_ai_assistant');
// Cria url
$cria_url = $config->cria_url. '/webservice/rest/server.php';
$token = $config->cria_token;

$file_path = '/var/www/moodledata/temp/cria/46/syllabus.docx';
$file_content = file_get_contents($file_path);
$encoded_content = base64_encode($file_content);

echo $OUTPUT->header();
$params = [
    "intentid" => 43,
    "filename" => "syllabus.docx",
    "filecontent" => $encoded_content,
];
print_object($params);

$results = webservice::exec('cria_file_upload', $params);
print_object($results);
echo $OUTPUT->footer();
