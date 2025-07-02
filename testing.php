<?php

require_once(__DIR__ . '/../../config.php');

global $CFG, $OUTPUT, $USER, $PAGE;

use block_ai_assistant\webservice;

$context = context_system::instance();

require_login(1, false);
$PAGE->set_url(new moodle_url('/blocks/learningassist/testing.php', []));
$PAGE->set_title('Testing');
$PAGE->set_heading('Test');
$PAGE->set_pagelayout('standard');
$PAGE->set_context($context);

echo $OUTPUT->header();

// Get file from mod_resource
$fs = get_file_storage();

$file = $fs->get_file_by_id(22); // Replace with the actual file ID you want to retrieve

$file_path = '/var/www/moodledata/temp/' . $file->get_filename();
$file->copy_content_to($file_path);

$file_type = $file->get_mimetype();

$md_data = json_decode(webservice::exec_convert($file_path, $file_type), true);

file_put_contents('/var/www/moodledata/temp/' . $md_data['filename'] . '.md', $md_data['content']);

echo print_object($md_data);
echo $OUTPUT->footer();