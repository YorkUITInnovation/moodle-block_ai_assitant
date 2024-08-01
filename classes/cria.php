<?php

namespace block_ai_assistant;


use block_ai_assistant\webservice;
use Exception;

require_once($CFG->libdir . '/phpspreadsheet/vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class cria
{

    /**
     * Create bot instance and returns bot_name
     * @param int $course_id
     * @return string bot_name
     */
    public static function create_bot_instance($course_id)
    {
        $method = 'cria_create_bot';
        $data = self::get_create_cria_bot_config($course_id);

        $bot = webservice::exec($method, $data);
        $bot_name = self::get_bot_name_intent_id($bot);
        return $bot_name;
    }


    /**
     * Update bot instance and returns bot_name
     * @param int $course_id , $bot_id
     * @return string Message
     */
    public static function update_bot_instance($course_id, $botid)
    {
        $method = 'cria_create_bot';

        $data = self::get_create_cria_bot_config($course_id);
        $data['id'] = $botid;

        $updated_bot_name = webservice::exec($method, $data);

        return $updated_bot_name;
    }

    /**
     * Delete bot instance
     * @param int $course_id , $bot_id
     * @return string Message
     */
    public static function delete_bot_instance($botid)
    {
        $method = 'cria_bot_delete';

        $data = array();
        $data['id'] = $botid;

        $delete_bot_name = webservice::exec($method, $data);

        return $delete_bot_name;
    }


    /**
     * Create bot instance and returns bot_name
     * @param int $bot_name
     * @return string bot_name-intentId
     */

    public static function get_bot_name_intent_id($bot_name)
    {
        $method = 'cria_get_bot_name';
        $data = array('bot_id' => $bot_name);
        $bot_name_intent_id = webservice::exec($method, $data);
        return $bot_name_intent_id;
    }

    /**
     * Returns the config for creating bot instance
     * @param int $course_id
     * @param bool $is_syllabus
     * @return array
     */
    private static function get_create_cria_bot_config($course_id, $is_syllabus = true)
    {
        global $CFG, $DB;
        // Get the site
        $site = get_site();
        // Set parameters
        $context = \context_course::instance($course_id);
        $config = get_config('block_ai_assistant');
        $course_data = $DB->get_record('course', array('id' => $course_id));
        if (!$block_settings = $DB->get_record('block_aia_settings', array('courseid' => $course_id))) {
            // Set variables
            $subtitle = $config->subtitle;
            $welcome_message = $config->welcome_message;
            $no_context_message = $config->no_context_message;
            $embed_position = $config->embed_position;
            $parsing_strategy = $config->parse_strategy;
        } else {
            // Set variables
            $subtitle = $block_settings->subtitle;
            $welcome_message = $block_settings->welcome_message;
            $no_context_message = $block_settings->no_context_message;
            $embed_position = $block_settings->embed_position;
            // Parsing strategy is based on if this is a syllabus
            if ($is_syllabus) {
                // If block_settings lang is set to French, use ALSYLLABUS_FR
                // otherwise use ALSYLLABUS
                if ($block_settings->lang == 'fr') {
                    $parsing_strategy = 'ALSYLLABUSFR';
                } else {
                    $parsing_strategy = 'ALSYLLABUS';
                }
            }
        }
        $system_message = self::get_default_system_message($course_id);

        if ($course_data) {
            if ($course_data->idnumber != '') {
                $name = $course_data->idnumber;
            } else {
                $name = $course_data->shortname;
            }
        }
        // Get ai assistant logo
        $image = self::get_ai_assistant_logo();

        $data = array(
            'name' => $site->shortname . '-' . $name,
            'description' => $config->description,
            'bot_type' => $config->bot_type,
            'bot_system_message' => $system_message,
            'model_id' => $config->criadex_model_id,
            'embedding_id' => $config->criadex_embed_id,
            'rerank_model_id' => $config->criadex_rerank_id,
            'requires_content_prompt' => $config->requires_content_prompt,
            'requires_user_prompt' => $config->requires_user_prompt,
            'user_prompt' => $config->user_prompt,
            'welcome_message' => $welcome_message,
            'theme_color' => $config->theme_color,
            'max_tokens' => $config->max_tokens,
            'temperature' => $config->temperature,
            'top_p' => $config->top_p,
            'top_k' => $config->top_k,
            'top_n' => $config->top_n,
            'min_k' => $config->min_k,
            'min_relevance' => $config->min_relevance,
            'max_context' => $config->max_context,
            'no_context_message' => $no_context_message,
            'no_context_use_message' => $config->no_context_use_message,
            'no_context_llm_guess' => $config->no_context_llm_guess,
            'email' => implode('; ', self::get_teacher_emails($course_id)),
            'available_child' => $config->available_child,
            'parse_strategy' => $parsing_strategy,
            'botwatermark' => $config->botwatermark,
            'title' => $config->title,
            'subtitle' => $subtitle,
            'embed_position' => $embed_position,
            'icon_file_name' => $image->filename,
            'icon_file_content' => $image->filecontent,
            'bot_locale' => $config->bot_locale,
            'child_bots' => $config->child_bots,
            'publish' => 0
        );
        return $data;
    }

    public static function delete_content_from_bot($contentid)
    {
        $method = 'cria_content_delete';
        $data = array("id" => $contentid);
        $status = webservice::exec($method, $data);
        return $status;
    }

    /**
     * Uploads content to bot and returns file_id
     * @param string $file_path
     * @param int $course_id
     * @return int file_id
     */
    public static function upload_content_to_bot($course_id, $file_name, $file_content, $parsing_strategy = '')
    {
        $method = 'cria_content_upload';
        $data = [
            "intentid" => (int)self::get_intent_id($course_id),
            "filename" => $file_name,
            "filecontent" => $file_content,
            "parsingstrategy" => $parsing_strategy
        ];
        $file_id = webservice::exec($method, $data);
        return $file_id;
    }

    /**
     * Returns the config for uploading content to bot
     * @param string $file_path
     * @return array
     */
    public static function get_upload_content_to_bot_config($file_path)
    {
        global $DB;
        $file_content = file_get_contents($file_path);
        $encoded_content = base64_encode($file_content);
        $file_name = basename($file_path);
        // Set data
        $data = new \stdClass();
        $data->file_name = $file_name;
        $data->file_content = $encoded_content;
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
//            $file = reset($files);
            $temppath = $CFG->dataroot . '/temp/' . $courseid . '/cria';

            // Check if the directory exists, create it if it doesn't
            if (!is_dir($temppath)) {
                if (!mkdir($temppath, 0777, true)) {
                    throw new Exception("Failed to create directory: $temppath");
                }
            }

            foreach ($files as $file) {
                if ($file->get_filesize() > 0) { // Ensures it's not a directory
                    $filepath = $temppath . '/' . $file->get_filename();
                    $file->copy_content_to($filepath);
                    return $filepath;
                }
            }
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

    /**
     * Get image ai_assistant.png and convert the content to base64
     * @return stdClass
     */
    public static function get_ai_assistant_logo()
    {
        global $CFG;
        $image_data = new \stdClass();
        $path = $CFG->dirroot . '/blocks/ai_assistant/pix/ai_assistant.png';
        $file_content = file_get_contents($path);
        $encoded_content = base64_encode($file_content);

        $image_data->filename = 'ai_assistant.png';
        $image_data->filecontent = $encoded_content;

        return $image_data;
    }

    /**
     * Get embed bot code
     * @param int $bot_id
     * @return string
     */
    public static function get_embed_bot_code($bot_id)
    {
        $config = get_config('block_ai_assistant');
        $embed_code = '';
        if (!empty($config->cria_embed_url)) {
            $embed_code = '<script type="text/javascript" src="' . $config->cria_embed_url . '/embed/' . $bot_id . '/load" async> </script>';
        }
        return $embed_code;
    }

    /**
     * Get questions in json format
     * @param int $course_id
     */
    public static function get_question_json_format($file_content)
    {
        ///replace all of the below dummy value with the original api call

        $json_file_path = 'AL_questions.json';
        $json_content = file_get_contents($json_file_path);
        $json_question_obj = json_decode($json_content, true);
        return $json_question_obj;
    }

    /**
     * Create a question
     * @param object $question_obj
     * @return int $question_id
     */
    public static function create_question($question_obj)
    {
        $method = 'cria_question_create';
        $question_id = webservice::exec($method, $question_obj);
        return $question_id;
    }

    /**
     * Publish a question to the bot
     * @param int $question_id
     * @return boolean $status
     */
    public static function publish_question($question_id)
    {
        $method = 'cria_question_publish';
        $data = array('id' => $question_id);
        $status = webservice::exec($method, $data);
        return $status;
    }

    /**
     * Parse json object retuned from get_question_json_format
     * @param object $jsonObj
     * @return array $questions
     */
    public static function create_questions_from_json($json_question_obj, $courseid)
    {
        foreach ($json_question_obj as $key => $question_data) {

            $name = $key;
            $value = $question_data['question'];
            $answer = $question_data['answer'];
            $related_questions = array();
            $lang = 'en';
            $generate_answer = 0;
            $example_questions = array_map(function ($example) {
                return array('value' => $example);
            }, $question_data['examples']);

            $question_obj = [
                'intentid' => (int)self::get_intent_id($courseid),
                'name' => $name,
                'value' => $value,
                'answer' => $answer,
                'relatedquestions' => json_encode($related_questions),
                'lang' => $lang,
                'generateanswer' => $generate_answer,
                'examplequestions' => json_encode($example_questions)
            ];
            $question_id = cria::create_question($question_obj);

            $status = cria::publish_question($question_id);

            if ($status) {
                $question_data = [
                    'courseid' => $courseid,
                    'name' => $name,
                    'value' => $value,
                    'answer' => $answer,
                    'criaquestionid' => intval($question_id),
                    'related_questions' => json_encode($related_questions)
                ];
                self::update_questions_db($question_data);
            }
        }
    }

    private static function update_questions_db($question_data)
    {
        global $DB;
        $question_id = $question_data['criaquestionid'];
        $question_record = $DB->get_record('block_aia_questions', array('criaquestionid' => $question_id));

        if ($question_record) {
            $questionData['id'] = $question_record->id;
            $DB->update_record('block_aia_questions', $question_data);
        } else {
            $DB->insert_record('block_aia_questions', $question_data);
        }
    }

    public static function create_questions_from_xlsx($file, $courseid)
    {
        global $CFG;

        // Extract the file content
        $content = $file->get_content();

        // Define directory and file paths
        $directory_path = $CFG->dataroot . '/temp/' . $courseid;
        $temp_file = $directory_path . '/questions_upload.xlsx';

        // Check if the directory exists, create it if it doesn't
        if (!is_dir($directory_path)) {
            if (!mkdir($directory_path, 0777, true)) {
                throw new Exception("Failed to create directory: $directory_path");
            }
        }

        // Save the content to the temporary file
        file_put_contents($temp_file, $content);

        // Load the spreadsheet from the temporary file
        try {
            $spreadsheet = IOFactory::load($temp_file);
        } catch (Exception $e) {
            throw new Exception("Failed to load spreadsheet: " . $e->getMessage());
        }

        $sheet = $spreadsheet->getActiveSheet();
        $highest_row = $sheet->getHighestRow();
        echo "Total Rows in Spreadsheet: " . $highest_row . "<br>";
        for ($row = 2; $row <= $highest_row; $row++) {
            echo "Processing Row: " . $row . "<br>";

            $name = $sheet->getCell('A' . $row)->getValue();
            $value = $sheet->getCell('B' . $row)->getValue();
            $answer = $sheet->getCell('C' . $row)->getValue();
            $related_questions = $sheet->getCell('D' . $row)->getValue();
            $lang = $sheet->getCell('E' . $row)->getValue();
            $generate_answer = $sheet->getCell('F' . $row)->getValue();
            $example_questions = $sheet->getCell('G' . $row)->getValue();

            // Debug output
            echo "Name: " . $name . "<br>";
            echo "Value: " . $value . "<br>";
            echo "Answer: " . $answer . "<br>";
            echo "Related Questions: " . $related_questions . "<br>";
            echo "Language: " . $lang . "<br>";
            echo "Generate Answer: " . $generate_answer . "<br>";
            echo "Example Questions: " . $example_questions . "<br>";

            // Prepare question data
            $questionObj = [
                'intentid' => (int)self::get_intent_id($courseid),
                'name' => $name,
                'value' => $value,
                'answer' => $answer,
                'relatedquestions' => $related_questions,
                'lang' => $lang,
                'generateanswer' => $generate_answer,
                'examplequestions' => $example_questions
            ];

            try {
                // Create and publish question
                $question_id = cria::create_question($questionObj);
                $status = cria::publish_question($question_id);

                if ($status) {
                    //autotest the bot:
                    $question_data = [
                        'courseid' => $courseid,
                        'name' => $name,
                        'value' => $value,
                        'answer' => $answer,
                        'criaquestionid' => intval($question_id),
                        'related_questions' => $related_questions
                    ];
                    self::update_questions_db($question_data);
                    echo "Question $name processed and saved.<br>";
                } else {
                    echo "Failed to publish question $name.<br>";
                }
            } catch (Exception $e) {
                echo "Error processing question $name: " . $e->getMessage() . "<br>";
            }
        }
    }

    /**
     * Get chat id from CRIA
     * @return mixed
     */
    public static function get_chat_id()
    {
        $method = 'cria_get_chat_id';
        $data = array();
        $chat_id = webservice::exec($method, $data);
        return $chat_id;
    }

    /**
     * Return chat response
     * @param $chat_id
     * @param $bot_id
     * @param $prompt
     * @param $content
     * @return mixed
     */
    public static function get_gpt_response($chat_id, $bot_id, $prompt, $content = '')
    {
        $method = 'cria_get_gpt_response';
        $data = array(
            'bot_id' => (int)$bot_id,
            'chat_id' => str_replace('"', '', $chat_id),
            'prompt' => $prompt,
            'content' => $content
        );
        $response = webservice::exec($method, $data);
        return $response;
    }

    /**
     * Run auto test
     * @return mixed
     */
    public static function run_autotest($course_id)
    {
        global $CFG;
        exec("php $CFG->dirroot/blocks/ai_assistant/cli/autotest.php -cid=$course_id > /dev/null 2>&1 &");
    }

    /**
     * Get bot name and intent id
     * @param $course_id
     * @return \stdClass
     */
    private static function split_bot_name($course_id)
    {
        global $DB;
        $block_settings = $DB->get_record('block_aia_settings', array('courseid' => $course_id));
        $bot_info = new \stdClass();
        $bot_name = explode('-', $block_settings->bot_name);
        $bot_info->bot_id = str_replace('"','', $bot_name[0]);
        $bot_info->intent_id = str_replace('"','', $bot_name[1]);
        return $bot_info;
    }

    /**
     * Get bot id
     * @param $course_id
     * @return string
     */
    public static function get_bot_id($course_id)
    {
        return self::split_bot_name($course_id)->bot_id;
    }

    /**
     * Get intent id
     * @param $course_id
     * @return string
     */
    public static function get_intent_id($course_id)
    {
        return self::split_bot_name($course_id)->intent_id;
    }

}