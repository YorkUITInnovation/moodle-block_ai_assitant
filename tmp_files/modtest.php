<?php


use block_ai_assistant\webservice;
use block_ai_assistant\cria;

require_once("../../../config.php");


global $CFG, $OUTPUT, $USER, $PAGE, $DB;

$courseid = optional_param('courseid', 32, PARAM_INT);

require_login(1, false);

$context = context_course::instance($courseid);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/ai_assistant/modtest.php'));
$PAGE->set_title('Test');
$PAGE->set_heading('Test');
$config = get_config('block_ai_assistant');
echo $OUTPUT->header();

$course_structure = new stdClass();
// Get accepted modules
$accepted_modules = explode(',', $config->accepted_modules);
// Get course modules
$modules = get_fast_modinfo($courseid);
// Get all sections from $modules and print them.
$sections = $modules->get_section_info_all();
// Loop through sections
$i = 0; // Used to count the number of sections
foreach ($sections as $sectionnum => $section) {
    $course_structure->sections[$i] = new stdClass();
    $course_structure->sections[$i]->sectionnum = $sectionnum;
    $course_structure->sections[$i]->name = $section->name;
//    echo '<h2>' . $section->name . '</h2>';
    $x = 0; // Used to count the number of modules in a section
    if (isset($modules->sections[$section->section])) {
        $section_mods = $modules->sections[$section->section];
        foreach ($section_mods as $cmid) {
            $mod = get_module_from_cmid($cmid);
            if (in_array($mod[1]->modname, $accepted_modules)) {
                $course_structure->sections[$i]->modules[$x] = new stdClass();
                $course_structure->sections[$i]->modules[$x]->name = $mod[0]->name;
                $course_structure->sections[$i]->modules[$x]->intro = strip_tags($mod[0]->intro);
                $course_structure->sections[$i]->modules[$x]->instanceid = $mod[0]->id;
                $course_structure->sections[$i]->modules[$x]->cmid = $mod[1]->id;
                // Prepare the content based on the type of module
                switch ($mod[1]->modname) {
                    case 'page':
                        $course_structure->sections[$i]->modules[$x]->content = $mod[0]->content;
                        break;
                    case 'label':
                        $course_structure->sections[$i]->modules[$x]->content = $mod[0]->intro;
                        break;
                    case 'book':
                        // Must get book content
                        $content = get_book_content($courseid);
                        $course_structure->sections[$i]->modules[$x]->content = $content;
                    
                        break;
                    case 'resource': // File
                        $course_structure->sections[$i]->modules[$x]->content = get_files_from_resource($mod[1]->id);
                        break;
                    case 'folder':
                        // Index the folder files
                        break;
                    case 'glossary':
                        $content = get_glossary_entries($courseid);
                        $course_structure->sections[$i]->modules[$x]->content = $content;
                       
                        break;
                }
//                echo '<h5>' . $mod[0]->name . '</h5>';


                $x++;
//                print_object($mod);
            }
        }
    }
    $i++;
}

print_object($course_structure);
function get_module_from_cmid($cmid)
{
    global $CFG, $DB;
    if (!$cmrec = $DB->get_record_sql("SELECT cm.*, md.name as modname
                               FROM {course_modules} cm,
                                    {modules} md
                               WHERE cm.id = ? AND
                                     md.id = cm.module", array($cmid))) {
        throw new \moodle_exception('invalidcoursemodule');
    } elseif (!$modrec = $DB->get_record($cmrec->modname, array('id' => $cmrec->instance))) {
        throw new \moodle_exception('invalidcoursemodule');
    }
    $modrec->instance = $modrec->id;
    $modrec->cmid = $cmrec->id;
    $cmrec->name = $modrec->name;

    return array($modrec, $cmrec);
}
function get_book_content($courseid)
{
    global $DB;
    $book = $DB->get_record('book', array('course' => $courseid));
    if ($book) {
        $bookid = $book->id;
    }
    $chapters = $DB->get_records('book_chapters', array('bookid' => $bookid));
    $content = '';
    foreach ($chapters as $chapter) {
        $content .= strip_tags($chapter->content);
    }
    return $content;
}
function get_glossary_entries($courseid)
{
    global $DB;
    $glossary = $DB->get_record('glossary', array('course' => $courseid));
    if ($glossary) {
        $glossaryid = $glossary->id;
    }
    $glossary_entries = $DB->get_records('glossary_entries', array('glossaryid' => $glossaryid));
    $glossary=array();

    foreach ($glossary_entries as $entry) {
        $glossary[$entry->concept]=strip_tags($entry->definition);
    }
    return $glossary;
}

function get_files_from_resource($cmid)
{
    global $DB;

    // Fetch the context of the resource
    $context = context_module::instance($cmid);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_resource', 'content');
    $file_info = array();
    // Loop through the files 
    foreach ($files as $file) {
        if (!$file->is_directory()) {
            $file_info[] = array(
                'filename' => $file->get_filename(),
                'filepath' => $file->get_filepath(),
                'filesize' => $file->get_filesize(),
                'fileurl'  => moodle_url::make_pluginfile_url(
                    $context->id,
                    'mod_resource',
                    'content',
                    $file->get_itemid(),
                    $file->get_filepath(),
                    $file->get_filename()
                )->out()
            );
        }
    }

    return $file_info;
}



echo $OUTPUT->footer();
