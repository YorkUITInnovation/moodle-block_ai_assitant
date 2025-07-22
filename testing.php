<?php

require_once(__DIR__ . '/../../config.php');
require_once('classes/external/course_modules_ws.php');
global $CFG, $OUTPUT, $USER, $PAGE;

use block_ai_assistant\webservice;
use block_ai_assistant\course_module_training;
use block_ai_assistant\course_modules;
use block_ai_assistant\cria;

$context = context_system::instance();

require_login(1, false);
$PAGE->set_url(new moodle_url('/blocks/learningassist/testing.php', []));
$PAGE->set_title('Testing');
$PAGE->set_heading('Test');
$PAGE->set_pagelayout('standard');
$PAGE->set_context($context);

//$courseid = required_param('courseid', PARAM_INT);

echo $OUTPUT->header();

// get all course modules
$course_modules = course_modules::get_course_modules(1, true);

foreach ($course_modules->sections as $section) {
    if (isset($section->modules)) {
        foreach ($section->modules as $module) {
            // Check to see if module already exists in the block_aia_course_modules table
            if (!$DB->record_exists(
                'block_aia_course_modules',
                [
                    'courseid' => 1,
                    'cmid' => $module->cmid
                ]
            )) {
                // Upload to cria
                $cria_fileid = cria::upload_content_to_bot(
                    1,
                    $module->content->file_name,
                    $module->content->content,
                    'GENERIC'
                );

                if ($cria_fileid > 0) {
                    // Insert record into block_aia_course_modules
                    $record = new stdClass();
                    $record->courseid = 1;
                    $record->cmid = $module->cmid;
                    $record->cria_file_id = $cria_fileid;
                    $record->timecreated = time();
                    $DB->insert_record('block_aia_course_modules', $record);
                }
            }
        }
    }
}
echo $OUTPUT->footer();

