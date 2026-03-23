<?php

namespace block_ai_assistant;

use block_ai_assistant\course_modules;
use block_ai_assistant\cria;
use block_ai_assistant\markitdown;

require_once(__DIR__ . '/ConvertApi/autoload.php');

abstract class module_training
{
    private $cmid;
    private $mod;
    private $file_name;
    private $bacmid;
    private $context;
    private $mod_type; // Name of the module type (e.g., page, book, glossary, forum, resource, folder)
    private $images_path;
    // Collect names of files that were skipped because the type isn't supported
    private $unsupported_files = [];

    public function __construct(int $cmid, bool $retrain = false)
    {
        global $CFG, $DB;
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

        // Set up temp images path for base64 image extraction (used by ConvertApi PDF/DOCX conversion)
        $images_path = $CFG->dataroot . '/temp/ai_assistant/';
        if (!file_exists($images_path)) {
            mkdir($images_path, 0777, true);
        }
        $images_path .= $this->cmid . '/';
        if (!file_exists($images_path)) {
            mkdir($images_path, 0777, true);
        }
        $this->images_path = $images_path;
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
     * List files skipped due to unsupported type
     * @return string[]
     */
    public function get_unsupported_files(): array
    {
        return $this->unsupported_files;
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

        // Make sure $content is UTF-8 encoded
        if (!mb_detect_encoding($content, 'UTF-8', true)) {
            $content = mb_convert_encoding($content, 'UTF-8');
        }
        $content = base64_encode($content);
        $cria_file_id = cria::upload_content_to_bot($this->mod[0]->course, $this->file_name, $content);
        if (!$cria_file_id) {
            return false;
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
     * Train the forum module content (news forums only)
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
            return false;
        }
        if ($this->mod[0]->type !== 'news') {
            return false; // Only news forums are supported
        }
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
     * Train the resource module content.
     * PDF and DOCX are converted via ConvertApi (preserves images).
     * All other supported types are converted via markitdown.
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function resource()
    {
        global $CFG, $DB;
        $config = get_config('block_ai_assistant');
        if ($this->bacmid === false) {
            return false;
        }
        $supported_mime_types = markitdown::supported_mime_types();
        $mod_url = $CFG->wwwroot . '/mod/resource/view.php?id=' . $this->cmid;
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'mod_resource', 'content');

        // If there is exactly one non-directory file and it is unsupported, track and bail early
        $nonDir = [];
        foreach ($files as $f) {
            if (!$f->is_directory()) {
                $nonDir[] = $f;
            }
        }
        if (count($nonDir) === 1) {
            $single = $nonDir[0];
            if (!empty($supported_mime_types) && !in_array($single->get_mimetype(), $supported_mime_types)) {
                $this->unsupported_files[] = $single->get_filename();
                course_modules::delete_course_module_by_cmid($this->cmid);
                return false;
            }
        }

        foreach ($files as $file) {
            if (!$file->is_directory() && $file->get_sortorder() == 1) {
                if (!empty($supported_mime_types) && !in_array($file->get_mimetype(), $supported_mime_types)) {
                    $this->unsupported_files[] = $file->get_filename();
                    continue;
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
                $mime_type = $file->get_mimetype();

                if (!file_exists($this->images_path)) {
                    mkdir($this->images_path, 0777, true);
                }

                if ($mime_type === 'application/pdf') {
                    // Use ConvertApi to convert PDF to HTML (preserves embedded images)
                    \ConvertApi\ConvertApi::setApiCredentials($config->convert_api_key);
                    $result = \ConvertApi\ConvertApi::convert('html', [
                        'File' => $path . $file_name,
                        'Wysiwyg' => 'false'
                    ], 'pdf');
                    $file_name = str_replace('.pdf', '.html', $file_name);
                    $content = $result->getFile()->getContents();
                    $base_filename = 'resource_' . $this->cmid . '_' . pathinfo($file_name_for_saving, PATHINFO_FILENAME);
                    $content = $this->convert_base64_images_to_files($content, $this->images_path, $base_filename);

                } elseif ($mime_type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
                    // Use ConvertApi to convert DOCX to HTML (preserves embedded images)
                    \ConvertApi\ConvertApi::setApiCredentials($config->convert_api_key);
                    $result = \ConvertApi\ConvertApi::convert('html', [
                        'File' => $path . $file_name,
                    ], 'docx');
                    $file_name = str_replace('.docx', '.html', $file_name);
                    $content = $result->getFile()->getContents();
                    $base_filename = 'resource_' . $this->cmid . '_' . pathinfo($file_name_for_saving, PATHINFO_FILENAME);
                    $content = $this->convert_base64_images_to_files($content, $this->images_path, $base_filename);

                } else {
                    // For all other file types, use markitdown
                    $converted_file = markitdown::execute($path . $file_name);
                    if ($converted_file === false) {
                        continue;
                    }
                    $content = markdown_to_html($converted_file['content']);
                    $file_name = str_replace(' ', '_', $converted_file['filename']) . '.html';
                    $base_filename = 'resource_' . $this->cmid . '_' . pathinfo($file_name_for_saving, PATHINFO_FILENAME);
                    $content = $this->convert_base64_images_to_files($content, $this->images_path, $base_filename);
                }

                // Add module URL prefix to content
                $content = get_string('content_found_at', 'block_ai_assistant')
                    . ' <a href="' . $mod_url . '" title="' . $this->mod[0]->name . '">' . $this->mod[0]->name . '</a><br><br>'
                    . $content;

                if (!mb_detect_encoding($content, 'UTF-8', true)) {
                    $content = mb_convert_encoding($content, 'UTF-8');
                }
                $content = base64_encode($content);
                $final_file_name = 'resource_' . $this->cmid . '_' . $file_name;

                $cria_file_id = cria::upload_content_to_bot($this->mod[0]->course, $final_file_name, $content);
                if (!$cria_file_id) {
                    continue;
                } else {
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
     * Train the folder module content.
     * PDF and DOCX are converted via ConvertApi (preserves images).
     * All other supported types are converted via markitdown.
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function folder()
    {
        global $CFG, $DB;
        $config = get_config('block_ai_assistant');
        if ($this->bacmid === false) {
            return false;
        }
        $supported_mime_types = markitdown::supported_mime_types();
        $mod_url = $CFG->wwwroot . '/mod/folder/view.php?id=' . $this->cmid;
        $fs = get_file_storage();

        // Get files for this entry
        $files = $fs->get_area_files($this->context->id, 'mod_folder', 'content', 0);

        // If there is exactly one non-directory file and it is unsupported, track and bail early
        $nonDir = [];
        foreach ($files as $f) {
            if (!$f->is_directory()) {
                $nonDir[] = $f;
            }
        }
        if (count($nonDir) === 1) {
            $single = $nonDir[0];
            if (!empty($supported_mime_types) && !in_array($single->get_mimetype(), $supported_mime_types)) {
                $this->unsupported_files[] = $single->get_filename();
                course_modules::delete_course_module_by_cmid($this->cmid);
                return false;
            }
        }

        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            if (!empty($supported_mime_types) && !in_array($file->get_mimetype(), $supported_mime_types)) {
                $this->unsupported_files[] = $file->get_filename();
                continue;
            }

            $tempdir = $CFG->dataroot . '/temp/ai_assistant/folder/' . $this->mod[1]->id . '/';
            if (!file_exists($tempdir)) {
                mkdir($tempdir, 0777, true);
            }
            $origname = $file->get_filename();
            $file->copy_content_to($tempdir . $origname);
            $mime = $file->get_mimetype();

            if (!file_exists($this->images_path)) {
                mkdir($this->images_path, 0777, true);
            }

            if ($mime === 'application/pdf') {
                // Use ConvertApi to convert PDF to HTML (preserves embedded images)
                \ConvertApi\ConvertApi::setApiCredentials($config->convert_api_key);
                $result = \ConvertApi\ConvertApi::convert('html', ['File' => $tempdir . $origname], 'pdf');
                $content = $result->getFile()->getContents();
                $finalname = str_replace('.pdf', '.html', $origname);
                $base = 'folder_' . $this->cmid . '_' . pathinfo($origname, PATHINFO_FILENAME);
                $content = $this->convert_base64_images_to_files($content, $this->images_path, $base);

            } elseif ($mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
                // Use ConvertApi to convert DOCX to HTML (preserves embedded images)
                \ConvertApi\ConvertApi::setApiCredentials($config->convert_api_key);
                $result = \ConvertApi\ConvertApi::convert('html', ['File' => $tempdir . $origname], 'docx');
                $content = $result->getFile()->getContents();
                $finalname = str_replace('.docx', '.html', $origname);
                $base = 'folder_' . $this->cmid . '_' . pathinfo($origname, PATHINFO_FILENAME);
                $content = $this->convert_base64_images_to_files($content, $this->images_path, $base);

            } else {
                // For all other file types, use markitdown
                $converted_file = markitdown::execute($tempdir . $origname);
                if ($converted_file === false) {
                    continue;
                }
                $content = markdown_to_html($converted_file['content']);
                $finalname = str_replace(' ', '_', $converted_file['filename']) . '.html';
                $base = 'folder_' . $this->cmid . '_' . pathinfo($origname, PATHINFO_FILENAME);
                $content = $this->convert_base64_images_to_files($content, $this->images_path, $base);
            }

            // Clean up temp file
            unlink($tempdir . $origname);

            // Add module URL to content
            $content = get_string('content_found_at', 'block_ai_assistant')
                . ' <a href="' . $mod_url . '" title="' . $this->mod[0]->name . '">' . $this->mod[0]->name . '</a><br><br>'
                . $content;

            if (!mb_detect_encoding($content, 'UTF-8', true)) {
                $content = mb_convert_encoding($content, 'UTF-8');
            }
            $content = base64_encode($content);
            $final_file_name = 'folder_' . $this->cmid . '_' . $finalname;

            $cria_file_id = cria::upload_content_to_bot($this->mod[0]->course, $final_file_name, $content);
            if (!$cria_file_id) {
                continue;
            } else {
                $DB->insert_record('block_aia_course_mod_files', [
                    'bacmid' => $this->bacmid,
                    'cria_fileid' => $cria_file_id,
                    'name' => $origname
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
        if ($this->bacmid === false) {
            return false;
        }
        $mod_url = $CFG->wwwroot . '/mod/tab/view.php?id=' . $this->cmid;
        $content = get_string('content_found_at', 'block_ai_assistant')
            . ' <a href="' . $mod_url . '" title="' . $this->mod[0]->name . '">' . $this->mod[0]->name . '</a><br><br>';
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
        if (!mb_detect_encoding($content, 'UTF-8', true)) {
            $content = mb_convert_encoding($content, 'UTF-8');
        }
        $content = base64_encode($content);
        $cria_file_id = cria::upload_content_to_bot($this->mod[0]->course, $this->file_name, $content);
        if (!$cria_file_id) {
            return false;
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
     * Convert base64 images in HTML content to physical files and update HTML references.
     * Used after ConvertApi PDF/DOCX conversion to extract embedded images.
     * @param string $html_content HTML content containing base64 images
     * @param string $images_path Path where images should be saved
     * @param string $base_filename Base filename for generating unique image names
     * @return string Updated HTML with file references instead of base64
     */
    private function convert_base64_images_to_files($html_content, $images_path, $base_filename)
    {
        global $CFG;

        if (!file_exists($images_path)) {
            mkdir($images_path, 0777, true);
        }

        $pattern = '/src="data:image\/([^;]+);base64,([^"]+)"/i';
        $image_counter = 1;

        $updated_html = preg_replace_callback($pattern, function($matches) use ($images_path, $base_filename, &$image_counter, $CFG) {
            $image_format = $matches[1];
            $base64_data = $matches[2];
            $image_data = base64_decode($base64_data);

            if ($image_data === false) {
                return 'src="data:image/' . $image_format . ';base64,' . $base64_data . '"';
            }

            $image_filename = $base_filename . '_image_' . $image_counter . '.' . $image_format;
            $image_counter++;
            $image_file_path = $images_path . $image_filename;

            $bytes_written = file_put_contents($image_file_path, $image_data);
            if ($bytes_written !== false) {
                $web_path = $CFG->wwwroot . '/blocks/ai_assistant/imagefile.php?cmid=' . $this->cmid . '&filename=' . $image_filename;
                return 'src="' . $web_path . '"';
            } else {
                return 'src="data:image/' . $image_format . ';base64,' . $base64_data . '"';
            }
        }, $html_content);

        return $updated_html;
    }

}

