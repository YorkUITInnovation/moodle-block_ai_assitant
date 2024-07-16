<?php

namespace block_ai_assistant;

use block_ai_assistant\webservice;
use Exception;

class cria
{

    /**
     * Create bot instance and returns bot_name
     * @param int $course_id
     * @return string bot_name
     */
    public static function create_bot_instance($course_id)
    {
        $method = get_string('create_cria_bot_endpoint', 'block_ai_assistant');
        $data = self::get_create_cria_bot_config($course_id);

        $bot_name = webservice::exec($method, $data);
        return $bot_name;
    }

    /**
     * Returns the config for creating bot instance
     * @param int $course_id
     * @return array
     */
    private static function get_create_cria_bot_config($course_id)
    {
        global $DB;
        // Set parameters
        $context = \context_course::instance($course_id);
        $config = get_config('block_ai_assistant');
        $course_data = $DB->get_record('course', array('id' => $course_id));
        $system_message = self::get_default_system_message($course_id);

        if ($course_data) {
            if ($course_data->idnumber != '') {
                $name = $course_data->idnumber;
            } else {
                $name = $course_data->shortname;
            }
        }

        $data = array(
            'name' => $name,
            'description' => $config->description,
            'bot_type' => $config->bot_type,
            'bot_system_message' => $system_message,
            'model_id' => $config->criadex_model_id,
            'embedding_id' => $config->criadex_embed_id,
            'rerank_model_id' => $config->criadex_rerank_id,
            'requires_content_prompt' => $config->requires_content_prompt,
            'requires_user_prompt' => $config->requires_user_prompt,
            'user_prompt' => $config->user_prompt,
            'welcome_message' => $config->welcome_message,
            'theme_color' => $config->theme_color,
            'max_tokens' => $config->max_tokens,
            'temperature' => $config->temperature,
            'top_p' => $config->top_p,
            'top_k' => $config->top_k,
            'top_n' => $config->top_n,
            'min_k' => $config->min_k,
            'min_relevance' => $config->min_relevance,
            'max_context' => $config->max_context,
            'no_context_message' => self::get_default_no_context_message(),
            'no_context_use_message' => $config->no_context_use_message,
            'no_context_llm_guess' => $config->no_context_llm_guess,
            'email' => implode('; ', self::get_teacher_emails($course_id)),
            'available_child' => $config->available_child,
            'parse_strategy' => $config->parse_strategy,
            'botwatermark' => $config->botwatermark,
            'title' => $config->title,
            'subtitle' => $config->subtitle,
            'embed_position' => $config->embed_position,
            'icon_url' => $config->icon_url,
            'bot_locale' => $config->bot_locale,
            'child_bots' => $config->child_bots,
            'publish' => 0
        );
        return $data;
    }

    /**
     * Uploads content to bot and returns file_id
     * @param string $file_path
     * @param int $course_id
     * @return int file_id
     */
    public static function upload_content_to_bot($file_path, $course_id)
    {
        $method = get_string('upload_content_to_bot_endpoint', 'block_ai_assistant');
        $data = self::get_upload_content_to_bot_config($file_path, $course_id);
        $file_id = webservice::exec($method, $data);
        return $file_id;
    }

    /**
     * Returns the config for uploading content to bot
     * @param string $file_path
     * @param int $course_id
     * @return array
     */
    private static function get_upload_content_to_bot_config($file_path, $course_id)
    {
        global $DB;
        $file_content = file_get_contents($file_path);
        $encoded_content = base64_encode($file_content);
        $filename = basename($file_path);
        $courserecord = $DB->get_record('block_aia_settings', array('courseid' => $course_id));
        if ($courserecord) {
            $bot_name = $courserecord->bot_name;
            $intentid = explode('_', $bot_name)[1];
        }

        $data = array(
            "intentid" => $intentid,
            "filename" => $filename,
            "filecontent" => $encoded_content,
        );
        return $data;
    }

    /**
     * Copies a file from the draft area to a temporary folder for LLM upload.
     *
     * @param int $contextid The context ID.
     * @param int $courseid The course ID.
     * @return string The path of the copied file.
     * @throws Exception If the directory creation or file copy fails.
     */
    public static function copy_file_to_temp_folder($contextid, $courseid)
    {
        global $CFG;
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid, 'block_ai_assistant', 'syllabus', $courseid);

        if ($files) {
            $file = reset($files);
            $temppath = $CFG->dataroot . '/temp/' . $courseid . '/cria';

            // Check if the directory exists, create it if it doesn't
            if (!is_dir($temppath)) {
                if (!mkdir($temppath, 0777, true)) {
                    throw new Exception("Failed to create directory: $temppath");
                }
            }

            // Define the file path and copy content
            $filepath = $temppath . '/' . $file->get_filename();
            $file->copy_content_to($filepath);
            return $filepath;
        }
    }
    /**
     * Returns default system message
     * @param int $course_id
     * @return array|string|string[]
     * @throws \dml_exception
     */
    public static function get_default_system_message($course_id)
    {
        global $DB;
        $course_data = $DB->get_record('course', array('id' => $course_id));
        $config = get_config('block_ai_assistant');
        $system_message = $config->system_message;
        // Replace the [course_number] with the shortname of the course
        $system_message = str_replace('[course_number]', $course_data->shortname, $system_message);
        // Replace the [course_title] with the fullname of the course
        $system_message = str_replace('[course_title]', $course_data->fullname, $system_message);
        return $system_message;
    }

    /**
     * Returns the default no_context_message
     */
    public static function get_default_no_context_message()
    {
        $config = get_config('block_ai_assistant');
        return $config->no_context_message;
    }

    /**
     * Get teachers in the course
     * @param int $course_id
     */
    public static function get_teachers($course_id)
    {
        $context = \context_course::instance($course_id);
        $teachers = get_users_by_capability($context, 'block/ai_assistant:teacher', 'u.id, u.firstname, u.lastname, u.email', 'u.lastname, u.firstname');
        return array_values($teachers);
    }

    /**
     * Get teacher emails in the course
     * @param int $course_id
     */
    public static function get_teacher_emails($course_id)
    {
        $teachers = self::get_teachers($course_id);
        $emails = array();
        foreach ($teachers as $teacher) {
            $emails[] = $teacher->email;
        }
        return $emails;
    }
}
