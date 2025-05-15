<?php

namespace block_ai_assistant;

use block_ai_assistant\cria;

class course_modules
{
    /**
     * Get course modules
     * @param $course_id
     * @return stdClass
     */
    public static function get_course_modules($courseid)
    {
        global $DB, $OUTPUT;
        $config = get_config('block_ai_assistant');
        $course_structure = new \stdClass();
        // Get accepted modules
        $accepted_modules = explode(',', $config->accepted_modules);
        // Get course modules
        $modules = get_fast_modinfo($courseid);
        // Get all sections from $modules and print them.
        $sections = $modules->get_section_info_all();
        // Loop through sections
        $i = 0; // Used to count the number of sections
        foreach ($sections as $sectionnum => $section) {
            // Set number of modules to 0
            $number_of_modules_in_section = 0;
            $course_structure->sections[$i] = new \stdClass();
            $course_structure->sections[$i]->courseid = $courseid;
            $course_structure->sections[$i]->sectionnum = $sectionnum;
            if ($sectionnum == 0) {
                $course_structure->sections[$i]->name = get_string('general');
            } else {
                if (isset($section->name)) {
                    $course_structure->sections[$i]->name = $section->name;
                } else {
                    $course_structure->sections[$i]->name = 'Topic ' . $sectionnum;
                }
            }
            if (isset($section->name)) {
                $course_structure->sections[$i]->idname = $sectionnum . '-' . strtolower(str_replace(' ', '-', $course_structure->sections[$i]->name));
            } else {
                $course_structure->sections[$i]->idname = $sectionnum . '-' . strtolower(str_replace(' ', '-', $course_structure->sections[$i]->name));
            }
            $x = 0; // Used to count the number of modules in a section
            if (isset($modules->sections[$section->section])) {
                $section_mods = $modules->sections[$section->section];
                foreach ($section_mods as $cmid) {
                    $mod = self::get_module_from_cmid($cmid);
                    // Only get the modules that are accepted
                    if (in_array($mod[1]->modname, $accepted_modules)) {
                        $number_of_modules_in_section++;
                        $course_structure->sections[$i]->modules[$x] = new \stdClass();
                        $course_structure->sections[$i]->modules[$x]->name = $mod[0]->name;
                        $course_structure->sections[$i]->modules[$x]->intro = strip_tags($mod[0]->intro);
                        $course_structure->sections[$i]->modules[$x]->instanceid = $mod[0]->id;
                        $course_structure->sections[$i]->modules[$x]->cmid = $mod[1]->id;
                        $course_structure->sections[$i]->modules[$x]->modname = $mod[1]->modname;
                        // Is this module trained?
                        if ($ai_assistant_module = $DB->get_record('block_aia_course_modules', ['cmid' => $mod[1]->id])) {
                            if ($ai_assistant_module->trained != 1) {
                                $data = cria::get_content_training_status($ai_assistant_module->cria_fileid);
                                $ai_assistant_module->trained = $data->training_status_id;
                                $DB->set_field('block_aia_course_modules',
                                    'trained',
                                    $ai_assistant_module->trained,
                                    ['id' => $ai_assistant_module->id]
                                );

                            }
                            switch ($ai_assistant_module->trained) {
                                case 0:
                                    $course_structure->sections[$i]->modules[$x]->trained = '<span class="badge badge-warning">'
                                        . get_string('pending', 'block_ai_assistant')
                                        . '</span>';
                                    break;
                                case 1:
                                    $course_structure->sections[$i]->modules[$x]->trained = '<span class="badge badge-success">'
                                        . get_string('trained', 'block_ai_assistant')
                                        . '</span>';;
                                    break;
                                case 2:
                                    $course_structure->sections[$i]->modules[$x]->trained = '<span class="badge badge-danger">'
                                        . get_string('error', 'block_ai_assistant')
                                        . '</span>';
                                    break;
                                case 3:
                                    $course_structure->sections[$i]->modules[$x]->trained = '<span class="badge badge-info">'
                                        . get_string('training', 'block_ai_assistant')
                                        . '</span>';
                                    break;
                            }
                            $course_structure->sections[$i]->modules[$x]->cria_fileid = $ai_assistant_module->cria_fileid;
                            $course_structure->sections[$i]->modules[$x]->block_aia_course_moduel_id = $ai_assistant_module->id;
                        } else {
                            $course_structure->sections[$i]->modules[$x]->trained = false;
                        }
                        // Get module pix
                        // Prepare the content based on the type of module
                        switch ($mod[1]->modname) {
                            case 'forum':
                                // Only print if it's the news forum
                                if ($mod[0]->type == 'news') {
                                    $content = self::get_forum_content($mod[0]->id, $mod[0]->name);
                                    $course_structure->sections[$i]->modules[$x]->content = self::set_module_content(
                                        $mod[0]->id,
                                        $mod[0]->name,
                                        $mod[0]->intro,
                                        $content,
                                        $mod[1]->modname
                                    );
                                    $course_structure->sections[$i]->modules[$x]->icon = $OUTPUT->image_url('monologo', 'forum');
                                    $course_structure->sections[$i]->modules[$x]->icontype = 'collaboration ';
                                }
                                break;
                            case 'page':
                                $course_structure->sections[$i]->modules[$x]->content = self::set_module_content(
                                    $mod[0]->id,
                                    $mod[0]->name,
                                    $mod[0]->intro,
                                    $mod[0]->content,
                                    $mod[1]->modname
                                );
                                $course_structure->sections[$i]->modules[$x]->icon = $OUTPUT->image_url('monologo', 'page');
                                $course_structure->sections[$i]->modules[$x]->icontype = 'content';
                                break;
                            case 'label':
                                $course_structure->sections[$i]->modules[$x]->content = self::set_module_content(
                                    $mod[0]->id,
                                    $mod[0]->name,
                                    $mod[0]->intro,
                                    '',
                                    $mod[1]->modname
                                );
                                $course_structure->sections[$i]->modules[$x]->icon = $OUTPUT->image_url('monologo', 'label');
                                $course_structure->sections[$i]->modules[$x]->icontype = 'content';
                                break;
                            case 'book':
                                // Must get book content
                                $content = self::get_book_content($mod[0]->id);
                                $course_structure->sections[$i]->modules[$x]->content = self::set_module_content(
                                    $mod[0]->id,
                                    $mod[0]->name,
                                    $mod[0]->intro,
                                    $content,
                                    $mod[1]->modname
                                );
                                $course_structure->sections[$i]->modules[$x]->icon = $OUTPUT->image_url('monologo', 'book');
                                $course_structure->sections[$i]->modules[$x]->icontype = 'content';
                                break;
                            case 'resource': // File
                                $course_structure->sections[$i]->modules[$x]->content = self::get_files_from_resource(
                                    $mod[1]->id,
                                    $mod[0]->id
                                );
                                $course_structure->sections[$i]->modules[$x]->icon = $OUTPUT->image_url('monologo', 'resource');
                                $course_structure->sections[$i]->modules[$x]->icontype = 'content';
                                break;
                            case 'folder':
                                $folder_files = self::get_folder_files(
                                    $mod[1]->id,
                                    $mod[0]->id,
                                    $mod[0]->name,
                                    $mod[0]->intro
                                );
                                $course_structure->sections[$i]->modules[$x]->content = $folder_files->content;
                                $course_structure->sections[$i]->modules[$x]->files = $folder_files->files;
                                $course_structure->sections[$i]->modules[$x]->icon = $OUTPUT->image_url('monologo', 'folder');
                                $course_structure->sections[$i]->modules[$x]->icontype = 'content';
                                break;
                            case 'glossary':
                                $content = self::get_glossary_entries($mod[1]->id, $mod[0]->id, $mod[0]->name);
                                $course_structure->sections[$i]->modules[$x]->content = self::set_module_content(
                                    $mod[0]->id,
                                    $mod[0]->name,
                                    $mod[0]->intro,
                                    $content->content,
                                    $mod[1]->modname
                                );
                                $course_structure->sections[$i]->modules[$x]->files = $content->files;
                                $course_structure->sections[$i]->modules[$x]->icon = $OUTPUT->image_url('monologo', 'glossary');
                                $course_structure->sections[$i]->modules[$x]->icontype = 'collaboration';

                                break;
                        }
                        $x++;
                    }
                }
            }
            $course_structure->sections[$i]->number_of_modules = $number_of_modules_in_section;
            $i++;
        }
        return $course_structure;
    }

    /**
     * Get module from course module id
     * @param $cmid
     * @return array
     * @throws \moodle_exception
     */
    public static function get_module_from_cmid($cmid)
    {
        global $DB;
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

    /**
     * Set module content
     * @param $id
     * @param $name
     * @param $intro
     * @param $content
     * @param $module_type
     * @return stdClass
     */
    public static function set_module_content($id, $name, $intro, $content, $module_type)
    {
        //Create object
        $module = new \stdClass();
        // Set default variable values
        $file_name = '';
        $module_content = '';
        if (isset($name)) {
            // Set the file name
            $file_name = $module_type . ' ' . $id . ' ' . substr($name, 0, 30) . '.html';
        }
        // Set the content
        if (isset($intro) ) {
            $module_content .= $intro;
        }
        if (isset($content)) {
            $module_content .=  '<br><br>' . $content;
        }

        $module->file_name = $file_name;
        // Make sure $module_content is UTF-8 encoded
        if (!mb_detect_encoding($module_content, 'UTF-8', true)) {
            $module_content = mb_convert_encoding($module_content, 'UTF-8');
        }
        $module->content = base64_encode($module_content);

        return $module;
    }

    /**
     * Get book content
     * @param $cmid
     * @return string
     */
    public static function get_book_content($book_id)
    {
        global $CFG, $DB;

//    $content = file_get_contents($CFG->wwwroot . '/mod/book/tool/print/index.php?id=' . $cmid);
        $chapters = $DB->get_records('book_chapters', array('bookid' => $book_id));
        $content = '';
        foreach ($chapters as $chapter) {
            $content .= $chapter->content;
        }
        return $content;
    }

    /**
     * Get glossary entries
     * @param $cmid
     * @param $id
     * @return stdClass
     */
    public static function get_glossary_entries($cmid, $id, $name)
    {
        global $CFG, $DB;

        $context = \context_module::instance($cmid);

        $glossary_entries = $DB->get_records('glossary_entries', array('glossaryid' => $id));
        $glossary = new \stdClass();
        // We will need to get files from the glossary entries
        $fs = get_file_storage();
        $html = '';
        foreach ($glossary_entries as $entry) {
            $html .= '<h3>' . $entry->concept . '</h3>';
            $html .= $entry->definition;
            // Get files for this entry
            $files = $fs->get_area_files($context->id, 'mod_glossary', 'attachment', $entry->id);
            $glossary_files = [];
            $i = 0;
            foreach ($files as $file) {
                if (!$file->is_directory()) {
                    $path = $CFG->dataroot . '/temp/ai_assistant/';
                    if (!file_exists($path)) {
                        mkdir($path, 0777, true);
                    }
                    $path .= 'glossary/';
                    if (!file_exists($path)) {
                        mkdir($path, 0777, true);
                    }
                    $path .= $entry->id . '/';
                    if (!file_exists($path)) {
                        mkdir($path, 0777, true);
                    }
                    // Set the file name
                    $file_name = str_replace(' ', '_', $file->get_filename());
                    // Save a copy of the file
                    $file->copy_content_to($path . $file_name);
                    // Get the content of the file
                    $content = file_get_contents($path . $file_name);
                    $glossary_files[$i] = new \stdClass();
                    $glossary_files[$i]->content = base64_encode($content);
                    $glossary_files[$i]->file_name = $file->get_filename();
                    $glossary_files[$i]->mime_type = $file->get_mimetype();
                    // Delete the file
                    unlink($path . $file_name);
                    $i++;
                }
            }
        }
        $glossary->file_name = 'glossary ' . $id . ' ' . str_replace(' ', '_', $name) . '.html';
        $glossary->content = $html;
        $glossary->files = $glossary_files;

        // If the glossary files are empty, set it to null
        if (empty($glossary_files)) {
            $glossary->files = new \stdClass() ;
        }

        return $glossary;
    }

    public static function get_forum_content($id, $name)
    {
        global $DB;
        // Get forum discussions
        $forum_discussions = $DB->get_records('forum_discussions', array('forum' => $id));
        $html = '';
        foreach ($forum_discussions as $fd) {
            // Get forum posts
            $forum_posts = $DB->get_records('forum_posts', array('discussion' => $fd->id));
            foreach($forum_posts as $fp) {
                $html .= '<h3>' . $fp->subject . '</h3>';
                $html .= $fp->message . "\n";
            }
        }

        return $html;
    }

    /**
     * Get files from resource
     * @param $cmid
     * @return array
     */
    public static function get_files_from_resource($cmid, $id)
    {
        global $CFG;

        // Fetch the context of the resource
        $context = \context_module::instance($cmid);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_resource', 'content');
        $file_info = new \stdClass();

        // Loop through the files
        foreach ($files as $file) {
            if (!$file->is_directory() && $file->get_sortorder() == 1) {
                $path = $CFG->dataroot . '/temp/ai_assistant/';
                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }
                $path .= 'resource/';
                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }
                $path .= $id . '/';
                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }

                // Set the file name
                $file_name = $file->get_filename();
                // Save a copy of the file
                $file->copy_content_to($path . $file_name);
                // Get the content of the file
                $content = file_get_contents($path . $file_name);
                $file_info->content = base64_encode($content);
                $file_info->file_name = 'resource ' . $id . ' ' . $file_name;
            }
        }
        // If no files are available, set the content to empty
        if (empty($file_info)) {
            $file_info->file_name = '';
            $file_info->content = '';
        }

        return $file_info;
    }

    /**
     * Get folder files
     * @param $cmid
     * @param $id
     * @param $name
     * @param $intro
     * @return stdClass
     */
    public static function get_folder_files($cmid, $id, $name, $intro)
    {
        global $CFG, $DB;

        $context = \context_module::instance($cmid);
        $folder_data = new \stdClass();
        // We will need to get files from the glossary entries
        $fs = get_file_storage();
        $html = '<h3>' . $name . '</h3>';
        $html .= $intro;
        $folder_data->content = new \stdClass();
        $folder_data->content->file_name = 'folder ' . $id . ' ' . $name . '.html';
        $folder_data->content->content = base64_encode($html);


        // Get files for this entry
        $files = $fs->get_area_files($context->id, 'mod_folder', 'content', 0);
        $folder_files = [];
        $i = 0;
        foreach ($files as $file) {
            // Must ignore excel files as we don't know how to parse them yet
            if (!$file->is_directory() &&
                $file->get_mimetype() != 'application/vnd.ms-excel' &&
                $file->get_mimetype() != 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ) {
                $path = $CFG->dataroot . '/temp/ai_assistant/';
                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }
                $path .= 'folder/';
                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }
                $path .= $id . '/';
                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }
                // Set the file name
                $file_name = $file->get_filename();
                // Save a copy of the file
                $file->copy_content_to($path . $file_name);
                // Get the content of the file
                $content = file_get_contents($path . $file_name);
                $folder_files[$i] = new \stdClass();
                $folder_files[$i]->content = base64_encode($content);
                $folder_files[$i]->file_name = $file->get_filename();
                $folder_files[$i]->mime_type = $file->get_mimetype();
                // Delete the file
                unlink($path . $file_name);
                $i++;
            }
        }

        if (empty($folder_files)) {
            $folder_data->content = new \stdClass();
        }

        $folder_data->files = $folder_files;

        return $folder_data;
    }

    /**
     * Insert record
     * @param stdClass $data $courseid, $cmid, $modname
     * @return int
     */
    public static function insert_record($data)
    {
        global $DB, $USER;

        // Check if the record already exists
        if (!$existing_record = $DB->get_record('block_aia_course_modules', array(
                'courseid' => $data->courseid,
                'cmid' => $data->cmid
            )
        )) {
            // Add required fields
            $data->usermodified = $USER->id;
            $data->timecreated = time();
            $data->timemodified = time();

            // Insert the new record
            return $DB->insert_record('block_aia_course_modules', $data);
        }

        return 0;
    }

    /**
     * Update a record. Should be triggered when a course module event is updated
     * @param $data $id, $courseid, $cmid, $modname
     * @return bool
     * @throws \dml_exception
     */
    public static function update_record($data)
    {
        global $DB, $USER;
        $block_cm_info = $DB->get_record('block_ai_assistant_course_modules', ['id' => $data->id]);
        // Delete the old file on cria
        $delete_result = cria::delete_content_from_bot($block_cm_info->cria_fileid);
        if ($delete_result != 200) {
            return false;
        }
        // add the file to cria base on modname
        switch ($block_cm_info->modname) {
            case 'page':
                $sql = "SELECT page.* FROM {page} page 
                INNER JOIN {course_modules} cm ON cm.instanceid = page.id WHERE cm.id = ?";
                $page = $DB->get_record_sql($sql, [$block_cm_info->cmid]);
                $module_content = self::set_module_content(
                    $block_cm_info->cmid,
                    $page->name,
                    $page->intro,
                    $page->content,
                    $block_cm_info->modname
                );
                $data->cria_fileid = cria::upload_content_to_bot(
                    $block_cm_info->courseid,
                    $module_content->file_name,
                    $module_content->content,
                    'GENERIC'
                );
                break;


        }
        // Add required fields
        $data->usermodified = $USER->id;
        $data->timemodified = time();

        return $DB->update_record('block_ai_assistant_course_modules', $data);
    }

    /**
     * Get the availability text for the module
     * @param $name
     * @param $availability
     * @return void
     */
    private static function get_availability_content($name, $availability)
    {
        $availability = json_decode($availability);

        $content = '';
        $i = 0;
        foreach($availability->c as $c) {
            if (trim($c->type) == 'date') {
                $date_operator = $c->d;
                $date = $c->t;
                $date_show = $availability->showc[$i];
                if ($date_show == true) {
                    $date_show_text = 'will be available';
                } else {
                    $date_show_text = 'will not be available';
                }
                switch ($date_operator) {
                    case "<":
                        $content .= 'The assessment, assignment, activity, resource called ' . $name . ' ' . $date_show_text . ' before ' . date('Y-m-d H:i:s', $date) . "<br>\n";
                        break;
                    case ">":
                        $content .= 'The assessment, assignment, activity, resource called ' . $name . ' ' . $date_show_text . ' after ' . date('Y-m-d H:i:s', $date) . "<br>\n";
                        break;
                    case "=":
                        $content .= 'The assessment, assignment, activity, resource called ' . $name . ' ' . $date_show_text . ' on ' . date('Y-m-d H:i:s', $date) . "<br>\n";
                        break;
                    case ">=":
                        $content .= 'The assessment, assignment, activity, resource called ' . $name . ' ' . $date_show_text . ' on or after ' . date('Y-m-d H:i:s', $date) . "<br>\n";
                        break;
                    case "<=":
                        $content .= 'The assessment or assignment called ' . $name . ' ' . $date_show_text . ' on or before ' . date('Y-m-d H:i:s', $date) . "<br>\n";
                        break;
                }
            }

        }
        return $content;
    }

    /**
     * Get course modules
     * @param $course_id
     * @return stdClass
     */
    public static function upload_course_dates($courseid)
    {
        global $DB, $CFG;
        $config = get_config('block_ai_assistant');
        $course_structure = new \stdClass();
        // Get course modules
        $modules = get_fast_modinfo($courseid);
        // Get all sections from $modules and print them.
        $sections = $modules->get_section_info_all();
        // Loop through sections
        $i = 0; // Used to count the number of sections
        $title = '<h1>Assessment, Assignment and Activity dates<h1>' . "\n";
        // if folder $CFG->dataroot . '/temp/ai_assistant/' . $courseid . '/' does not exist, create it
        $path = $CFG->dataroot . '/temp/ai_assistant/' . $courseid . '/';
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        // Delete cria file based on cria_assignment_file_id if there is a value
        $cria_assignment_file_id = $DB->get_field('block_aia_settings', 'cria_assignment_file_id', ['courseid' => $courseid]);
        if ($cria_assignment_file_id != 0) {
            $delete_result = cria::delete_content_from_bot($cria_assignment_file_id);

            // Set cria_assignment_file_id to null
            $DB->set_field('block_aia_settings', 'cria_assignment_file_id', 0, ['courseid' => $courseid]);
        }

        $file_name = $path . $courseid . '_assessment_dates.html';
        // If the file based on file_name exists, delete it first
        if (file_exists($file_name)) {
            unlink($file_name);
        }
        file_put_contents($file_name, $title);
        foreach ($sections as $sectionnum => $section) {
            $x = 0; // Used to count the number of modules in a section
            if (isset($modules->sections[$section->section])) {
                $section_mods = $modules->sections[$section->section];
                foreach ($section_mods as $cmid) {
                    $mod = self::get_module_from_cmid($cmid);
                    $content = '';
                    // Get the data for the module
                    switch ($mod[1]->modname) {
                        case 'assign':
                            $sql = "SELECT assign.*, cm.availability FROM {assign} assign 
                                        Inner Join {course_modules} cm ON cm.instance = assign.id WHERE cm.id = ?";
                            $data = $DB->get_record_sql($sql, [$mod[1]->id]);
                            // Build the content
                            $content .= '<h3>' . $data->name . '</h3>' . "\n";
                            if ($data->allowsubmissionsfromdate) {
                                $content .= 'You can submit your submission for the assessment, assignment, activity called ' . $data->name . ' on ' . date('Y-m-d H:i:s', $data->allowsubmissionsfromdate) . "<br>\n";
                            }
                            if ($data->duedate) {
                                $content .= 'The assessment, assignment, activity called ' . $data->name . ' is due on ' . date('Y-m-d H:i:s', $data->duedate) . "<br>\n";
                            }
                            if ($data->cutoffdate) {
                                $content .= 'The assessment, assignment, activitycalled ' . $data->name . ' will be closed on ' . date('Y-m-d H:i:s', $data->cutoffdate) . "<br>\n";
                            }
                            if ($data->availability) {
                                $content .= self::get_availability_content($data->name, $data->availability);
                            }
                            break;
                        case 'quiz':
                            $sql = "SELECT quiz.*, cm.availability FROM {quiz} quiz 
                                        Inner Join {course_modules} cm ON cm.instance = quiz.id WHERE cm.id = ?";
                            $data = $DB->get_record_sql($sql, [$mod[1]->id]);
                            // Build the content
                            $content .= '<h3>' . $data->name . '</h3>' . "\n";
                            if ($data->timeopen) {
                                $content .= 'You can start the assessment, assignment, activity, quiz called ' . $data->name . ' on ' . date('Y-m-d H:i:s', $data->timeopen) . "<br>\n";
                            }
                            if ($data->timeclose) {
                                $content .= 'The assessment, assignment, activity, quiz called ' . $data->name . ' will be closed on ' . date('Y-m-d H:i:s', $data->timeclose) . "<br>\n";
                            }
                            if ($data->timelimit) {
                                $content .= 'You have ' . $data->timelimit . ' minutes to complete the assessment, assignment, activity, quiz called ' . $data->name . "<br>\n";
                            }
                            if ($data->availability) {
                                $content .= self::get_availability_content($data->name, $data->availability);
                            }
                            break;
                        case 'forum':
                            $sql = "SELECT forum.*, cm.availability FROM {forum} forum 
                                        Inner Join {course_modules} cm ON cm.instance = forum.id WHERE cm.id = ?";
                            $data = $DB->get_record_sql($sql, [$mod[1]->id]);
                            // Build the content
                            $content .= '<h3>' . $data->name . '</h3>' . "\n";
                            if ($data->duedate) {
                                $content .= 'The assessment, assignment, activity, forum called ' . $data->name . ' is due on ' . date('Y-m-d H:i:s', $data->duedate) . "<br>\n";
                            }
                            if ($data->cutoffdate) {
                                $content .= 'The assessment, assignment, activity, forum called ' . $data->name . ' will be closed on ' . date('Y-m-d H:i:s', $data->cutoffdate) . "<br>\n";
                            }
                            if ($data->assesstimestart) {
                                $content .= 'You can start the assessment, assignment, activity, forum called ' . $data->name . ' on ' . date('Y-m-d H:i:s', $data->assessmenttimestart) . "<br>\n";
                            }
                            if ($data->assesstimefinish) {
                                $content .= 'The assessment, assignment, activity, forum called ' . $data->name . ' will close on ' . date('Y-m-d H:i:s', $data->assesstimefinish) . "<br>\n";
                            }
                            if ($data->availability) {
                                $content .= self::get_availability_content($data->name, $data->availability);
                            }
                            // Get the discussions for the forum
                            if ($discussions = $DB->get_records('forum_discussions', ['forum' => $data->id])) {
                                foreach ($discussions as $discussion) {
                                    $content .= '<h4>' . $discussion->name . '</h4>';
                                    if ($discussion->timestart) {
                                        $content .= 'You can start the discussion, assessment, assignment, activity called ' . $discussion->name . ' for forum called ' . $data->name . ' on ' . date('Y-m-d H:i:s', $discussion->timestart) . "<br>\n";
                                    }
                                    if ($discussion->timeend) {
                                        $content .= 'The discussion, assessment, assignment, activity called ' . $discussion->name . ' for forum called ' . $data->name . ' will be closed on ' . date('Y-m-d H:i:s', $discussion->timeend) . "<br>\n";
                                    }
                                }
                            }
                            break;
                        case 'bigbluebuttonbn':
                            $sql = "SELECT bigbluebuttonbn.*, cm.availability FROM {bigbluebuttonbn} bigbluebuttonbn 
                                        Inner Join {course_modules} cm ON cm.instance = bigbluebuttonbn.id WHERE cm.id = ?";
                            $data = $DB->get_record_sql($sql, [$mod[1]->id]);
                            // Build the content
                            $content .= '<h3>' . $data->name . '</h3>' . "\n";
                            if ($data->openingtime) {
                                $content .= 'The assessment, assignment, activity, meeting called ' . $data->name . ' will open or be made available on ' . date('Y-m-d H:i:s', $data->openingtime) . "<br>\n";
                            }
                            if ($data->closingtime) {
                                $content .= 'The assessment, assignment, activity, meeting called ' . $data->name . ' will close or end on ' . date('Y-m-d H:i:s', $data->closingtime) . "<br>\n";
                            }
                            if ($data->availability) {
                                $content .= self::get_availability_content($data->name, $data->availability);
                            }
                            break;
                        case 'chat':
                            $sql = "SELECT chat.*, cm.availability FROM {chat} chat 
                                        Inner Join {course_modules} cm ON cm.instance = chat.id WHERE cm.id = ?";
                            $data = $DB->get_record_sql($sql, [$mod[1]->id]);
                            // Build the content
                            $content .= '<h3>' . $data->name . '</h3>' . "\n";
                            if ($data->chattime) {
                                $content .= 'The assessment, assignment, activity, chat session called ' . $data->name . ' will start or open on ' . date('Y-m-d H:i:s', $data->chattime) . "<br>\n";
                            }
                            if ($data->availability) {
                                $content .= self::get_availability_content($data->name, $data->availability);
                            }
                            break;
                        case 'choice':
                            $sql = "SELECT choice.*, cm.availability FROM {choice} choice 
                                        Inner Join {course_modules} cm ON cm.instance = choice.id WHERE cm.id = ?";
                            $data = $DB->get_record_sql($sql, [$mod[1]->id]);
                            // Build the content
                            $content .= '<h3>' . $data->name . '</h3>' . "\n";
                            if ($data->timeopen) {
                                $content .= 'You can start the choice assessment, assignment, activity called ' . $data->name . ' on ' . date('Y-m-d H:i:s', $data->timeopen) . "<br>\n";
                            }
                            if ($data->timeclose) {
                                $content .= 'The choice activity called ' . $data->name . ' will close on ' . date('Y-m-d H:i:s', $data->timeclose) . "<br>\n";
                            }
                            if ($data->availability) {
                                $content .= self::get_availability_content($data->name, $data->availability);
                            }
                            break;
                        case 'data':
                            $sql = "SELECT data.*, cm.availability FROM {data} data 
                                        Inner Join {course_modules} cm ON cm.instance = data.id WHERE cm.id = ?";
                            $data = $DB->get_record_sql($sql, [$mod[1]->id]);
                            // Build the content
                            $content .= '<h3>' . $data->name . '</h3>' . "\n";
                            if ($data->timeavailablefrom) {
                                $content .= 'The data or database assessment, assignment, activity called ' . $data->name . ' will be available from ' . date('Y-m-d H:i:s', $data->timeavailablefrom) . "<br>\n";
                            }
                            if ($data->timeavailableto) {
                                $content .= 'The data or database assessment, assignment, activity called ' . $data->name . ' will be available to ' . date('Y-m-d H:i:s', $data->timeavailableto) . "<br>\n";
                            }
                            if ($data->timeviewfrom) {
                                $content .= 'The data or database assessment, assignment, activity called ' . $data->name . ' will be viewable from ' . date('Y-m-d H:i:s', $data->timeviewfrom) . "<br>\n";
                            }
                            if ($data->timeviewto) {
                                $content .= 'The data or database assessment, assignment, activity called ' . $data->name . ' will be viewable to ' . date('Y-m-d H:i:s', $data->timeviewto) . "<br>\n";
                            }
                            if ($data->availability) {
                                $content .= self::get_availability_content($data->name, $data->availability);
                            }
                            break;
                        case 'feedback':
                            $sql = "SELECT feedback.*, cm.availability FROM {feedback} feedback 
                                        Inner Join {course_modules} cm ON cm.instance = feedback.id WHERE cm.id = ?";
                            $data = $DB->get_record_sql($sql, [$mod[1]->id]);
                            // Build the content
                            $content .= '<h3>' . $data->name . '</h3>';
                            if ($data->open) {
                                $content .= 'The feedback assessment, assignment, activity called ' . $data->name . ' will open on ' . date('Y-m-d H:i:s', $data->open) . "<br>\n";
                            }
                            if ($data->close) {
                                $content .= 'The feedback assessment, assignment, activity called ' . $data->name . ' will close on ' . date('Y-m-d H:i:s', $data->close) . "<br>\n";
                            }
                            if ($data->availability) {
                                $content .= self::get_availability_content($data->name, $data->availability);
                            }
                            break;
                        case 'glossary':
                            $sql = "SELECT glossary.*, cm.availability FROM {glossary} glossary 
                                        Inner Join {course_modules} cm ON cm.instance = glossary.id WHERE cm.id = ?";
                            $data = $DB->get_record_sql($sql, [$mod[1]->id]);
                            // Build the content
                            $content .= '<h3>' . $data->name . '</h3>';
                            if ($data->availability) {
                                $content .= self::get_availability_content($data->name, $data->availability);
                            }
                            if ($data->assesstimestart) {
                                $content .= 'You can start glossary assessment, assignment, activity called ' . $data->name . ' on ' . date('Y-m-d H:i:s', $data->assesstimestart) . "<br>\n";
                            }
                            if ($data->assesstimefinish) {
                                $content .= 'The glossary assessment, assignment, activity called ' . $data->name . ' will finish on ' . date('Y-m-d H:i:s', $data->assesstimefinish) . "<br>\n";
                            }
                            break;
                        case 'h5pactivity':
                            $sql = "SELECT h5pactivity.*, cm.availability FROM {h5pactivity} h5pactivity 
                                        Inner Join {course_modules} cm ON cm.instance = h5pactivity.id WHERE cm.id = ?";
                            $data = $DB->get_record_sql($sql, [$mod[1]->id]);
                            $content .= '<h3>' . $data->name . '</h3>' . "\n";
                            // Build the content
                            $content .= '<h3>' . $data->name . '</h3>';
                            if ($data->availability) {
                                $content .= self::get_availability_content($data->name, $data->availability);
                            }
                            break;
                        case 'lesson':
                            $sql = "SELECT lesson.*, cm.availability FROM {lesson} lesson 
                                        Inner Join {course_modules} cm ON cm.instance = lesson.id WHERE cm.id = ?";
                            $data = $DB->get_record_sql($sql, [$mod[1]->id]);
                            $content .= '<h3>' . $data->name . '</h3>' . "\n";
                            if ($data->availability) {
                                $content .= self::get_availability_content($data->name, $data->availability);
                            }
                            if ($data->available) {
                                $content .= 'The lesson assessment, assignment, activity called ' . $data->name . ' will be available on ' . date('Y-m-d H:i:s', $data->available) . "<br>\n";
                            }
                            if ($data->deadline) {
                                $content .= 'The lesson assessment, assignment, activity called ' . $data->name . ' will be closed on ' . date('Y-m-d H:i:s', $data->deadline) . "<br>\n";
                            }
                            break;
                        case 'lti':
                            $sql = "SELECT lti.*, cm.availability FROM {lti} lti 
                                        Inner Join {course_modules} cm ON cm.instance = lti.id WHERE cm.id = ?";
                            $content .= '<h3>' . $data->name . '</h3>' . "\n";
                            if ($data->availability) {
                                $content .= self::get_availability_content($data->name, $data->availability);
                            }
                            break;
                        case 'scorm':
                            $sql = "SELECT scorm.*, cm.availability FROM {scorm} scorm 
                                        Inner Join {course_modules} cm ON cm.instance = scorm.id WHERE cm.id = ?";
                            $data = $DB->get_record_sql($sql, [$mod[1]->id]);
                            $content .= '<h3>' . $data->name . '</h3>' . "\n";
                            if ($data->availability) {
                                $content .= self::get_availability_content($data->name, $data->availability);
                            }
                            break;
                        case 'survey':
                            $sql = "SELECT survey.*, cm.availability FROM {survey} survey 
                                        Inner Join {course_modules} cm ON cm.instance = survey.id WHERE cm.id = ?";
                            $data = $DB->get_record_sql($sql, [$mod[1]->id]);
                            $content .= '<h3>' . $data->name . '</h3>' . "\n";
                            if ($data->availability) {
                                $content .= self::get_availability_content($data->name, $data->availability);
                            }
                            break;
                        case 'wiki':
                            $sql = "SELECT wiki.*, cm.availability FROM {wiki} wiki 
                                        Inner Join {course_modules} cm ON cm.instance = wiki.id WHERE cm.id = ?";
                            $data = $DB->get_record_sql($sql, [$mod[1]->id]);
                            $content .= '<h3>' . $data->name . '</h3>' . "\n";
                            if ($data->availability) {
                                $content .= self::get_availability_content($data->name, $data->availability);
                            }
                            if ($data->editbegin) {
                                $content .= 'You can start editing the wiki assessment, assignment, activity called ' . $data->name . ' on ' . date('Y-m-d H:i:s', $data->editbegin) . "<br>\n";
                            }
                            if ($data->editend) {
                                $content .= 'The wiki assessment, assignment, activity called ' . $data->name . ' will be closed for editing on ' . date('Y-m-d H:i:s', $data->editend) . "<br>\n";
                            }
                            break;
                        case 'workshop':
                            $sql = "SELECT workshop.*, cm.availability FROM {workshop} workshop 
                                        Inner Join {course_modules} cm ON cm.instance = workshop.id WHERE cm.id = ?";
                            $data = $DB->get_record_sql($sql, [$mod[1]->id]);
                            $content .= '<h3>' . $data->name . '</h3>' . "\n";
                            if ($data->availability) {
                                $content .= self::get_availability_content($data->name, $data->availability);
                            }
                            if ($data->submissionstart) {
                                $content .= 'You can start submitting your work for the workshop assessment, assignment, activity called ' . $data->name . ' on ' . date('Y-m-d H:i:s', $data->submissionstart) . "<br>\n";
                            }
                            if ($data->submissionend) {
                                $content .= 'The workshop assessment, assignment, activity called ' . $data->name . ' will be closed for submissions on ' . date('Y-m-d H:i:s', $data->submissionend) . "<br>\n";
                            }
                            if ($data->assessmentstart) {
                                $content .= 'You can start assessing the work for the workshop assessment, assignment, activity called ' . $data->name . ' on ' . date('Y-m-d H:i:s', $data->assessmentstart) . "<br>\n";
                            }
                            if ($data->assessmentend) {
                                $content .= 'The workshop assessment, assignment, activity called ' . $data->name . ' will be closed for assessments on ' . date('Y-m-d H:i:s', $data->assessmentend) . "<br>\n";
                            }
                            break;
                        case 'journal':
                            $sql = "SELECT journal.*, cm.availability FROM {journal} journal 
                                        Inner Join {course_modules} cm ON cm.instance = journal.id WHERE cm.id = ?";
                            $data = $DB->get_record_sql($sql, [$mod[1]->id]);
                            $content .= '<h3>' . $data->name . '</h3>' . "\n";
                            if ($data->availability) {
                                $content .= self::get_availability_content($data->name, $data->availability);
                            }
                            break;
                        case 'questionnaire':
                            $sql = "SELECT questionnaire.*, cm.availability FROM {questionnaire} questionnaire 
                                        Inner Join {course_modules} cm ON cm.instance = questionnaire.id WHERE cm.id = ?";
                            $data = $DB->get_record_sql($sql, [$mod[1]->id]);
                            $content .= '<h3>' . $data->name . '</h3>' . "\n";
                            if ($data->availability) {
                                $content .= self::get_availability_content($data->name, $data->availability);
                            }
                            if ($data->opendate) {
                                $content .= 'You can start the questionnaire assessment, assignment, activity called ' . $data->name . ' on ' . date('Y-m-d H:i:s', $data->opendate) . "<br>\n";
                            }
                            if ($data->closedate) {
                                $content .= 'The questionnaire assessment, assignment, activity called ' . $data->name . ' will be closed on ' . date('Y-m-d H:i:s', $data->closedate) . "<br>\n";
                            }
                            break;
                        default:
                            $sql = "SELECT " . $mod[1]->modname . ".*, cm.availability FROM {" . $mod[1]->modname . "} " . $mod[1]->modname
                                . "  Inner Join {course_modules} cm ON cm.instance = " . $mod[1]->modname . ".id WHERE cm.id = ?";
                            $data = $DB->get_record_sql($sql, [$mod[1]->id]);
                            $content .= '<h3>' . $data->name . '</h3>' . "\n";
                            if ($data->availability) {
                                $content .= self::get_availability_content($data->name, $data->availability);
                            }
                            break;
                    }
                    // Open file based on file name
                    $file = fopen($file_name, 'a');
                    // Write the content to the file
                    fwrite($file, $content);
                    // Close the file
                    fclose($file);
                }
                $x++;
            }

        }
        // prepare file for upload to cria
        $new_file_name = 'Assessment dates ' . date('Y-m-d His') . '.html';
        $file_content = base64_encode(file_get_contents($file_name));
        $new_cria_file_id = cria::upload_content_to_bot($courseid, $new_file_name, $file_content, 'GENERIC');
        // save file to bloc settings
        $DB->set_field('block_aia_settings', 'cria_assignment_file_id', $new_cria_file_id, ['courseid' => $courseid]);
        // Delete file
        unlink($file_name);
    }
}