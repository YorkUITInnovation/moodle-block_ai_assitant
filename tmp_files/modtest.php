<?php


use block_ai_assistant\webservice;
use block_ai_assistant\cria;

require_once("../../../config.php");


global $CFG, $OUTPUT, $USER, $PAGE, $DB;

$courseid = optional_param('courseid', 1, PARAM_INT);

require_login(1, false);

$context = context_course::instance($courseid);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/ai_assistant/modtest.php'));
$PAGE->set_title('Test');
$PAGE->set_heading('Test');
$config = get_config('block_ai_assistant');
echo $OUTPUT->header();

$modinfo = get_fast_modinfo($courseid);
// Loop through all sections and list all modules within each section
foreach ($modinfo->get_section_info_all() as $section) {
    echo '<h2>' . $section->name . '</h2>';

}

function get_module_from_cmid($cmid) {
    global $CFG, $DB;
    if (!$cmrec = $DB->get_record_sql("SELECT cm.*, md.name as modname
                               FROM {course_modules} cm,
                                    {modules} md
                               WHERE cm.id = ? AND
                                     md.id = cm.module", array($cmid))){
        throw new \moodle_exception('invalidcoursemodule');
    } elseif (!$modrec =$DB->get_record($cmrec->modname, array('id' => $cmrec->instance))) {
        throw new \moodle_exception('invalidcoursemodule');
    }
    $modrec->instance = $modrec->id;
    $modrec->cmid = $cmrec->id;
    $cmrec->name = $modrec->name;

    return array($modrec, $cmrec);
}



echo $OUTPUT->footer();
