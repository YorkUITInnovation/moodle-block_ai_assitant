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
                $course_structure->sections[$i]->modules[$x]->intro = $mod[0]->intro;
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
                        $content = get_book_content($courseid);
                        $course_structure->sections[$i]->modules[$x]->content = $content;
                        break;
                    case 'resource': // File
                        // Index the file
                        break;
                    case 'folder':
                        // Index the folder files
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
        $content .= $chapter->content;
    }
    return $content;
}

echo $OUTPUT->footer();
