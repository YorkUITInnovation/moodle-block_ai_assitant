<?php

namespace block_ai_assistant;

use block_ai_assistant\course_modules;
use block_ai_assistant\cria;
use block_ai_assistant\markitdown;

abstract class module_training
{
    private $cmid;
    private $mod;
    private $file_name;
    private $bacmid;
    private $context;
    private $mod_type; // Name of the module type (e.g., page, book, glossary, forum, resource, folder)

    public function __construct(int $cmid, bool $retrain = false)
    {
        global $DB;
        $this->cmid = $cmid;
        $this->context = \context_module::instance($cmid);
        $this->mod = course_modules::get_module_from_cmid($cmid);
        $this->mod_type = $this->mod[1]->modname; // Get the module type (e.g., page, book, glossary, forum, resource, folder)
        $this->bacmid = $DB->get_field(
            'block_aia_course_modules',
            'id',
            [
                'cmid' => $cmid,
                'courseid' => $this->mod[0]->course
            ]
        );
        $this->file_name = $this->mod[1]->modname . '_' . $this->mod[1]->id . '_'
            . substr(str_replace(' ', '_', $this->mod[0]->name), 0, 30) . '.html';

        // If retraining is requested, delete the existing records from cria and course_mod_files
        if ($this->bacmid !== false && $retrain == true) {
            course_modules::delete_course_module_files($this->bacmid);
        }
    }

    /**
     * Return the type of module
     * @return string
     */
    public function get_module_type(): string
    {
        return $this->mod_type;
    }

    /**
     * Persist trained file reference while keeping legacy int schema compatibility.
     *
     * New Criabot flows return document_name (string) instead of numeric content id.
     * The legacy schema expects int in cria_fileid, so we store 0 as a safe placeholder
     * and keep the document name in the display name for traceability.
     *
     * @param mixed $cria_file_id
     * @param string $display_name
     * @return void
     * @throws \dml_exception
     */
    private function save_module_file_record($cria_file_id, string $display_name): void
    {
        global $DB;

        $legacy_id = 0;
        if (is_int($cria_file_id) || (is_string($cria_file_id) && ctype_digit($cria_file_id))) {
            $legacy_id = (int)$cria_file_id;
        } else if (!empty($cria_file_id)) {
            error_log(
                'block_ai_assistant: non-numeric cria file id received for bacmid=' . (int)$this->bacmid
                . ' value=' . substr((string)$cria_file_id, 0, 180)
            );
        }

        $name = (string)$display_name;
        if ($legacy_id === 0 && !empty($cria_file_id)) {
            $name = trim($name . ' [doc:' . (string)$cria_file_id . ']');
        }
        $name = substr($name, 0, 255);

        $DB->insert_record('block_aia_course_mod_files', [
            'bacmid' => $this->bacmid,
            'cria_fileid' => $legacy_id,
            'trained' => 1,
            'name' => $name,
        ]);
    }

    /**
     * Train the page module content
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function page()
    {
        global $CFG, $DB;
        // if $this->bacmid is false, it means that the module is not registered in the block_aia_course_modules table
        if ($this->bacmid === false) {
            return false; // If the module is not registered, return false
        }
        // Set the URL to the module
        $mod_url = $CFG->wwwroot . '/mod/page/view.php?id=' . $this->cmid;
        // In clude the url to the module
        $content = get_string('content_found_at', 'block_ai_assistant')
            . ' <a href="' . $mod_url . '" title="' . $this->mod[0]->name . '">' . $this->mod[0]->name . '</a><br><br>';

        // Set the content
        if (isset($this->mod[0]->intro)) {
            $content .= $this->mod[0]->intro . '<br><br>';
        }
        if (isset($this->mod[0]->content)) {
            $content .=  file_rewrite_pluginfile_urls(
                $this->mod[0]->content,
                'pluginfile.php',
                $this->context->id,
                'mod_page',
                'content',
                $this->mod[0]->revision
            );
        }

        // Make sure $module_content is UTF-8 encoded
        if (!mb_detect_encoding($content, 'UTF-8', true)) {
            $content = mb_convert_encoding($content, 'UTF-8');
        }
        $content = base64_encode($content);

        $cria_file_id = cria::upload_content_to_bot($this->mod[0]->course, $this->file_name, $content);
        if (!$cria_file_id) {
            return false; // If there was an error uploading the content
        } else {
            $this->save_module_file_record($cria_file_id, (string)$this->mod[0]->name);
        }
        return true;
    }

    public function label()
    {
        global $CFG, $DB;
        // if $this->bacmid is false, it means that the module is not registered in the block_aia_course_modules table
        if ($this->bacmid === false) {
            return false; // If the module is not registered, return false
        }
        // Set the URL to the module
        $mod_url = $CFG->wwwroot . '/course/view.php?id=' . $this->mod[0]->course;
        // Name must not be more than 30 characters
        $name =  substr(str_replace(' ', '_', $this->mod[0]->name), 0, 30);
        // Include the url to the module
        $content = get_string('content_found_at', 'block_ai_assistant')
            . ' <a href="' . $mod_url . '" title="' . $name . '">' . $name . '</a><br><br>';

        if (isset($this->mod[0]->content)) {
            $content .= $this->mod[0]->content;
        }

        // Make sure $module_content is UTF-8 encoded
        if (!mb_detect_encoding($content, 'UTF-8', true)) {
            $content = mb_convert_encoding($content, 'UTF-8');
        }
        $content = base64_encode($content);

        $cria_file_id = cria::upload_content_to_bot($this->mod[0]->course, $this->file_name, $content);
        if (!$cria_file_id) {
            return false; // If there was an error uploading the content
        } else {
            $this->save_module_file_record($cria_file_id, (string)$name);
        }
        return true;
    }

    /**
     * Train the book module content
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function book()
    {
        global $CFG, $DB;
        // if $this->bacmid is false, it means that the module is not registered in the block_aia_course_modules table
        if ($this->bacmid === false) {
            return false; // If the module is not registered, return false
        }
        // Set URL to the module
        $mod_url = $CFG->wwwroot . '/mod/book/view.php?id=' . $this->cmid;
        $content = get_string('content_found_at', 'block_ai_assistant')
            . ' <a href="' . $mod_url . '" title="' . $this->mod[0]->name . '">' . $this->mod[0]->name . '</a><br><br>';
        // Get book chapters
        $chapters = $DB->get_records('book_chapters', ['bookid' => $this->mod[0]->instance], 'pagenum ASC');
        foreach ($chapters as $chapter) {
            $content .= file_rewrite_pluginfile_urls(
                $chapter->content,
                'pluginfile.php',
                $this->context->id,
                'mod_book',
                'chapter',
                $chapter->id
            );
        }
        // Make sure $content is UTF-8 encoded
        if (!mb_detect_encoding($content, 'UTF-8', true)) {
            $content = mb_convert_encoding($content, 'UTF-8');
        }
        $content = base64_encode($content);
        // Upload content to Cria
        $cria_file_id = cria::upload_content_to_bot($this->mod[0]->course, $this->file_name, $content);
        if (!$cria_file_id) {
            return false; // If there was an error uploading the content
        } else {
            $this->save_module_file_record($cria_file_id, (string)$this->mod[0]->name);
        }
        return true;
    }

    /**
     * Train the glossary module content
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function glossary()
    {
        global $CFG, $DB;
        // if $this->bacmid is false, it means that the module is not registered in the block_aia_course_modules table
        if ($this->bacmid === false) {
            return false; // If the module is not registered, return false
        }
        // Set URL to the module
        $mod_url = $CFG->wwwroot . 'mod/glossary/view.php?id=' . $this->cmid;
        $content = get_string('content_found_at', 'block_ai_assistant')
            . ' <a href="' . $mod_url . '" title="' . $this->mod[0]->name . '">' . $this->mod[0]->name . '</a><br><br>';

        $glossary_entries = $DB->get_records('glossary_entries', array('glossaryid' => $this->mod[0]->instance , 'approved' => 1));
        foreach ($glossary_entries as $entry) {
            $content .= '<h3>' . $entry->concept . '</h3>';
            $content .= file_rewrite_pluginfile_urls(
                $entry->definition,
                'pluginfile.php',
                $this->context->id,
                'mod_glossary',
                'entry',
                $entry->id
            );
            $content .= '<br><br>';
        }

        // Make sure $html is UTF-8 encoded
        if (!mb_detect_encoding($content, 'UTF-8', true)) {
            $html = mb_convert_encoding($content, 'UTF-8');
        }
        $content = base64_encode($html);
        // Upload content to Cria
        $cria_file_id = cria::upload_content_to_bot($this->mod[0]->course, $this->file_name, $content);
        if (!$cria_file_id) {
            return false; // If there was an error uploading the content
        } else {
            $this->save_module_file_record($cria_file_id, (string)$this->mod[0]->name);
        }

        return true;
    }

    /**
     * Train the forum module content
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function forum()
    {
        global $CFG, $DB;
        // if $this->bacmid is false, it means that the module is not registered in the block_aia_course_modules table
        if ($this->bacmid === false) {
            return false; // If the module is not registered, return false
        }
        if ($this->mod[0]->type !== 'news') {
            return false; // Only news forums are supported
        }
        // Set URL to the module
        $mod_url =$CFG->wwwroot . '/mod/forum/view.php?id=' . $this->cmid;
        $content = get_string('content_found_at', 'block_ai_assistant')
            . ' <a href="' . $mod_url . '" title="' . $this->mod[0]->name . '">' . $this->mod[0]->name . '</a><br><br>';

        // Get forum discussions
        $forum_discussions = $DB->get_records('forum_discussions', array('forum' => $this->mod[0]->instance));
        if (empty($forum_discussions)) {
            return false; // No discussions found
        }
        foreach ($forum_discussions as $fd) {
            // Get forum posts
            $forum_posts = $DB->get_records('forum_posts', array('discussion' => $fd->id));
            foreach ($forum_posts as $fp) {
                $content .= '<h3>' . $fp->subject . '</h3>';
                $content .= file_rewrite_pluginfile_urls(
                    $fp->message,
                    'pluginfile.php',
                        $this->context->id,
                        'mod_forum',
                    'post',
                    $this->mod[0]->instance) . "\n";
            }
        }
        // Make sure $content is UTF-8 encoded
        if (!mb_detect_encoding($content, 'UTF-8', true)) {
            $content = mb_convert_encoding($content, 'UTF-8');
        }
        $content = base64_encode($content);
        // Upload content to Cria
        $cria_file_id = cria::upload_content_to_bot($this->mod[0]->course, $this->file_name, $content);
        if (!$cria_file_id) {
            return false; // If there was an error uploading the content
        } else {
            $this->save_module_file_record($cria_file_id, (string)$this->mod[0]->name);
        }
        return true;
    }

    /**
     * @return true
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function resource()
    {
        global $CFG, $DB;
        // if $this->bacmid is false, it means that the module is not registered in the block_aia_course_modules table
        if ($this->bacmid === false) {
            return false; // If the module is not registered, return false
        }
        // Set URL to the module
        $mod_url = $CFG->wwwroot . '/mod/forum/view.php?id=' . $this->cmid;
        // Get files from the resource
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'mod_resource', 'content');
        // Loop through the files
        foreach ($files as $file) {
            if (!$file->is_directory() && $file->get_sortorder() == 1) {
                // Only accept the following file formats: docx, pdf, txt, html, htm, pptx, ppt, odt, rtf, md, excel,csv, xlsx, mp3, mp4
                if (!in_array($file->get_mimetype(), course_modules::get_accepted_file_types())) {
                    continue; // Skip unsupported file types
                }

                $path = $CFG->dataroot . '/temp/ai_assistant/';
                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }
                $path .= 'resource/';
                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }
                $path .= $this->mod[1]->id . '/';
                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }

                // Set the file name
                $file_name = $file->get_filename();
                $file_name_for_saving = $file_name;
                // Save a copy of the file
                $file->copy_content_to($path . $file_name);
                // Using maritdown to convert the content to HTML
                // This is a workaround to avoid issues with file_get_contents and large files
                $converted_file = markitdown::execute($path . $file_name, $file->get_mimetype());
                if ($converted_file->error) {
                    // If there is an error, set the content to an empty string
                    $content = '';
                } else {
                    unlink($path . $file_name);
                    $content = get_string('content_found_at', 'block_ai_assistant')
                        . ' <a href="' . $mod_url . '" title="' . $this->mod[0]->name . '">' . $this->mod[0]->name . '</a><br><br>';
                    // Set the content to the converted file content
                    $content .= markdown_to_html($converted_file['content']);
                    $file_name = 'resource_' . $this->cmid . '_' . str_replace(' ', '_', $converted_file->file_name) . '.html';

                    // Make sure the content is UTF-8 encoded
                    if (!mb_detect_encoding($content, 'UTF-8', true)) {
                        $content = mb_convert_encoding($content, 'UTF-8');
                    }
                    $content = base64_encode($content);
                    // Upload to cria
                    $cria_file_id = cria::upload_content_to_bot($this->mod[0]->course, $file_name, $content);
                    if (!$cria_file_id) {
                        continue; // If there was an error uploading the content
                    } else {
                        $this->save_module_file_record($cria_file_id, (string)$file_name_for_saving);
                    }
                }

            }
        }

        return true;

    }

    /**
     * @param int $bacmid
     * @param int $courseid
     * @param int $cmid
     * @param int $id
     * @param string $name
     * @param string $intro
     * @return true
     * @throws \coding_exception
     */
    public  function folder()
    {
        global $CFG, $DB;
        // if $this->bacmid is false, it means that the module is not registered in the block_aia_course_modules table
        if ($this->bacmid === false) {
            return false; // If the module is not registered, return false
        }
        // Get the course module ID
        $mod_url =$CFG->wwwroot . '/mod/folder/view.php?id=' . $this->cmid;
        // We will need to get files from the folder
        $fs = get_file_storage();

        // Get files for this entry
        $files = $fs->get_area_files($this->context->id, 'mod_folder', 'content', 0);
        $folder_files = [];
        $i = 0;
        foreach ($files as $file) {
            if (!$file->is_directory()
            ) {
                // Only accept the following file formats: docx, pdf, txt, html, htm, pptx, ppt, odt, rtf, md
                if (!in_array($file->get_mimetype(), course_modules::get_accepted_file_types())) {
                    continue; // Skip unsupported file types
                }
                $path = $CFG->dataroot . '/temp/ai_assistant/';
                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }
                $path .= 'folder/';
                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }
                $path .= $this->mod[1]->id . '/';
                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }
                // Set the file name
                $file_name = $file->get_filename();
                $file_name_for_saving = $file_name;
                // Save a copy of the file
                $file->copy_content_to($path . $file_name);
                // Using maritdown to convert the content to HTML
                $converted_file = (object)markitdown::execute($path . $file_name, $file->get_mimetype());
                // If the file is a directory, we will not convert it
                if ($file->is_directory()) {
                    $content = '';
                }
                // If the file is not a directory, we will convert it
                // If there was an error converting the file, we will skip it
               // If there was an error converting the file
                if (isset($converted_file->error)) {
                    // If there is an error, set the content to an empty string
                    $content = '';
                } else {
                    unlink($path . $file_name);
                    // Add module URL to content
                    $content = get_string('content_found_at', 'block_ai_assistant')
                        . ' <a href="' . $mod_url . '" title="' . $this->mod[0]->name . '">' . $this->mod[0]->name . '</a><br><br>';
                    // Set the content to the converted file content
                    $content .= markdown_to_html($converted_file['content']);
                    $file_name = 'folder_' . $this->cmid . '_' . str_replace(' ', '_', $converted_file->filename) . '.html';
                    // Make sure the content is UTF-8 encoded
                    if (!mb_detect_encoding($content, 'UTF-8', true)) {
                        $content = mb_convert_encoding($content, 'UTF-8');
                    }
                    $content = base64_encode($content);
                    // Upload content to Cria
                    $cria_file_id = cria::upload_content_to_bot($this->mod[0]->course, $file_name, $content);
                    if (!$cria_file_id) {
                        continue; // If there was an error uploading the content
                    } else {
                        $this->save_module_file_record($cria_file_id, (string)$file_name_for_saving);
                    }
                    // Wait 2 seconds before moving to the next file
                    sleep(2);
                }
            }
        }

        return true;
    }
}