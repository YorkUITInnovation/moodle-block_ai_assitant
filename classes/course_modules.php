<?php

namespace block_ai_assistant;

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
                        $course_structure->sections[$i]->modules[$x] = new \stdClass();
                        $course_structure->sections[$i]->modules[$x]->name = $mod[0]->name;
                        $course_structure->sections[$i]->modules[$x]->intro = strip_tags($mod[0]->intro);
                        $course_structure->sections[$i]->modules[$x]->instanceid = $mod[0]->id;
                        $course_structure->sections[$i]->modules[$x]->cmid = $mod[1]->id;
                        $course_structure->sections[$i]->modules[$x]->modname = $mod[1]->modname;
                        // Is this module trained?
                        if ($ai_assistant_module = $DB->get_record('block_aia_course_modules', ['cmid' => $mod[1]->id])) {
                            switch ($ai_assistant_module->trained) {
                                case 0:
                                    $course_structure->sections[$i]->modules[$x]->trained = '<span class="badge badge-warning">'
                                        .  get_string('pending', 'block_ai_assistant')
                                        . '</span>';
                                    break;
                                case 1:
                                    $course_structure->sections[$i]->modules[$x]->trained = '<span class="badge badge-success">'
                                        .  get_string('trained', 'block_ai_assistant')
                                        . '</span>';;
                                    break;
                                case 2:
                                    $course_structure->sections[$i]->modules[$x]->trained = '<span class="badge badge-danger">'
                                        .  get_string('error', 'block_ai_assistant')
                                        . '</span>';
                                    break;
                                case 3:
                                    $course_structure->sections[$i]->modules[$x]->trained = '<span class="badge badge-info">'
                                        .  get_string('training', 'block_ai_assistant')
                                        . '</span>';
                                    break;
                            }
                            $course_structure->sections[$i]->modules[$x]->cria_fileid = $ai_assistant_module->cria_fileid;
                        } else {
                            $course_structure->sections[$i]->modules[$x]->trained = false;
                        }
                        // Get module pix
                        // Prepare the content based on the type of module
                        switch ($mod[1]->modname) {
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
        $module = new \stdClass();
        // Set the file name
        $file_name = $module_type . ' ' . $id . ' ' . substr($name, 0, 30) . '.html';
        // Set the content
        $module_content = $intro . '<br><br>' . $content;
        $module->file_name = $file_name;
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

        return $glossary;
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
}