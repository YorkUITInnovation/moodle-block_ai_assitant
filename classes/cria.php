<?php

namespace block_ai_assistant;


use block_ai_assistant\webservice;
use Exception;

class cria
{

    /**
     * Create bot instance and returns bot metadata.
     * @param int $course_id
     * @return string bot_name or JSON payload with name/bot_id/bot_api_key for Criabot
     */
    public static function create_bot_instance($course_id)
    {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $data = self::get_create_cria_bot_config($course_id);
        $bot_name = (string)($data['name'] ?? '');

        $block = get_config('block_ai_assistant');
        $local = get_config('local_cria');

        $get = static function ($obj, string $key): string {
            if (is_object($obj) && isset($obj->{$key}) && (string)$obj->{$key} !== '') {
                return (string)$obj->{$key};
            }
            return '';
        };

        $criabot_url = rtrim($get($local, 'criabot_url') ?: $get($block, 'criabot_url'), '/');
        $api_key = $get($local, 'criadex_api_key') ?: $get($block, 'criadex_api_key');

        if ($criabot_url !== '' && $api_key !== '' && $bot_name !== '') {
            $create_body = [
                'llm_model_id' => (int)($data['model_id'] ?? 0),
                'embedding_model_id' => (int)($data['embedding_id'] ?? 0),
                'rerank_model_id' => (int)($data['rerank_model_id'] ?? 0),
                'parent_bot_names' => array_values(array_filter(array_map('trim', explode(',', (string)($data['child_bots'] ?? ''))))),
            ];

            $curl = new \curl();
            $common_headers = [
                'Accept: application/json',
                'Content-Type: application/json',
                'X-API-Key: ' . $api_key,
            ];

            $create_url = $criabot_url . '/bots/' . rawurlencode($bot_name) . '/manage/create';
            $create_raw = (string)$curl->post($create_url, json_encode($create_body), [
                'CURLOPT_TIMEOUT' => 60,
                'CURLOPT_HTTPHEADER' => $common_headers,
            ]);
            $created = json_decode($create_raw, true);

            if (is_array($created) && (int)($created['status'] ?? 0) === 409) {
                $delete_url = $criabot_url . '/bots/' . rawurlencode($bot_name) . '/manage/delete';
                $curl->post($delete_url, '', [
                    'CURLOPT_TIMEOUT' => 60,
                    'CURLOPT_CUSTOMREQUEST' => 'DELETE',
                    'CURLOPT_HTTPHEADER' => [
                        'Accept: application/json',
                        'X-API-Key: ' . $api_key,
                    ],
                ]);

                $create_raw = (string)$curl->post($create_url, json_encode($create_body), [
                    'CURLOPT_TIMEOUT' => 60,
                    'CURLOPT_HTTPHEADER' => $common_headers,
                ]);
                $created = json_decode($create_raw, true);
            }

            $bot_api_key = is_array($created) ? (string)($created['bot_api_key'] ?? '') : '';
            $create_status = is_array($created) ? (int)($created['status'] ?? 0) : 0;
            $create_code = is_array($created) ? (string)($created['code'] ?? '') : '';
            $create_message = is_array($created) ? (string)($created['message'] ?? '') : '';

            $update_body = [
                'max_input_tokens' => (int)($data['max_context'] ?? 2000),
                'max_reply_tokens' => (int)($data['max_tokens'] ?? 1024),
                'temperature' => (float)($data['temperature'] ?? 0.9),
                'top_p' => (float)($data['top_p'] ?? 0),
                'top_k' => (int)($data['top_k'] ?? 10),
                'min_k' => (float)($data['min_k'] ?? 0.5),
                'top_n' => (int)($data['top_n'] ?? 3),
                'min_n' => (float)($data['min_relevance'] ?? 0.7),
                'no_context_message' => (string)($data['no_context_message'] ?? ''),
                'no_context_use_message' => (bool)($data['no_context_use_message'] ?? false),
                'no_context_llm_guess' => (bool)($data['no_context_llm_guess'] ?? false),
                'system_message' => (string)($data['bot_system_message'] ?? ''),
            ];

            $curl->post($criabot_url . '/bots/' . rawurlencode($bot_name) . '/manage/update', json_encode($update_body), [
                'CURLOPT_TIMEOUT' => 60,
                'CURLOPT_CUSTOMREQUEST' => 'PATCH',
                'CURLOPT_HTTPHEADER' => $common_headers,
            ]);

            $about_raw = (string)$curl->get($criabot_url . '/bots/' . rawurlencode($bot_name) . '/manage/about', [], [
                'CURLOPT_TIMEOUT' => 30,
                'CURLOPT_HTTPHEADER' => [
                    'Accept: application/json',
                    'X-API-Key: ' . $api_key,
                ],
            ]);

            $about = json_decode((string)$about_raw, true);
            $bot_id = 0;
            if (is_array($about)) {
                $bot_id = (int)($about['about']['info']['id'] ?? 0);
            }

            return json_encode([
                'name' => $bot_name,
                'bot_id' => $bot_id,
                'bot_api_key' => $bot_api_key,
                'status' => $create_status,
                'code' => $create_code,
                'message' => $create_message,
            ]);
        }

        $method = 'cria_create_bot';
        $bot = webservice::exec($method, $data);
        return str_replace('"', '', self::get_bot_name_intent_id($bot));
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
     * Checks to see if cria is in maintenance mode
     * @return stdClass object
     * @return int
     */
    public static function get_availability() {
        $method = 'cria_get_availability';
        $data = array();
        $availability = json_decode(webservice::exec($method, $data));

        if (is_array($availability)) {
            $data = new \stdClass();
            $data->exception = $availability[0]->exception;
            $data->errorcode = $availability[0]->errorcode;
            $data->message = $availability[0]->message;
            return $data;
        } else if (is_object($availability)) {
            return $availability;
        } else {
            $data = new \stdClass();
            $data->exception = 'error';
            $data->errorcode = '404: Site unavailable';
            $data->message = 'Cria is currently unreachable. Please try again later.';
            return $data;
        }

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
            $bot_contact = '';
            $bot_help_text = '';
        } else {
            // Set variables
            $subtitle = $block_settings->subtitle;
            $welcome_message = $block_settings->welcome_message;
            $no_context_message = $block_settings->no_context_message;
            $embed_position = $block_settings->embed_position;
            $bot_contact = $block_settings->bot_contact;
            $bot_help_text = $block_settings->bot_help_text;
            // Parsing strategy is based on if this is a syllabus
            if ($is_syllabus) {
                if ($block_settings->lang == 'fr') {
                    $parsing_strategy = 'GENERIC';
                } else {
                    $parsing_strategy = 'GENERIC';
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
            'publish' => 0,
            'bot_contact' => $bot_contact,
            'bot_help_text' => $bot_help_text
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
        global $CFG, $DB;
        require_once($CFG->libdir . '/filelib.php');

        $block = get_config('block_ai_assistant');
        $local = get_config('local_cria');

        $get = static function ($obj, string $key): string {
            if (is_object($obj) && isset($obj->{$key}) && (string)$obj->{$key} !== '') {
                return (string)$obj->{$key};
            }
            return '';
        };

        $criabot_url = rtrim($get($local, 'criabot_url') ?: $get($block, 'criabot_url'), '/');
        $criaparse_url = rtrim($get($local, 'criaparse_url') ?: $get($block, 'criaparse_url'), '/');
        $api_key = $get($local, 'criadex_api_key') ?: $get($block, 'criadex_api_key');
        $llm_model_id = (int)($get($local, 'criadex_model_id') ?: $get($block, 'criadex_model_id'));
        $embedding_model_id = (int)($get($local, 'criadex_embed_id') ?: $get($block, 'criadex_embed_id'));
        $rerank_model_id = (int)($get($local, 'criadex_rerank_id') ?: $get($block, 'criadex_rerank_id'));
        $criadex_url = rtrim($get($local, 'criadex_url') ?: $get($block, 'criadex_url'), '/');

        if ($criabot_url !== '' && $criaparse_url !== '' && $api_key !== '') {
            $bot_name = $DB->get_field('block_aia_settings', 'bot_name', ['courseid' => $course_id]);
            if (!$bot_name) {
                return '';
            }

            $tmp_dir = make_temp_directory('block_ai_assistant/' . $course_id);
            $tmp_path = $tmp_dir . '/' . $file_name;
            file_put_contents($tmp_path, base64_decode($file_content));

            $strategy = $parsing_strategy ?: 'GENERIC';
            // CriaParse expects a valid string `dataset_id` for SemanticDocumentParser.
            // Using `course_id` keeps it stable and avoids invalid dataset/index names.
            $dataset_id = (string)$course_id;

            // Ensure the Criadex/Ragflow group exists before queuing parsing.
            // CriaParse uploads parsed content into this group; if missing, the job fails with GROUP_NOT_FOUND.
            if ($criadex_url !== '' && $llm_model_id > 0 && $embedding_model_id > 0) {
                $curl = new \curl();

                $group_name = rawurlencode($dataset_id);
                $create_group_url = $criadex_url . '/groups/' . $group_name . '/create';
                $create_group_body = [
                    'type' => 'DOCUMENT',
                    'llm_model_id' => $llm_model_id,
                    'embedding_model_id' => $embedding_model_id,
                    'rerank_model_id' => $rerank_model_id,
                ];

                $create_opts = [
                    'CURLOPT_TIMEOUT' => 30,
                    'CURLOPT_HTTPHEADER' => [
                        'Accept: application/json',
                        'Content-Type: application/json',
                        'X-API-Key: ' . $api_key,
                    ],
                ];
                try {
                    $curl->post($create_group_url, json_encode($create_group_body), $create_opts);
                    $info = $curl->get_info();
                    $status = isset($info['http_code']) ? (int)$info['http_code'] : 0;
                    if ($status !== 200 && $status !== 409) {
                        // If group creation fails for reasons other than "already exists", stop early.
                        return '';
                    }
                } catch (\Throwable $e) {
                    return '';
                }

                // Best-effort: authorize current API key against the created group.
                // (If the key is master this is effectively redundant, but safe.)
                $group_auth_url = $criadex_url . '/group_auth/' . $group_name . '/create?api_key=' . rawurlencode($api_key);
                $auth_opts = [
                    'CURLOPT_TIMEOUT' => 30,
                    'CURLOPT_HTTPHEADER' => [
                        'Accept: application/json',
                        'Content-Type: application/json',
                        'X-API-Key: ' . $api_key,
                    ],
                ];
                try {
                    $curl->post($group_auth_url, '', $auth_opts);
                    $info = $curl->get_info();
                    $status = isset($info['http_code']) ? (int)$info['http_code'] : 0;
                    // Accept 200/409; ignore other failures.
                    if ($status !== 200 && $status !== 409) {
                        // Don't fail hard; parsing may still succeed if master key bypasses auth.
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            $queue_url = $criaparse_url . '/parser/queue?strategy=' . rawurlencode($strategy) . '&dataset_id=' . rawurlencode($dataset_id);
            if ($llm_model_id > 0) {
                $queue_url .= '&llm_model_id=' . rawurlencode((string)$llm_model_id);
            }
            if ($embedding_model_id > 0) {
                $queue_url .= '&embedding_model_id=' . rawurlencode((string)$embedding_model_id);
            }

            $curl = new \curl();
            $queue_opts = [
                'CURLOPT_TIMEOUT' => 120,
                'CURLOPT_HTTPHEADER' => [
                    'Accept: application/json',
                    'x-api-key: ' . $api_key
                ]
            ];
            $queue_params = [
                'file' => new \CURLFile($tmp_path)
            ];

            $queue_raw = (string)$curl->post($queue_url, $queue_params, $queue_opts);
            $queued = json_decode($queue_raw, true);
            $job_id = is_array($queued) ? (string)($queued['job']['job_id'] ?? '') : '';
            if ($job_id === '') {
                return '';
            }

            $poll_url = $criaparse_url . '/parser/poll?job_id=' . rawurlencode($job_id);
            $poll_opts = [
                'CURLOPT_TIMEOUT' => 30,
                'CURLOPT_HTTPHEADER' => [
                    'Accept: application/json',
                    'x-api-key: ' . $api_key
                ]
            ];

            $nodes = [];
            $assets = [];
            $deadline = time() + 180;
            while (time() < $deadline) {
                $poll_raw = (string)$curl->get($poll_url, [], $poll_opts);
                $polled = json_decode($poll_raw, true);
                $job = is_array($polled) ? ($polled['job'] ?? null) : null;
                if (!is_array($job)) {
                    sleep(1);
                    continue;
                }

                if (!empty($job['finished'])) {
                    $response = $job['response'] ?? null;
                    if (is_array($response)) {
                        $nodes = $response['elements'] ?? [];
                        $assets = $response['assets'] ?? [];
                    }
                    break;
                }
                sleep(1);
            }

            if (empty($nodes) && empty($assets)) {
                return '';
            }

            $upload_body = [
                'file_name' => $file_name,
                'file_contents' => [
                    'nodes' => $nodes,
                    'assets' => $assets
                ],
                'file_metadata' => new \stdClass()
            ];

            $upload_url = $criabot_url . '/bots/' . rawurlencode($bot_name) . '/documents/upload';
            $upload_opts = [
                'CURLOPT_TIMEOUT' => 120,
                'CURLOPT_HTTPHEADER' => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'X-API-Key: ' . $api_key
                ]
            ];
            $upload_raw = (string)$curl->post($upload_url, json_encode($upload_body), $upload_opts);

            $uploaded = json_decode((string)$upload_raw, true);
            if (!is_array($uploaded) || ($uploaded['status'] ?? null) !== 200) {
                return '';
            }

            $document_name = (string)($uploaded['document_name'] ?? '');
            if ($document_name !== '') {
                $DB->set_field('block_aia_settings', 'syllabus_document_name', $document_name, ['courseid' => $course_id]);
                $DB->set_field('block_aia_settings', 'syllabus_trained', 1, ['courseid' => $course_id]);
            }

            return $document_name;
        }

        $method = 'cria_content_upload';
        $data = [
            "intentid" => (int)self::get_intent_id($course_id),
            "filename" => $file_name,
            "filecontent" => $file_content,
            "parsingstrategy" => $parsing_strategy
        ];
        return webservice::exec($method, $data);
    }

    /**
     * Get content training status
     * @param int $contentid
     * @return int
     */
    public static function get_content_training_status($contentid)
    {
        $method = 'cria_content_get_training_status';
        $data = array("id" => $contentid);
        $status = webservice::exec($method, $data);
        $results = new \stdClass();

        switch ($status) {
            case 0:
                $training_status_id = 0;
                $training_status = '<div class="badge badge-warning">'
                    . get_string('pending', 'block_ai_assistant') . '</div>';
                break;
            case 1:
                $training_status_id = 1;
                $training_status = '<div class="badge badge-success">'
                    . get_string('trained', 'block_ai_assistant') . '</div>';
                break;
            case 2:
                $training_status_id = 2;
                $training_status = '<div class="badge badge-danger">'
                    . get_string('error', 'block_ai_assistant') . '</div>';
                break;
            case 3:
                $training_status_id = 3;
                $training_status = '<div class="badge badge-info">'
                    . get_string('training', 'block_ai_assistant') . '</div>';
                break;
        }

        $results->training_status_id = $training_status_id;
        $results->training_status = $training_status;

        return $results;
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
     * Create small talk questions
     * Get intent id
     * @param int $course_id
     * @return int
     */
    public static function create_small_talk_questions($course_id)
    {
        // Get intent_id
        $intent_id = self::get_intent_id($course_id);
        $config = get_config('block_ai_assistant');
        // Set small talk questions
        $small_talk_questions = [
            [
                'name' => 'Small talk - Hello',
                'value' => 'Hello',
                'answer' => 'Hello! How can I help you today?',
                'examples' => [
                    ['value' => 'Hi'],
                    ['value' => 'Hey'],
                    ['value' => 'Greetings'],
                    ['value' => 'Good morning'],
                    ['value' => 'Good afternoon'],
                    ['value' => 'Good evening']
                ]
            ],
            [
                'name' => 'Smalltalk - How are you?',
                'value' => 'How are you?',
                'answer' => 'I am an AI assistant. How can I help you today?',
                'examples' => [
                    ['value' => 'How are you?'],
                    ['value' => 'How are you doing?'],
                    ['value' => 'How do you do?'],
                    ['value' => 'How are you feeling?'],
                    ['value' => 'How are you today?']
                ]

            ],
            [
                'name' => 'Small talk - Good bye',
                'value' => 'Good bye',
                'answer' => 'Good bye! Have a great day! If you need help, feel free to ask.',
                'examples' => [
                    ['value' => 'Bye'],
                    ['value' => 'Goodbye'],
                    ['value' => 'See you later'],
                    ['value' => 'See you soon'],
                    ['value' => 'Take care']
                ]
            ],
            [
                'name' => 'Small talk - Thank you',
                'value' => 'Thank you',
                'answer' => 'You are welcome! If you need help, feel free to ask.',
                'examples' => [
                    ['value' => 'Thanks'],
                    ['value' => 'Thank you very much'],
                    ['value' => 'Thank you so much'],
                    ['value' => 'Gracias'],
                    ['value' => 'Merci']
                ]
            ],
            [
                'name' => 'Small talk - Who are you?',
                'value' => 'Who are you?',
                'answer' => 'I am an AI assistant. How can I help you today?',
                'examples' => [
                    ['value' => 'Who are you?'],
                    ['value' => 'What are you?'],
                    ['value' => 'What is your name?'],
                    ['value' => 'What do you do?'],
                    ['value' => 'What can you do?']
                ]
            ],
            [
                'name' => 'Small talk - Where are you from?',
                'value' => 'Where are you from?',
                'answer' => 'I am an AI assistant. How can I help you today?',
                'examples' => [
                    ['value' => 'Where are you from?'],
                    ['value' => 'Where do you come from?'],
                    ['value' => 'Where were you born?'],
                    ['value' => 'Where do you live?'],
                    ['value' => 'Where do you reside?']
                ]
            ],
            [
                'name' => 'Small talk - What is your name?',
                'value' => 'What is your name?',
                'answer' => 'My name is ' . $config->title . ' . How can I help you today?',
                'examples' => [
                    ['value' => 'What is your name?'],
                    ['value' => 'What do you call yourself?'],
                    ['value' => 'What should I call you?'],
                    ['value' => 'What are you called?'],
                    ['value' => 'What is your title?']
                ]
            ],
            [
                'name' => 'Small talk - What do you do?',
                'value' => 'What do you do?',
                'answer' => 'I am an AI assistant. How can I help you today?',
                'examples' => [
                    ['value' => 'What do you do?'],
                    ['value' => 'What is your job?'],
                    ['value' => 'What is your role?'],
                    ['value' => 'What is your function?'],
                    ['value' => 'What is your purpose?']
                ]
            ],
            [
                'name' => 'Small talk - What can you do?',
                'value' => 'What can you do?',
                'answer' => 'I am an AI assistant. How can I help you today?',
                'examples' => [
                    ['value' => 'What can you do?'],
                    ['value' => 'What are your capabilities?'],
                    ['value' => 'What are your functions?'],
                    ['value' => 'What are your features?'],
                    ['value' => 'What are your abilities?']
                ]
            ],
            [
                'name' => 'Small talk - What are you doing?',
                'value' => 'What are you doing?',
                'answer' => 'I am an AI assistant. How can I help you today?',
                'examples' => [
                    ['value' => 'What are you doing?'],
                    ['value' => 'What are you up to?'],
                    ['value' => 'What are you working on?'],
                    ['value' => 'What are you busy with?'],
                    ['value' => 'What are you occupied with?']
                ]
            ],
            [
                'name' => 'Small talk - Are you dumb?',
                'value' => 'Are you dumb?',
                'answer' => 'I am an AI assistant trained on specific information based on the syllbus the instructor provided. How can I help you today?',
                'examples' => [
                    ['value' => 'Hey dummy!'],
                    ['value' => 'Are you stupid?'],
                    ['value' => 'Are you intelligent?'],
                    ['value' => 'Are you smart?'],
                    ['value' => 'Are you clever?']
                ]
            ]
        ];
        // Create and publish small talk questions
        foreach ($small_talk_questions as $question) {
            $question_obj = [
                'intentid' => $intent_id,
                'name' => $question['name'],
                'value' => $question['value'],
                'answer' => $question['answer'],
                'relatedquestions' => json_encode([]),
                'lang' => 'en',
                'generateanswer' => 1,
                'examplequestions' => json_encode($question['examples'])
            ];
            $question_id = self::create_question($question_obj);
            if ($question_id) {
                $published_question = self::publish_question($question_id);
                if ($published_question != 1) {
                    continue;
                } else {
                    return false;
                }
            } else {
                continue;
            }

        }

        return true;
    }

    /**
     * Get embed bot code
     * @param int $bot_id
     * @return string
     */
    public static function get_embed_bot_code($bot_name)
    {
        $config = get_config('block_ai_assistant');
        $embed_code = '';
        if (!empty($config->cria_embed_url)) {
            $embed_code = '<script type="text/javascript" src="' . $config->cria_embed_url . '/embed/' . $bot_name . '/load" async> </script>';
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
     * Delete question on cria server
     * @param $question_id
     * @return mixed
     */
    public static function delete_question($question_id)
    {
        $method = 'cria_question_delete';
        $data = array('id' => $question_id);
        $status = webservice::exec($method, $data);
        return $status;
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
     * Check if chat exists
     * @param $chat_id
     * @return object
     */
    public static function chat_exists($chat_id)
    {
        $method = 'cria_chat_exists';
        $data = array(
            'chat_id' => trim($chat_id)
        );
        $chat_exists = webservice::exec($method, $data);
        return (object)json_decode($chat_exists, true);
    }

    /**
     * @return mixed
     */
    public static function chat_start()
    {
        $method = 'cria_chat_start';
        $data = array();
        $chat_id = webservice::exec($method, $data);
        $resp = json_decode($chat_id, true);
        if (is_array($resp) && isset($resp['chat_id'])) {
            return (string)$resp['chat_id'];
        }
        return '';
    }

    /**
     * Send a message to the chat
     * @param string $chat_id
     * @param string $prompt
     * @param string $bot_name
     * @return mixed
     */
    public static function chat_send(string $chat_id, string $prompt, string $bot_name)
    {
        $method = 'cria_chat_send';
        $data = array(
            'bot_name' => $bot_name,
            'chat_id' => trim($chat_id),
            'prompt' => $prompt
        );
        $response = webservice::exec($method, $data);
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return '';
        }

        if (($decoded['status'] ?? null) === 200) {
            $reply = $decoded['reply'] ?? null;
            if (is_array($reply) && isset($reply['message'])) {
                return (string)$reply['message'];
            }
            if (is_string($reply)) {
                return $reply;
            }
            return '';
        }

        $status = (string)($decoded['status'] ?? '');
        $code = (string)($decoded['code'] ?? '');
        $message = (string)($decoded['message'] ?? '');
        return trim($status . ' ' . ($code ?: $message));
    }

    /**
     * Get chat history
     * @param string $chat_id
     * @return mixed
     */
    public static function chat_history(string $chat_id)
    {
        $method = 'cria_chat_history';
        $data = array(
            'chat_id' => trim($chat_id)
        );
        $response = webservice::exec($method, $data);
        $response = (object)json_decode($response, true);
        return $response;
    }

    /**
     * End chat session
     * @param string $chat_id
     * @return mixed
     */
    public static function chat_end(string $chat_id)
    {
        $method = 'cria_chat_end';
        $data = array(
            'chat_id' => trim($chat_id)
        );
        $response = webservice::exec($method, $data);
        return json_decode($response);
    }

    public static function gradebook_start(int $courseid, int $professorid): string
    {
        global $DB;
        $bot_name = (string)$DB->get_field('block_aia_settings', 'bot_name', ['courseid' => $courseid]);
        if ($bot_name === '') {
            return json_encode([
                'status' => 500,
                'code' => 'BOT_NOT_CONFIGURED',
                'message' => 'Bot is not configured for this course.',
            ]);
        }

        $activities = [];
        $resources = [];
        try {
            $modinfo = get_fast_modinfo($courseid);
            foreach ($modinfo->get_cms() as $cm) {
                if (!$cm->uservisible) {
                    continue;
                }
                $activities[] = [
                    'cmid' => (int)$cm->id,
                    'module' => (string)$cm->modname,
                    'name' => (string)$cm->name,
                ];
                if (in_array((string)$cm->modname, ['resource', 'page', 'book', 'folder', 'label'], true)) {
                    $resources[] = [
                        'cmid' => (int)$cm->id,
                        'type' => (string)$cm->modname,
                        'name' => (string)$cm->name,
                        'content_url' => (string)$cm->url,
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Fallback: leave arrays empty; backend will handle intake mode.
        }

        $method = 'cria_gradebook_start';
        $data = array(
            'course_id' => (string)$courseid,
            'professor_id' => (string)$professorid,
            'bot_name' => $bot_name,
            'moodle_resources' => $resources,
            'course_activities' => $activities,
        );
        return webservice::exec($method, $data);
    }

    public static function gradebook_chat(string $session_id, string $prompt): string
    {
        $method = 'cria_gradebook_chat';
        $data = array(
            'session_id' => trim($session_id),
            'prompt' => $prompt,
        );
        return webservice::exec($method, $data);
    }

    public static function gradebook_proposal(string $session_id): string
    {
        $method = 'cria_gradebook_proposal';
        $data = array(
            'session_id' => trim($session_id),
        );
        return webservice::exec($method, $data);
    }

    public static function gradebook_status(string $session_id): string
    {
        $method = 'cria_gradebook_status';
        $data = array(
            'session_id' => trim($session_id),
        );
        return webservice::exec($method, $data);
    }

    public static function gradebook_accept(string $session_id): string
    {
        $method = 'cria_gradebook_accept';
        $data = array(
            'session_id' => trim($session_id),
        );
        return webservice::exec($method, $data);
    }

    public static function gradebook_finalize(string $session_id, array $confirmed_mapping): string
    {
        $method = 'cria_gradebook_finalize';
        $data = array(
            'session_id' => trim($session_id),
            'confirmed_mapping' => $confirmed_mapping,
            'create_categories' => true,
            'reorganize_resources' => false,
        );
        return webservice::exec($method, $data);
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
//        exec("php $CFG->dirroot/blocks/ai_assistant/cli/autotest.php -cid=$course_id > /dev/null 2>&1 &");
        $handle = popen("php $CFG->dirroot/blocks/ai_assistant/cli/autotest.php -cid=$course_id", 'r');
        pclose($handle);
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
        $bot_info->bot_id = (int)($block_settings->bot_id ?? 0);
        $bot_info->intent_id = 0;
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

    /**
     * Get bot api key
     * @param $chat_id
     * @param $bot_id
     * @param $prompt
     * @param $content
     * @return string
     */
    public static function get_api_key($bot_id): string
    {
        $method = 'cria_get_bot_api_key';
        $data = array(
            'bot_id' => (int)$bot_id,
        );
        $response = webservice::exec($method, $data);
        // Remove "quotes from response
        $response = str_replace('"', '', $response);
        return $response;
    }


    /**
     * @param $course_id
     * @param $payload
     * @return string
     */
    public static function start_session($course_id, $api_key, $payload = "{}"): string
    {
        $session = webservice::exec_embed(
            (int)self::get_bot_id($course_id),
            $api_key,
            json_encode($payload)
        );
        return $session;
    }

}