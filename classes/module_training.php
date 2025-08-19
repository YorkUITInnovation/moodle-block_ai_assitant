<?php

namespace block_ai_assistant;

use block_ai_assistant\course_modules;
use block_ai_assistant\cria;
use block_ai_assistant\markitdown;

require_once('ConvertApi/autoload.php');

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
            $content .= file_rewrite_pluginfile_urls(
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
        // Convert base64 images to files and update HTML
        $images_path = $CFG->dirroot . '/blocks/ai_assistant/temp_images/' . $this->mod[1]->id . '/';
        $base_filename = pathinfo($this->file_name, PATHINFO_FILENAME);
        $content = $this->convert_base64_images_to_files($content, $images_path, $base_filename);

        // Convert content to base64
        $content = base64_encode($content);

        $cria_file_id = cria::upload_content_to_bot($this->mod[0]->course, $this->file_name, $content);
        if (!$cria_file_id) {
            return false; // If there was an error uploading the content
        } else {
            // Insert record into block_aia_course_mod_files
            $DB->insert_record('block_aia_course_mod_files', [
                'bacmid' => $this->bacmid,
                'cria_fileid' => $cria_file_id,
                'name' => $this->mod[0]->name,
            ]);
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
        $name = substr(str_replace(' ', '_', $this->mod[0]->name), 0, 30);
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
        // Convert base64 images to files and update HTML
        $images_path = $CFG->dirroot . '/blocks/ai_assistant/temp_images/' . $this->mod[1]->id . '/';
        $base_filename = pathinfo($this->file_name, PATHINFO_FILENAME);
        $content = $this->convert_base64_images_to_files($content, $images_path, $base_filename);

        $content = base64_encode($content);

        $cria_file_id = cria::upload_content_to_bot($this->mod[0]->course, $this->file_name, $content);
        if (!$cria_file_id) {
            return false; // If there was an error uploading the content
        } else {
            // Insert record into block_aia_course_mod_files
            $DB->insert_record('block_aia_course_mod_files', [
                'bacmid' => $this->bacmid,
                'cria_fileid' => $cria_file_id,
                'name' => $name,
            ]);
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
        // Convert base64 images to files and update HTML
        $images_path = $CFG->dirroot . '/blocks/ai_assistant/temp_images/' . $this->mod[1]->id . '/';
        $base_filename = pathinfo($this->file_name, PATHINFO_FILENAME);
        $content = $this->convert_base64_images_to_files($content, $images_path, $base_filename);

        // Convert content to base64
        $content = base64_encode($content);
        // Upload content to Cria
        $cria_file_id = cria::upload_content_to_bot($this->mod[0]->course, $this->file_name, $content);
        if (!$cria_file_id) {
            return false; // If there was an error uploading the content
        } else {
            $DB->insert_record('block_aia_course_mod_files', [
                'bacmid' => $this->bacmid,
                'cria_fileid' => $cria_file_id,
                'name' => $this->mod[0]->name
            ]);
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

        $glossary_entries = $DB->get_records('glossary_entries', array('glossaryid' => $this->mod[0]->instance, 'approved' => 1));
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

        // Make sure $content is UTF-8 encoded
        if (!mb_detect_encoding($content, 'UTF-8', true)) {
            $content = mb_convert_encoding($content, 'UTF-8');
        }
        // Convert base64 images to files and update HTML
        $images_path = $CFG->dirroot . '/blocks/ai_assistant/temp_images/' . $this->mod[1]->id . '/';
        $base_filename = pathinfo($this->file_name, PATHINFO_FILENAME);
        $content = $this->convert_base64_images_to_files($content, $images_path, $base_filename);
        // Convert content to base64
        $content = base64_encode($content);
        // Upload content to Cria
        $cria_file_id = cria::upload_content_to_bot($this->mod[0]->course, $this->file_name, $content);
        if (!$cria_file_id) {
            return false; // If there was an error uploading the content
        } else {
            // Insert record into block_aia_course_mod_files
            $DB->insert_record('block_aia_course_mod_files', [
                'bacmid' => $this->bacmid,
                'cria_fileid' => $cria_file_id,
                'name' => $this->mod[0]->name
            ]);
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
        $mod_url = $CFG->wwwroot . '/mod/forum/view.php?id=' . $this->cmid;
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
        // Convert base64 images to files and update HTML
        $images_path = $CFG->dirroot . '/blocks/ai_assistant/temp_images/' . $this->mod[1]->id . '/';
        $base_filename = pathinfo($this->file_name, PATHINFO_FILENAME);
        $content = $this->convert_base64_images_to_files($content, $images_path, $base_filename);
        // Convert content to base64
        $content = base64_encode($content);
        // Upload content to Cria
        $cria_file_id = cria::upload_content_to_bot($this->mod[0]->course, $this->file_name, $content);
        if (!$cria_file_id) {
            return false; // If there was an error uploading the content
        } else {
            $DB->insert_record('block_aia_course_mod_files', [
                'bacmid' => $this->bacmid,
                'cria_fileid' => $cria_file_id,
                'name' => $this->mod[0]->name
            ]);
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
        $config = get_config('block_ai_assistant');
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

                // Get the MIME type to determine processing method
                $mime_type = $file->get_mimetype();
                // Use the original path structure with CFG->dirroot
                $images_path = $CFG->dirroot . '/blocks/ai_assistant/temp_images/'. $this->mod[1]->id . '/';

                if (!file_exists($images_path)) {
                    mkdir($images_path, 0777, true);
                }

                // Check if file is PDF or DOCX
                if ($mime_type === 'application/pdf') {
                    // Use convertapi to convert the file to HTML
                    \ConvertApi\ConvertApi::setApiCredentials($config->convert_api_key);
                    $result = \ConvertApi\ConvertApi::convert('html', [
                        'File' => $path . $file_name,
                    ], 'pdf'
                    );
                    // Change $file_name to the converted file name
                    $file_name = str_replace('.pdf', '.html', $file_name);
                    // Get content from ConvertApi result
                    $content = $result->getFile()->getContents();

                    // Create images directory for this resource
                    $base_filename = 'resource_' . $this->cmid . '_' . pathinfo($file_name_for_saving, PATHINFO_FILENAME);

                    // Convert base64 images to physical files and update HTML
                    $content = $this->convert_base64_images_to_files($content, $images_path, $base_filename);

                } elseif ($mime_type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
                    // Use convertapi to convert the file to HTML
                    \ConvertApi\ConvertApi::setApiCredentials($config->convert_api_key);
                    $result = \ConvertApi\ConvertApi::convert('html', [
                        'File' => $path . $file_name,
                    ], 'docx'
                    );
                    // Change $file_name to the converted file name
                    $file_name = str_replace('.docx', '.html', $file_name);
                    // Get content from ConvertApi result
                    $content = $result->getFile()->getContents();

                    $base_filename = 'resource_' . $this->cmid . '_' . pathinfo($file_name_for_saving, PATHINFO_FILENAME);

                    // Convert base64 images to physical files and update HTML
                    $content = $this->convert_base64_images_to_files($content, $images_path, $base_filename);

                } else {
                    // For all other file types, use markitdown to convert
                    $converted_file = markitdown::execute($path . $file_name, false);
                    if (isset($converted_file->error) && $converted_file->error) {
                        // If there is an error, skip this file
                        continue;
                    } else {
                        // Set the content to the converted file content using markdown_to_html
                        $content = markdown_to_html($converted_file->content);
                        $file_name = str_replace(' ', '_', $converted_file->file_name) . '.html';

                        // Create images directory for this resource
                        $base_filename = 'resource_' . $this->cmid . '_' . pathinfo($file_name_for_saving, PATHINFO_FILENAME);

                        // Convert base64 images to physical files and update HTML
                        $content = $this->convert_base64_images_to_files($content, $images_path, $base_filename);
                    }
                }

                // Add module URL prefix to content
                $content = get_string('content_found_at', 'block_ai_assistant')
                    . ' <a href="' . $mod_url . '" title="' . $this->mod[0]->name . '">' . $this->mod[0]->name . '</a><br><br>'
                    . $content;

                // Make sure the content is UTF-8 encoded
                if (!mb_detect_encoding($content, 'UTF-8', true)) {
                    $content = mb_convert_encoding($content, 'UTF-8');
                }

                // Convert content to base64
                $content = base64_encode($content);

                // Create final filename with resource prefix
                $final_file_name = 'resource_' . $this->cmid . '_' . $file_name;

                // Upload to cria
                $cria_file_id = cria::upload_content_to_bot($this->mod[0]->course, $final_file_name, $content);
                if (!$cria_file_id) {
                    continue; // If there was an error uploading the content
                } else {
                    // Insert record into block_aia_course_mod_files
                    $DB->insert_record('block_aia_course_mod_files', [
                        'bacmid' => $this->bacmid,
                        'cria_fileid' => $cria_file_id,
                        'name' => $file_name_for_saving
                    ]);
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
    public function folder()
    {
        global $CFG, $DB;
        $config = get_config('block_ai_assistant');
        // if $this->bacmid is false, it means that the module is not registered in the block_aia_course_modules table
        if ($this->bacmid === false) {
            return false; // If the module is not registered, return false
        }
        // Set module URL
        $mod_url = $CFG->wwwroot . '/mod/folder/view.php?id=' . $this->cmid;
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'mod_folder', 'content', 0);
        foreach ($files as $file) {
            if ($file->is_directory() || !in_array($file->get_mimetype(), course_modules::get_accepted_file_types())) {
                continue;
            }
            // Prepare temp path and save a copy
            $tempdir = $CFG->dataroot . '/temp/ai_assistant/folder/' . $this->mod[1]->id . '/';
            if (!file_exists($tempdir)) {
                mkdir($tempdir, 0777, true);
            }
            $origname = $file->get_filename();
            $file->copy_content_to($tempdir . $origname);
            $mime = $file->get_mimetype();
            // Prepare images path
            $images_path = $CFG->dirroot . '/blocks/ai_assistant/temp_images/' . $this->mod[1]->id . '/';
            if (!file_exists($images_path)) {
                mkdir($images_path, 0777, true);
            }
            // Convert file to HTML
            if ($mime === 'application/pdf') {
                \ConvertApi\ConvertApi::setApiCredentials($config->convert_api_key);
                $result = \ConvertApi\ConvertApi::convert('html', ['File' => $tempdir . $origname], 'pdf');
                $content = $result->getFile()->getContents();
                $finalname = str_replace('.pdf', '.html', $origname);
                $base = 'folder_' . $this->cmid . '_' . pathinfo($origname, PATHINFO_FILENAME);
                $content = $this->convert_base64_images_to_files($content, $images_path, $base);
            } elseif ($mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
                \ConvertApi\ConvertApi::setApiCredentials($config->convert_api_key);
                $result = \ConvertApi\ConvertApi::convert('html', ['File' => $tempdir . $origname], 'docx');
                $content = $result->getFile()->getContents();
                $finalname = str_replace('.docx', '.html', $origname);
                $base = 'folder_' . $this->cmid . '_' . pathinfo($origname, PATHINFO_FILENAME);
                $content = $this->convert_base64_images_to_files($content, $images_path, $base);
            } else {
                $converted = markitdown::execute($tempdir . $origname, false);
                if (empty($converted->error)) {
                    $content = markdown_to_html($converted->content);
                    $finalname = 'folder_' . $this->cmid . '_' . str_replace(' ', '_', $converted->file_name) . '.html';
                    $base = 'folder_' . $this->cmid . '_' . pathinfo($origname, PATHINFO_FILENAME);
                    $content = $this->convert_base64_images_to_files($content, $images_path, $base);
                } else {
                    continue;
                }
            }
            // Prefix module URL
            $content = get_string('content_found_at', 'block_ai_assistant')
                . ' <a href="' . $mod_url . '" title="' . $this->mod[0]->name . '">' . $this->mod[0]->name . '</a><br><br>'
                . $content;
            // Ensure UTF-8 and encode
            if (!mb_detect_encoding($content, 'UTF-8', true)) {
                $content = mb_convert_encoding($content, 'UTF-8');
            }
            $encoded = base64_encode($content);
            // Upload and record
            $cria_id = cria::upload_content_to_bot($this->mod[0]->course, $finalname, $encoded);
            if ($cria_id) {
                $DB->insert_record('block_aia_course_mod_files', [
                    'bacmid' => $this->bacmid,
                    'cria_fileid' => $cria_id,
                    'name' => $origname,
                ]);
            }
            sleep(2);
        }

        return true;
    }

    /**
     * Train the tab module content
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function tab()
    {
        global $CFG, $DB;
        // if $this->bacmid is false, it means that the module is not registered in the block_aia_course_modules table
        if ($this->bacmid === false) {
            return false; // If the module is not registered, return false
        }
        // Set URL to the module
        $mod_url = $CFG->wwwroot . '/mod/tab/view.php?id=' . $this->cmid;
        $content = get_string('content_found_at', 'block_ai_assistant')
            . ' <a href="' . $mod_url . '" title="' . $this->mod[0]->name . '">' . $this->mod[0]->name . '</a><br><br>';
        // Get book chapters
        $tab_contents = $DB->get_records('tab_content', ['tabid' => $this->mod[0]->instance], 'tabcontentorder ASC');
        foreach ($tab_contents as $tab_content) {
            $content .= '<h4>' . $tab_content->tabname . '</h4>';
            $content .= file_rewrite_pluginfile_urls(
                $tab_content->tabcontent,
                'pluginfile.php',
                $this->context->id,
                'mod_tab',
                'content',
                $tab_content->id
            );
        }
        // Make sure $content is UTF-8 encoded
        if (!mb_detect_encoding($content, 'UTF-8', true)) {
            $content = mb_convert_encoding($content, 'UTF-8');
        }
        // Convert base64 images to files and update HTML
        $images_path = $CFG->dirroot . '/blocks/ai_assistant/temp_images/' . $this->mod[1]->id . '/';
        $base_filename = pathinfo($this->file_name, PATHINFO_FILENAME);
        $content = $this->convert_base64_images_to_files($content, $images_path, $base_filename);
        // Convert content to base64
        $content = base64_encode($content);
        // Upload content to Cria
        $cria_file_id = cria::upload_content_to_bot($this->mod[0]->course, $this->file_name, $content);
        if (!$cria_file_id) {
            return false; // If there was an error uploading the content
        } else {
            $DB->insert_record('block_aia_course_mod_files', [
                'bacmid' => $this->bacmid,
                'cria_fileid' => $cria_file_id,
                'name' => $this->mod[0]->name
            ]);
        }
        return true;
    }

    /**
     * Convert base64 images in HTML content to physical files and update HTML references
     * @param string $html_content The HTML content containing base64 images
     * @param string $images_path The path where images should be saved
     * @param string $base_filename The base filename for generating unique image names
     * @return string The updated HTML content with file references instead of base64
     */
    private function convert_base64_images_to_files($html_content, $images_path, $base_filename)
    {
        global $CFG;

        // Debug: Log the function call
        error_log("convert_base64_images_to_files called with images_path: " . $images_path);
        error_log("CFG->dirroot: " . $CFG->dirroot);
        error_log("CFG->wwwroot: " . $CFG->wwwroot);

        // Create images directory if it doesn't exist
        if (!file_exists($images_path)) {
            $mkdir_result = mkdir($images_path, 0777, true);
            error_log("mkdir result for " . $images_path . ": " . ($mkdir_result ? "SUCCESS" : "FAILED"));
            if (!$mkdir_result) {
                error_log("mkdir failed, checking parent directory permissions...");
                $parent_dir = dirname($images_path);
                error_log("Parent directory: " . $parent_dir . " exists: " . (file_exists($parent_dir) ? "YES" : "NO"));
                error_log("Parent directory writable: " . (is_writable($parent_dir) ? "YES" : "NO"));
            }
        } else {
            error_log("Directory already exists: " . $images_path);
            error_log("Directory writable: " . (is_writable($images_path) ? "YES" : "NO"));
        }

        // Pattern to match base64 images in img tags
        $pattern = '/src="data:image\/([^;]+);base64,([^"]+)"/i';

        // Debug: Check if pattern matches anything
        $matches_found = preg_match_all($pattern, $html_content, $all_matches);
        error_log("Found " . $matches_found . " base64 images in HTML content");

        if ($matches_found > 0) {
            error_log("First few image formats found: " . print_r(array_slice($all_matches[1], 0, 3), true));
        }

        $image_counter = 1;

        // Replace each base64 image with a file reference
        $updated_html = preg_replace_callback($pattern, function($matches) use ($images_path, $base_filename, &$image_counter, $CFG) {
            $image_format = $matches[1]; // png, jpg, jpeg, gif, etc.
            $base64_data = $matches[2];

            error_log("Processing image " . $image_counter . " with format: " . $image_format);

            // Decode the base64 data
            $image_data = base64_decode($base64_data);

            if ($image_data === false) {
                error_log("Failed to decode base64 data for image " . $image_counter);
                // If decode fails, return original src
                return 'src="data:image/' . $image_format . ';base64,' . $base64_data . '"';
            }

            // Generate unique filename
            $image_filename = $base_filename . '_image_' . $image_counter . '.' . $image_format;
            $image_counter++;

            // Full path for the image file
            $image_file_path = $images_path . $image_filename;

            error_log("Attempting to save image to: " . $image_file_path);
            error_log("Image data size: " . strlen($image_data) . " bytes");
            error_log("Directory exists: " . (file_exists($images_path) ? "YES" : "NO"));
            error_log("Directory writable: " . (is_writable($images_path) ? "YES" : "NO"));

            // Save the image to file
            $bytes_written = file_put_contents($image_file_path, $image_data);
            if ($bytes_written !== false) {
                error_log("Successfully saved image: " . $image_file_path . " (" . $bytes_written . " bytes)");
                // Verify file was actually created
                if (file_exists($image_file_path)) {
                    error_log("File verification: File exists and size is " . filesize($image_file_path) . " bytes");
                } else {
                    error_log("File verification: File does NOT exist after write!");
                }
                // Create web-accessible URL for the image
                // Convert from dirroot path to wwwroot URL
                $web_path = str_replace($CFG->dirroot, $CFG->wwwroot, $image_file_path);
                error_log("Generated web path: " . $web_path);
                return 'src="' . $web_path . '"';
            } else {
                error_log("Failed to save image to: " . $image_file_path);
                $error = error_get_last();
                error_log("Last PHP error: " . print_r($error, true));
                // If file save fails, return original src
                return 'src="data:image/' . $image_format . ';base64,' . $base64_data . '"';
            }
        }, $html_content);

        error_log("Image conversion completed");
        return $updated_html;
    }

}
