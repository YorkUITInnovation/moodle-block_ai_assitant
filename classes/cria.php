<?php

namespace block_ai_assistant;


use block_ai_assistant\webservice;
use Exception;
use Throwable;

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
     * Delete a document from Criabot by document name.
     *
     * @param int $course_id
     * @param string $document_name
     * @param string $index_type
     * @return bool
     */
    public static function delete_content_document_name_from_bot(int $course_id, string $document_name, string $index_type = 'documents'): bool
    {
        global $CFG, $DB;
        require_once($CFG->libdir . '/filelib.php');

        $document_name = trim($document_name);
        if ($document_name === '') {
            return false;
        }

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
        if ($criabot_url === '' || $api_key === '') {
            return false;
        }

        $bot_name = (string)$DB->get_field('block_aia_settings', 'bot_name', ['courseid' => $course_id]);
        if ($bot_name === '') {
            return false;
        }

        $path = ($index_type === 'questions') ? 'questions' : 'documents';
        $url = $criabot_url . '/bots/' . rawurlencode($bot_name) . '/' . $path
            . '/delete?document_name=' . rawurlencode($document_name);

        $curl = new \curl();
        $opts = [
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_CUSTOMREQUEST' => 'DELETE',
            'CURLOPT_HTTPHEADER' => [
                'Accept: application/json',
                'X-API-Key: ' . $api_key,
            ],
        ];

        try {
            $raw = (string)$curl->get($url, [], $opts);
            $info = $curl->get_info();
            $status = isset($info['http_code']) ? (int)$info['http_code'] : 0;
            if ($status === 200 || $status === 404) {
                return true;
            }

            // Backward-compatible idempotency: some deployments return 500 for
            // missing files even though delete outcome is effectively complete.
            if ($status === 500 && strpos($raw, 'FILE_NOT_FOUND') !== false) {
                return true;
            }

            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Uploads content to bot and returns file_id
     * @param string $file_path
     * @param int $course_id
     * @return int file_id
     */
    public static function upload_content_to_bot($course_id, $file_name, $file_content, $parsing_strategy = '', $persist_syllabus_metadata = false)
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
                error_log('block_ai_assistant: upload_content_to_bot failed, missing bot_name for course ' . (int)$course_id);
                return '';
            }

            $tmp_dir = make_temp_directory('block_ai_assistant/' . $course_id);
            $tmp_path = $tmp_dir . '/' . $file_name;
            file_put_contents($tmp_path, base64_decode($file_content));

            $strategy = $parsing_strategy ?: 'GENERIC';
            // Keep parser dataset aligned with the bot document group used by Criabot
            // so parser-side uploads do not target non-existent numeric groups.
            $dataset_id = (string)$bot_name . '-document-index';

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
                        error_log('block_ai_assistant: upload_content_to_bot failed creating dataset group ' . $dataset_id . ' status=' . $status);
                        return '';
                    }
                } catch (\Throwable $e) {
                    error_log('block_ai_assistant: upload_content_to_bot exception creating dataset group ' . $dataset_id . ' error=' . $e->getMessage());
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
                error_log('block_ai_assistant: upload_content_to_bot failed queueing parser job for course ' . (int)$course_id . ' response=' . substr($queue_raw, 0, 500));
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
                    // Back off when parser poll returns transient non-JSON/5xx payloads.
                    sleep(2);
                    continue;
                }

                if (!empty($job['finished'])) {
                    $response = $job['response'] ?? null;
                    if (is_array($response)) {
                        $raw_elements = $response['elements'] ?? [];
                        $raw_assets = $response['assets'] ?? [];
                        $nodes = self::normalize_criaparse_elements($raw_elements);
                        $assets = self::normalize_criaparse_assets($raw_assets);

                        // Defensive fallback: if parser output is not structured as elements,
                        // still push one minimal node to avoid hard 422 failures downstream.
                        if (empty($nodes)) {
                            $flat_text = trim((string)($response['text'] ?? $response['content'] ?? ''));
                            if ($flat_text !== '') {
                                $nodes = [[
                                    'text' => $flat_text,
                                    'metadata' => new \stdClass(),
                                    'type' => 'text',
                                ]];
                            }
                        }
                    }
                    break;
                }
                sleep(1);
            }

            if (empty($nodes) && empty($assets)) {
                error_log('block_ai_assistant: upload_content_to_bot parser returned no nodes/assets for course ' . (int)$course_id . ' job_id=' . $job_id);
                return '';
            }

            // Ensure bot-named group exists before uploading (required by Criabot)
            // Criabot will upload to {bot_name}-document-index group in Criadex
            if ($criadex_url !== '' && $llm_model_id > 0 && $embedding_model_id > 0) {
                $curl = new \curl();
                $bot_group_name = rawurlencode($bot_name . '-document-index');
                $create_bot_group_url = $criadex_url . '/groups/' . $bot_group_name . '/create';
                $create_bot_group_body = [
                    'type' => 'DOCUMENT',
                    'llm_model_id' => $llm_model_id,
                    'embedding_model_id' => $embedding_model_id,
                    'rerank_model_id' => $rerank_model_id,
                ];

                $create_bot_opts = [
                    'CURLOPT_TIMEOUT' => 30,
                    'CURLOPT_HTTPHEADER' => [
                        'Accept: application/json',
                        'Content-Type: application/json',
                        'X-API-Key: ' . $api_key,
                    ],
                ];
                try {
                    $curl->post($create_bot_group_url, json_encode($create_bot_group_body), $create_bot_opts);
                    $info = $curl->get_info();
                    $status = isset($info['http_code']) ? (int)$info['http_code'] : 0;
                    if ($status !== 200 && $status !== 409) {
                        error_log('block_ai_assistant: upload_content_to_bot failed creating bot group ' . $bot_name . '-document-index status=' . $status);
                        return '';
                    }
                } catch (\Throwable $e) {
                    error_log('block_ai_assistant: upload_content_to_bot exception creating bot group ' . $bot_name . ' error=' . $e->getMessage());
                    return '';
                }
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

            // Ensure retraining is idempotent when the same document name already exists.
            self::delete_content_document_name_from_bot((int)$course_id, (string)$file_name, 'documents');

            $try_upload = static function () use ($curl, $upload_url, $upload_body, $upload_opts): array {
                $upload_raw = (string)$curl->post($upload_url, json_encode($upload_body), $upload_opts);
                $uploaded = json_decode((string)$upload_raw, true);
                $status = is_array($uploaded) ? (int)($uploaded['status'] ?? 0) : 0;
                $is_duplicate = false;
                if (is_string($upload_raw) && (
                    strpos($upload_raw, '"code":"DUPLICATE"') !== false
                    || strpos($upload_raw, 'Requested content already exists') !== false
                    || strpos($upload_raw, 'already exists in the database') !== false
                )) {
                    $is_duplicate = true;
                }
                return [
                    'raw' => $upload_raw,
                    'uploaded' => $uploaded,
                    'status' => $status,
                    'duplicate' => $is_duplicate,
                ];
            };

            $attempt = $try_upload();
            $uploaded = $attempt['uploaded'];
            $upload_raw = $attempt['raw'];
            $is_duplicate = (bool)$attempt['duplicate'];
            $upload_status = (int)$attempt['status'];

            // Retry once for transient validation/network race failures.
            if (!$is_duplicate && $upload_status !== 200 && ($upload_status === 422 || $upload_status >= 500)) {
                self::delete_content_document_name_from_bot((int)$course_id, (string)$file_name, 'documents');
                $attempt = $try_upload();
                $uploaded = $attempt['uploaded'];
                $upload_raw = $attempt['raw'];
                $is_duplicate = (bool)$attempt['duplicate'];
                $upload_status = (int)$attempt['status'];
            }

            if ((!is_array($uploaded) || $upload_status !== 200) && !$is_duplicate) {
                error_log('block_ai_assistant: upload_content_to_bot failed Criabot upload for bot ' . $bot_name . ' status=' . $upload_status . ' response=' . substr((string)$upload_raw, 0, 500));
                return '';
            }

            $document_name = (string)($uploaded['document_name'] ?? '');
            if ($document_name === '' && is_array($uploaded) && (($uploaded['status'] ?? null) === 200)) {
                // Some Criabot deployments omit document_name in success payloads.
                $document_name = (string)$file_name;
            }
            if ($document_name === '' && $is_duplicate) {
                // Duplicate means the same document is already indexed; keep deterministic name.
                $document_name = (string)$file_name;
            }
            if ($document_name !== '' && $persist_syllabus_metadata) {
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
     * Normalize parser elements into the document schema expected by Criabot.
     *
     * @param array $elements
     * @return array
     */
    private static function normalize_criaparse_elements(array $elements): array
    {
        $nodes = [];
        foreach ($elements as $element) {
            if (is_object($element)) {
                $element = (array)$element;
            }
            if (!is_array($element)) {
                continue;
            }

            $text = '';
            foreach (['text', 'content', 'page_content', 'chunk', 'value'] as $candidate) {
                if (isset($element[$candidate]) && is_scalar($element[$candidate])) {
                    $text = trim((string)$element[$candidate]);
                    if ($text !== '') {
                        break;
                    }
                }
            }
            if ($text === '') {
                continue;
            }

            $metadata = [];
            if (isset($element['metadata']) && is_array($element['metadata'])) {
                $metadata = $element['metadata'];
            }
            if (isset($element['page']) && !isset($metadata['page'])) {
                $metadata['page'] = $element['page'];
            }

            $node = [
                'text' => $text,
                'metadata' => empty($metadata) ? new \stdClass() : $metadata,
            ];

            if (isset($element['type']) && is_scalar($element['type'])) {
                $node['type'] = (string)$element['type'];
            } else {
                $node['type'] = 'text';
            }

            $nodes[] = $node;
        }
        return $nodes;
    }

    /**
     * Normalize parser assets payload to a plain array.
     *
     * @param array $assets
     * @return array
     */
    private static function normalize_criaparse_assets(array $assets): array
    {
        $out = [];
        foreach ($assets as $asset) {
            if (is_object($asset)) {
                $out[] = (array)$asset;
            } else if (is_array($asset)) {
                $out[] = $asset;
            }
        }
        return $out;
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
        $training_status_id = 2;
        $training_status = '<div class="badge badge-danger">'
            . get_string('error', 'block_ai_assistant') . '</div>';

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
            default:
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
        if (is_array($resp)) {
            $fallback = self::first_non_empty_string($resp, [
                'response.chat_id',
                'data.chat_id',
                'response.data.chat_id',
            ]);
            if ($fallback !== '') {
                return $fallback;
            }
        }
        return '';
    }

    /**
     * Returns the first non-empty string found at one of the provided dot-path keys.
     *
     * @param array $payload
     * @param array $paths
     * @return string
     */
    private static function first_non_empty_string(array $payload, array $paths): string
    {
        foreach ($paths as $path) {
            $value = self::get_by_path($payload, $path);
            if (is_string($value)) {
                $value = trim($value);
                if ($value !== '') {
                    return $value;
                }
            }
        }
        return '';
    }

    /**
     * Resolve a value from an array using a dot-path.
     *
     * @param array $payload
     * @param string $path
     * @return mixed|null
     */
    private static function get_by_path(array $payload, string $path)
    {
        $current = $payload;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }
        return $current;
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
            return trim((string)$response);
        }

        // Prefer assistant payload content over top-level API status message.
        $message = self::first_non_empty_string($decoded, [
            'reply.content.content',
            'reply.content',
            'reply.message',
            'response.reply.content.content',
            'response.reply.content',
            'response.reply.message',
            'response.data.answer',
            'response.data.reply.message',
            'response.data.reply.content.content',
            'response.data.reply.content',
            'response.answer',
            'data.answer',
            'data.reply.message',
            'data.reply.content.content',
            'data.reply.content',
            'answer',
        ]);
        if ($message !== '') {
            return $message;
        }

        $status = self::first_non_empty_string($decoded, ['status', 'response.status']);
        $code = self::first_non_empty_string($decoded, ['code', 'response.code']);
        $error = self::first_non_empty_string($decoded, [
            'error',
            'response.error',
            'message',
            'response.message',
            'data.message',
        ]);
        if ($status !== '' || $code !== '' || $error !== '') {
            return trim($status . ' ' . ($code !== '' ? $code : $error));
        }

        return '';
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
                $sectionnum = isset($cm->sectionnum) ? (string)$cm->sectionnum : '';
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
                        'section' => $sectionnum,
                        'content_url' => (string)$cm->url,
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Fallback: leave arrays empty; backend will handle intake mode.
        }

        // Also include syllabus uploaded via AI Assistant block file area,
        // so gradebook flow can detect syllabus even when indexing/training failed.
        try {
            $context = \context_course::instance($courseid);
            $fs = get_file_storage();
            $files = $fs->get_area_files($context->id, 'block_ai_assistant', 'syllabus', $courseid, 'itemid', false);
            foreach ($files as $file) {
                if ($file->get_filesize() <= 0 || $file->is_directory()) {
                    continue;
                }
                $resources[] = [
                    'cmid' => null,
                    'type' => 'file',
                    'name' => (string)$file->get_filename(),
                    'section' => '0',
                    'content_url' => '',
                    'content_preview' => 'Uploaded in AI Assistant syllabus area.',
                ];
            }
        } catch (\Throwable $e) {
            // Ignore file area read failures; keep best-effort discovery.
        }

        // Include additional supporting documents uploaded from gradebook chat.
        try {
            $context = \context_course::instance($courseid);
            $fs = get_file_storage();
            $files = $fs->get_area_files($context->id, 'block_ai_assistant', 'gradebookdocs', $courseid, 'itemid', false);
            foreach ($files as $file) {
                if ($file->get_filesize() <= 0 || $file->is_directory()) {
                    continue;
                }
                $resources[] = [
                    'cmid' => null,
                    'type' => 'file',
                    'name' => (string)$file->get_filename(),
                    'section' => '0',
                    'content_url' => '',
                    'content_preview' => 'Supporting document uploaded in gradebook chat.',
                ];
            }
        } catch (\Throwable $e) {
            // Ignore file area read failures; keep best-effort discovery.
        }

        // Include block-level syllabus metadata as a final signal.
        try {
            $settings = $DB->get_record('block_aia_settings', ['courseid' => $courseid]);
            if ($settings && !empty($settings->syllabus_document_name)) {
                $resources[] = [
                    'cmid' => null,
                    'type' => 'file',
                    'name' => (string)$settings->syllabus_document_name,
                    'section' => '0',
                    'content_url' => '',
                    'content_preview' => ((int)($settings->syllabus_trained ?? 0) === 1)
                        ? 'Syllabus uploaded and trained in AI Assistant.'
                        : 'Syllabus uploaded in AI Assistant.',
                ];
            }
        } catch (\Throwable $e) {
            // Ignore metadata read failures; continue with available data.
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

    public static function gradebook_reset(string $session_id, bool $keep_extraction = true): string
    {
        $method = 'cria_gradebook_reset';
        $data = array(
            'session_id' => trim($session_id),
            'keep_extraction' => $keep_extraction,
        );
        return webservice::exec($method, $data);
    }

    public static function gradebook_delete(string $session_id, int $courseid = 0): string
    {
        $session_id = trim($session_id);
        $hascoursechanges = $courseid > 0 ? self::course_has_ai_gradebook($courseid) : false;

        // Prevent delete when no AI gradebook structure exists in course.
        // In this case there is nothing to clean up from Moodle grade setup,
        // so we block delete and let the user continue the current session.
        if (!$hascoursechanges) {
            return json_encode([
                'status' => 200,
                'code' => 'SUCCESS',
                'message' => get_string('gradebook_delete_nothing', 'block_ai_assistant'),
                'data' => [
                    'success' => false,
                    'existed' => true,
                    'blocked' => true,
                    'grade_setup_cleaned' => false,
                    'grade_setup_message' => '',
                ],
            ]);
        }

        $method = 'cria_gradebook_delete';
        $data = array(
            'session_id' => $session_id,
        );
        $result = webservice::exec($method, $data);
        $decoded = json_decode($result, true);

        $backendnotfound = is_array($decoded)
            && ((int)($decoded['status'] ?? 0) === 404 || (string)($decoded['code'] ?? '') === 'NOT_FOUND');

        $cleanup_result = null;
        if ($hascoursechanges) {
            $cleanup_result = self::remove_ai_gradebook_from_course($courseid);
        }

        if (!is_array($decoded)) {
            $decoded = [
                'status' => 200,
                'code' => 'SUCCESS',
                'message' => get_string('gradebook_delete_completed', 'block_ai_assistant'),
            ];
        }

        if ($backendnotfound && $hascoursechanges) {
            $decoded['status'] = 200;
            $decoded['code'] = 'SUCCESS';
            $decoded['message'] = get_string('gradebook_delete_cleanup_only', 'block_ai_assistant');
        }

        $decoded['data'] = array_merge(
            is_array($decoded['data'] ?? null) ? $decoded['data'] : [],
            [
                'success' => !$backendnotfound || $hascoursechanges,
                'existed' => !$backendnotfound,
                'blocked' => false,
                'grade_setup_cleaned' => (bool)($cleanup_result['cleaned'] ?? false),
                'grade_setup_message' => (string)($cleanup_result['message'] ?? ''),
            ]
        );

        return json_encode($decoded);
    }

    private static function course_has_ai_gradebook(int $courseid): bool
    {
        return self::count_ai_gradebook_roots($courseid) > 0;
    }

    /**
     * Match AI gradebook root names robustly so cleanup also works for legacy/localized labels.
     */
    private static function is_ai_gradebook_root_label(string $fullname): bool
    {
        $normalized = self::normalize_ai_gradebook_label($fullname);
        if ($normalized === '') {
            return false;
        }

        $known = [
            'ai assistant gradebook',
            'ai assistant - gradebook',
            'ai assistant grade book',
            'carnet de notes assistante ia',
            'carnet de notes - assistante ia',
            'carnet de notes assistante ai',
            'carnet de notes - assistante ai',
        ];
        if (in_array($normalized, $known, true)) {
            return true;
        }

        // Backward-compatible fallback for slight naming variants.
        return ((strpos($normalized, 'assistant') !== false || strpos($normalized, 'assistante') !== false)
            && (strpos($normalized, 'gradebook') !== false || strpos($normalized, 'carnet de notes') !== false));
    }

    private static function normalize_ai_gradebook_label(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }

        $value = str_replace(["\u{2013}", "\u{2014}", "\u{2212}"], '-', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    // ------------------------------------------------------------------ Ownership marker --
    // Stores the root category IDs that this plugin created for a given course so that
    // cleanup can find them even if the category name was changed after finalize.
    // Stored as a JSON-encoded array in Moodle plugin config (no schema change required).

    private static function _get_ai_ownership_ids(int $courseid): array
    {
        $raw = get_config('block_ai_assistant', 'ai_gb_roots_' . $courseid);
        if (!$raw) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? array_map('intval', $decoded) : [];
    }

    private static function _set_ai_ownership_id(int $courseid, int $categoryid): void
    {
        $existing = self::_get_ai_ownership_ids($courseid);
        $existing[] = $categoryid;
        set_config('ai_gb_roots_' . $courseid, json_encode(array_values(array_unique($existing))), 'block_ai_assistant');
    }

    private static function _clear_ai_ownership_ids(int $courseid): void
    {
        unset_config('ai_gb_roots_' . $courseid, 'block_ai_assistant');
    }

    private static function get_ai_gradebook_root_ids(int $courseid): array
    {
        global $DB;

        // Start from the ownership registry – most reliable (survives name changes).
        $registered = self::_get_ai_ownership_ids($courseid);

        // Also scan by label so we catch categories created before the registry existed.
        $cats = $DB->get_records('grade_categories', ['courseid' => $courseid]);
        $existing_ids = [];
        $name_ids = [];
        foreach (($cats ?: []) as $cat) {
            $existing_ids[] = (int)$cat->id;
            if (self::is_ai_gradebook_root_label((string)($cat->fullname ?? ''))) {
                $name_ids[] = (int)$cat->id;
            }
        }

        // Keep only registered IDs that still exist in the DB (skip stale entries).
        $valid_registered = array_filter($registered, static fn($id) => in_array($id, $existing_ids, true));

        return array_values(array_unique(array_merge(array_values($valid_registered), $name_ids)));
    }

    private static function count_ai_gradebook_roots(int $courseid): int
    {
        return count(self::get_ai_gradebook_root_ids($courseid));
    }

    private static function is_pristine_gradebook_session(string $session_id): bool
    {
        try {
            $status_json = self::gradebook_status($session_id);
            $status = json_decode($status_json, true);
            if (!is_array($status) || !is_array($status['session'] ?? null)) {
                return false;
            }

            $session = $status['session'];
            $phase = strtoupper(trim((string)($session['phase'] ?? '')));
            $hasproposal = !empty($session['proposal']);
            $hasmapping = !empty($session['content_mapping']);

            return in_array($phase, ['INITIAL', 'INTAKE', 'ANALYSIS'], true)
                && !$hasproposal
                && !$hasmapping;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Remove the AI Assistant gradebook category tree from the course grade setup.
     * Uses raw SQL throughout to avoid grade_item::fetch_all() filter unreliability.
     *
     * @return array with keys 'cleaned' (bool) and 'message' (string)
     */
    public static function remove_ai_gradebook_from_course(int $courseid): array
    {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->libdir . '/grade/grade_category.php');
        require_once($CFG->libdir . '/grade/grade_item.php');

        try {
            $roots = self::get_ai_gradebook_root_ids($courseid);
            if (empty($roots)) {
                // Nothing to remove – clear stale ownership entry just in case.
                self::_clear_ai_ownership_ids($courseid);
                return [
                    'cleaned' => false,
                    'message' => 'No AI Assistant gradebook categories found to remove.'
                ];
            }

            $allcats = $DB->get_records('grade_categories', ['courseid' => $courseid]);
            $children_by_parent = [];
            foreach (($allcats ?: []) as $cat) {
                $pid = (int)($cat->parent ?? 0);
                if (!isset($children_by_parent[$pid])) {
                    $children_by_parent[$pid] = [];
                }
                $children_by_parent[$pid][] = (int)$cat->id;
            }

            $tree_ids = self::_collect_category_tree_ids($roots, $children_by_parent);
            if (empty($tree_ids)) {
                self::_clear_ai_ownership_ids($courseid);
                return ['cleaned' => false, 'message' => 'Could not resolve AI gradebook category tree IDs.'];
            }

            debugging('Removing AI gradebook tree (' . implode(',', $tree_ids) . ') from course ' . $courseid, DEBUG_DEVELOPER);

            list($inSql, $inParams) = $DB->get_in_or_equal($tree_ids);

            // 1. Move non-structural grade items (activities/manual) back to course total.
            $coursecat = \grade_category::fetch_course_category($courseid);
            if ($coursecat) {
                $moditems = $DB->get_records_select(
                    'grade_items',
                    'courseid = ? AND categoryid ' . $inSql . " AND itemtype <> 'category'",
                    array_merge([$courseid], $inParams)
                );
                foreach ($moditems as $item) {
                    $item->categoryid = $coursecat->id;
                    $DB->update_record('grade_items', $item);
                }
            }

            // 2. Delete structural category-total grade items for the removed categories.
            $DB->delete_records_select(
                'grade_items',
                "courseid = ? AND itemtype = 'category' AND iteminstance " . $inSql,
                array_merge([$courseid], $inParams)
            );

            // 3. Delete the category records themselves.
            $DB->delete_records_select(
                'grade_categories',
                'courseid = ? AND id ' . $inSql,
                array_merge([$courseid], $inParams)
            );

            grade_regrade_final_grades($courseid);

            // 4. Verify cleanup was complete.
            $remaining_roots = self::count_ai_gradebook_roots($courseid);
            if ($remaining_roots > 0) {
                return [
                    'cleaned' => false,
                    'message' => $remaining_roots . ' AI Assistant gradebook ' . ($remaining_roots === 1 ? 'category' : 'categories') . ' could not be removed. Check Moodle grade setup manually.'
                ];
            }

            // 5. Repair any items that somehow still point at deleted category IDs.
            if (self::_repair_dangling_grade_items($courseid, $tree_ids) > 0) {
                return [
                    'cleaned' => false,
                    'message' => 'Categories removed but some grade items still referenced deleted AI categories. Please re-run delete session once.'
                ];
            }

            // 6. Clear ownership registry now that cleanup succeeded.
            self::_clear_ai_ownership_ids($courseid);

            $root_count = count($roots);
            return [
                'cleaned' => true,
                'message' => 'Removed ' . $root_count . ' AI Assistant gradebook ' . ($root_count === 1 ? 'category' : 'categories') . '.'
            ];
        } catch (Throwable $e) {
            debugging('Error removing AI gradebook from course: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [
                'cleaned' => false,
                'message' => 'Error during cleanup: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete a grade category tree bottom-up. Moves leaf grade items to course total first.
     */
    private static function _delete_grade_category_tree(int $courseid, int $category_id, array $children_by_parent): void
    {
        $children = $children_by_parent[$category_id] ?? [];
        foreach ($children as $child_id) {
            self::_delete_grade_category_tree($courseid, (int)$child_id, $children_by_parent);
        }

        self::_move_items_to_course_total($courseid, $category_id);
        self::_delete_category_total_item($courseid, $category_id);

        $cat = \grade_category::fetch(['id' => $category_id]);
        if ($cat) {
            $cat->delete('block_ai_assistant');
        }
    }

    /**
     * Move non-structural grade_items inside a category back to course total.
     * Uses direct DB query because grade_item::fetch_all() does not reliably filter by parameters.
     */
    private static function _move_items_to_course_total(int $courseid, int $from_category_id): void
    {
        global $DB;

        $course_cat = \grade_category::fetch_course_category($courseid);
        if (!$course_cat) {
            return;
        }

        $items = $DB->get_records_select(
            'grade_items',
            "courseid = ? AND categoryid = ? AND itemtype <> 'category'",
            [$courseid, $from_category_id]
        );
        foreach ($items as $item) {
            $item->categoryid = $course_cat->id;
            $DB->update_record('grade_items', $item);
        }
    }

    private static function _delete_category_total_item(int $courseid, int $category_id): void
    {
        global $DB;

        // Use direct DB query – grade_item::fetch_all() does not reliably filter by parameters.
        $items = $DB->get_records_select(
            'grade_items',
            "courseid = ? AND itemtype = 'category' AND iteminstance = ?",
            [$courseid, $category_id]
        );
        foreach ($items as $item) {
            $gi = \grade_item::fetch(['id' => $item->id]);
            if ($gi) {
                $gi->delete('block_ai_assistant');
            }
        }
    }

    private static function _collect_category_tree_ids(array $rootids, array $children_by_parent): array
    {
        $stack = array_values(array_map('intval', $rootids));
        $treeids = [];

        while (!empty($stack)) {
            $cid = (int)array_pop($stack);
            if (isset($treeids[$cid])) {
                continue;
            }
            $treeids[$cid] = true;
            foreach (($children_by_parent[$cid] ?? []) as $childid) {
                $stack[] = (int)$childid;
            }
        }

        return array_keys($treeids);
    }

    /**
     * If any non-structural grade items still reference removed category IDs,
     * move them to course total and return remaining dangling count.
     */
    private static function _repair_dangling_grade_items(int $courseid, array $removed_category_ids): int
    {
        global $DB;

        if (empty($removed_category_ids)) {
            return 0;
        }

        list($inSql, $inParams) = $DB->get_in_or_equal($removed_category_ids);
        $remaining = $DB->count_records_select(
            'grade_items',
            'courseid = ? AND categoryid ' . $inSql . ' AND itemtype <> ?',
            array_merge([$courseid], $inParams, ['category'])
        );
        if ($remaining < 1) {
            return 0;
        }

        $coursecat = \grade_category::fetch_course_category($courseid);
        if (!$coursecat) {
            return (int)$remaining;
        }

        $items = $DB->get_records_select(
            'grade_items',
            'courseid = ? AND categoryid ' . $inSql . ' AND itemtype <> ?',
            array_merge([$courseid], $inParams, ['category'])
        );
        foreach ($items as $item) {
            $item->categoryid = $coursecat->id;
            $DB->update_record('grade_items', $item);
        }

        grade_regrade_final_grades($courseid);

        return (int)$DB->count_records_select(
            'grade_items',
            'courseid = ? AND categoryid ' . $inSql . ' AND itemtype <> ?',
            array_merge([$courseid], $inParams, ['category'])
        );
    }

    /**
     * Force-purge fallback – delegates to remove_ai_gradebook_from_course which now uses
     * raw SQL throughout. Retained for backward-compatibility with any external call paths.
     */
    private static function _force_purge_ai_gradebook_tree(int $courseid): array
    {
        if (self::count_ai_gradebook_roots($courseid) < 1) {
            return [
                'cleaned' => true,
                'message' => 'Force purge was not needed; no AI Assistant gradebook categories remain.'
            ];
        }

        $result = self::remove_ai_gradebook_from_course($courseid);
        if ($result['cleaned']) {
            $result['message'] = 'AI Assistant gradebook categories were removed using force purge fallback.';
        }
        return $result;
    }

    public static function gradebook_finalize(int $courseid, string $session_id, array $confirmed_mapping): string
    {
        $method = 'cria_gradebook_finalize';
        $data = array(
            'session_id' => trim($session_id),
            'confirmed_mapping' => $confirmed_mapping,
            'create_categories' => true,
            'reorganize_resources' => false,
        );
        $response_json = webservice::exec($method, $data);
        $applywarnings = [];

        if ($courseid > 0) {
            try {
                $response = json_decode($response_json, true);
                if (is_array($response) && isset($response['proposal'])) {
                    $applywarnings = self::apply_gradebook_to_course($courseid, $response['proposal'], $confirmed_mapping);

                    if (!empty($applywarnings)) {
                        $warningtext = implode(' ', $applywarnings);
                        $response['message'] = trim((string)($response['message'] ?? 'Gradebook finalized.')) . ' ' . $warningtext;
                        $response['data'] = array_merge(
                            is_array($response['data'] ?? null) ? $response['data'] : [],
                            ['grade_setup_warnings' => $applywarnings]
                        );
                        $response_json = json_encode($response);
                    }
                }
            } catch (Throwable $e) {
                // Never block finalization on gradebook update failure
                debugging('Error applying gradebook to course: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        return $response_json;
    }

    /**
     * Apply the finalized gradebook proposal to the Moodle course gradebook.
     * Creates grade categories with the chosen aggregation method, sets weights,
     * drop/keep rules, and moves grade items for mapped activities into their categories.
     */
    private static function apply_gradebook_to_course(int $courseid, array $proposal, array $confirmed_mapping): array
    {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->libdir . '/grade/grade_category.php');
        require_once($CFG->libdir . '/grade/grade_item.php');

        if (!isset($proposal['categories']) || !is_array($proposal['categories'])) {
            return [];
        }

        $warnings = [];
        $forcedkeephigh = isset($CFG->grade_keephigh_flag) && (((int)$CFG->grade_keephigh_flag & 1) === 1);
        $forcedkeephighvalue = isset($CFG->grade_keephigh) ? (int)$CFG->grade_keephigh : 0;
        $keephighoverriddencategories = [];

        $aggregation_method = (int)($proposal['aggregation_method'] ?? 13);

        // Get or create top-level parent category for this AI-generated structure
        $parent = self::get_or_create_grade_category(
            $courseid,
            'AI Assistant - Gradebook',
            $aggregation_method,
            null,
            1.0,
              ['droplow' => 0, 'keephigh' => 0, 'aggregateonlygraded' => 1, 'aggregateoutcomes' => 0, 'extra_credit' => false,
               'grade_min' => null, 'grade_max' => 100.0, 'grade_pass' => null,
               'hidden' => false, 'hidden_until' => null,
               'locked' => false, 'lock_time' => null,
               'display_type' => 0, 'decimals' => -1]
        );

        // Register root so cleanup can find it even if the name is later changed.
        self::_set_ai_ownership_id($courseid, (int)$parent->id);

        // Create / update each top-level category and optional nested subcategories.
        $category_id_map = [];
        $subcategory_by_parent = [];
        $subcategory_global_map = [];
        $subcategory_rr_index = [];

        foreach ($proposal['categories'] as $cat) {
            $name = trim((string)($cat['name'] ?? ''));
            $pct = floatval($cat['weight'] ?? 0);
            if ($name === '') {
                continue;
            }

            $category_key = strtolower($name);
            $weight_fraction = $pct / 100.0;
            $requestedkeephigh = (int)($cat['keep_highest'] ?? 0);
            $effectivekeephigh = $forcedkeephigh ? $forcedkeephighvalue : $requestedkeephigh;
            if ($requestedkeephigh !== $effectivekeephigh) {
                $keephighoverriddencategories[] = $name;
            }

            $settings = [
                'droplow'              => (int)($cat['drop_lowest'] ?? 0),
                'keephigh'             => $effectivekeephigh,
                'aggregateonlygraded'  => (bool)($cat['aggregate_only_graded'] ?? true) ? 1 : 0,
                'aggregateoutcomes'    => (bool)($cat['aggregate_outcomes'] ?? false) ? 1 : 0,
                'extra_credit'         => (bool)($cat['extra_credit'] ?? false),
                'grade_min'            => isset($cat['grade_min']) && $cat['grade_min'] !== null ? (float)$cat['grade_min'] : null,
                'grade_max'            => isset($cat['grade_max']) ? (float)$cat['grade_max'] : 100.0,
                'grade_pass'           => isset($cat['grade_pass']) && $cat['grade_pass'] !== null ? (float)$cat['grade_pass'] : null,
                'hidden'               => (bool)($cat['hidden'] ?? false),
                'hidden_until'         => isset($cat['hidden_until']) && $cat['hidden_until'] !== null ? (int)$cat['hidden_until'] : null,
                'locked'               => (bool)($cat['locked'] ?? false),
                'lock_time'            => isset($cat['lock_time']) && $cat['lock_time'] !== null ? (int)$cat['lock_time'] : null,
                'display_type'         => (int)($cat['display_type'] ?? 0),
                'decimals'             => (int)($cat['decimals'] ?? -1),
            ];

            $cat_obj = self::get_or_create_grade_category(
                $courseid,
                $name,
                $aggregation_method,
                $parent,
                $weight_fraction,
                $settings
            );
            $category_id_map[$category_key] = $cat_obj->id;

            $subcategories = is_array($cat['subcategories'] ?? null) ? $cat['subcategories'] : [];
            foreach ($subcategories as $sub) {
                $sub_name = trim((string)($sub['name'] ?? ''));
                if ($sub_name === '') {
                    continue;
                }

                $sub_weight = floatval($sub['weight'] ?? 0);
                $sub_weight_fraction = $sub_weight / 100.0;
                $sub_settings = [
                    'droplow' => 0,
                    'keephigh' => 0,
                    'aggregateonlygraded' => 1,
                    'aggregateoutcomes' => 0,
                    'extra_credit' => false,
                    'grade_min' => null,
                    'grade_max' => 100.0,
                    'grade_pass' => null,
                    'hidden' => false,
                    'hidden_until' => null,
                    'locked' => false,
                    'lock_time' => null,
                    'display_type' => 0,
                    'decimals' => -1,
                ];

                $sub_obj = self::get_or_create_grade_category(
                    $courseid,
                    $sub_name,
                    $aggregation_method,
                    $cat_obj,
                    $sub_weight_fraction,
                    $sub_settings
                );

                $sub_key = strtolower($sub_name);
                $subcategory_by_parent[$category_key][$sub_key] = $sub_obj->id;
                $subcategory_global_map[$sub_key] = $sub_obj->id;
            }

            if (!empty($subcategory_by_parent[$category_key])) {
                $subcategory_rr_index[$category_key] = 0;
            }
        }

        if (!empty($keephighoverriddencategories)) {
            $warnings[] = '⚠ WARNING: Keep-highest rules were overridden by Moodle site grade settings for: '
                . implode(', ', array_values(array_unique($keephighoverriddencategories)))
                . '.';
        }

        // Move activity grade items into mapped categories/subcategories.
        $modinfo = get_fast_modinfo($courseid);
        foreach ($confirmed_mapping as $mapping) {
            $cmid = intval($mapping['moodle_cmid'] ?? 0);
            $category_name = trim((string)($mapping['category'] ?? ''));
            $category_key = strtolower($category_name);

            if ($cmid <= 0 || $category_name === '') {
                continue;
            }

            try {
                $cm = $modinfo->get_cm($cmid);
                if (!$cm) {
                    continue;
                }

                $target_category_id = null;
                if (isset($subcategory_global_map[$category_key])) {
                    $target_category_id = (int)$subcategory_global_map[$category_key];
                } elseif (isset($category_id_map[$category_key])) {
                    $target_category_id = (int)$category_id_map[$category_key];
                    if (!empty($subcategory_by_parent[$category_key])) {
                        $target_category_id = self::pick_subcategory_target(
                            (string)($cm->name ?? ''),
                            $subcategory_by_parent[$category_key],
                            $subcategory_rr_index[$category_key]
                        );
                    }
                }

                if ($target_category_id === null) {
                    continue;
                }

                $grade_items = \grade_item::fetch_all([
                    'courseid'     => $courseid,
                    'iteminstance' => $cm->instance,
                    'itemmodule'   => $cm->modname,
                    'itemtype'     => 'mod',
                ]);
                if (!$grade_items) {
                    continue;
                }

                foreach ($grade_items as $gi) {
                    $gi->categoryid = $target_category_id;
                    $gi->update('block_ai_assistant');
                }
            } catch (Throwable $e) {
                debugging("Error mapping cmid $cmid: " . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // Recalculate grades after structural change
        grade_regrade_final_grades($courseid);

        return $warnings;
    }

    /**
     * Pick a subcategory for an activity name.
     * 1) Prefer token match with subcategory name.
     * 2) Fallback to round-robin to avoid leaving all items under parent category.
     */
    private static function pick_subcategory_target(string $activity_name, array $subcategories, int &$round_robin_index): int
    {
        if (empty($subcategories)) {
            return 0;
        }

        $activity_l = strtolower($activity_name);
        foreach ($subcategories as $sub_name => $sub_id) {
            $tokens = preg_split('/[^a-z0-9]+/', strtolower((string)$sub_name));
            foreach ($tokens as $token) {
                if ($token === '' || strlen($token) < 3) {
                    continue;
                }
                if (strpos($activity_l, $token) !== false) {
                    return (int)$sub_id;
                }
            }
        }

        $sub_ids = array_values($subcategories);
        $idx = $round_robin_index % max(1, count($sub_ids));
        $round_robin_index++;
        return (int)$sub_ids[$idx];
    }

    /**
     * Fetch an existing grade category (by course + fullname + optional parent) or create it.
     * Uses Moodle's grade_category API so the associated grade_item is created automatically.
     *
     * Weight is stored differently depending on the aggregation method:
     *   - Weighted mean (10) / Simple weighted mean (11): aggregationcoef (numeric, proportional)
     *   - Natural (13): aggregationcoef2 (0–1 fraction) with weightoverride = 1
     *   - Mean / Mean+extra credits (0, 12): weight ignored (all categories equal)
     *
     * @param int                  $courseid
     * @param string               $fullname
     * @param int                  $aggregation  Moodle aggregation constant
     * @param \grade_category|null $parent        null = top-level under course total
     * @param float                $weight        0–1 fraction (weight / 100)
     * @param array                $settings      droplow, keephigh, aggregateonlygraded, extra_credit
     */
    private static function get_or_create_grade_category(
        int $courseid,
        string $fullname,
        int $aggregation,
        ?\grade_category $parent,
        float $weight,
        array $settings = []
    ): \grade_category {
        $parent_id = $parent ? $parent->id : null;

        $existing_list = \grade_category::fetch_all([
            'courseid' => $courseid,
            'fullname' => $fullname,
        ]);

        $gc = null;
        if ($existing_list) {
            foreach ($existing_list as $existing) {
                if ($parent_id === null || $existing->parent == $parent_id) {
                    $gc = $existing;
                    break;
                }
            }
        }

        if ($gc === null) {
            $gc = new \grade_category(['courseid' => $courseid], false);
            $gc->fullname = $fullname;
            $gc->courseid = $courseid;
            if ($parent) {
                $gc->parent = (int)$parent->id;
            }
        }

        $gc->aggregation         = $aggregation;
        $gc->aggregateonlygraded = $settings['aggregateonlygraded'] ?? 1;
        $gc->aggregateoutcomes   = $settings['aggregateoutcomes'] ?? 0;
        $gc->droplow             = $settings['droplow'] ?? 0;
        $gc->keephigh            = $settings['keephigh'] ?? 0;

        if (isset($gc->id)) {
            $gc->update('block_ai_assistant');
        } else {
            $gc->insert('block_ai_assistant');
        }

        // Ensure nested categories are attached to the expected parent in all Moodle variants.
        if ($parent && (int)$gc->parent !== (int)$parent->id) {
            $gc->set_parent((int)$parent->id);
            $gc = \grade_category::fetch(['id' => $gc->id]);
        }

        self::apply_category_grade_item($gc, $aggregation, $weight, $settings, $parent);
        return $gc;
    }

    /**
     * Apply weight, display format, pass threshold, hidden, locked to the grade_item
     * that represents this category inside its parent aggregation.
     *
     * Weight fields per aggregation method:
     *   Natural (13 = GRADE_AGGREGATE_SUM):       aggregationcoef2 = 0–1, weightoverride = 1
     *   Weighted mean (10/11):                     aggregationcoef  = percent value (e.g. 25 for 25%)
     *   Mean (0) / Mean+extra credits (12):        weight ignored; aggregationcoef = extra-credit flag
     */
    private static function apply_category_grade_item(
        \grade_category $gc,
        int $aggregation,
        float $weight,
        array $settings = [],
        ?\grade_category $parent = null
    ): void {
        $gi = \grade_item::fetch(['itemtype' => 'category', 'iteminstance' => $gc->id]);
        if (!$gi) {
            return;
        }

        // Weight
        $extra_credit = (bool)($settings['extra_credit'] ?? false);
        // Category item weight is interpreted by its parent category aggregation method.
        $parent_aggregation = $parent ? (int)($parent->aggregation ?? 13) : $aggregation;
        if ($parent_aggregation === 13) {
            $gi->weightoverride   = 1;
            $gi->aggregationcoef2 = $weight;
            $gi->aggregationcoef  = (int)$extra_credit;
        } elseif ($parent_aggregation === 10 || $parent_aggregation === 11) {
            $gi->aggregationcoef  = $weight * 100.0;
            $gi->aggregationcoef2 = 0;
            $gi->weightoverride   = 0;
        } else {
            $gi->aggregationcoef  = (int)$extra_credit;
            $gi->aggregationcoef2 = 0;
            $gi->weightoverride   = 0;
        }

        // Grade range
        if (isset($settings['grade_min']) && $settings['grade_min'] !== null) {
            $gi->grademin = (float)$settings['grade_min'];
        }
        if (isset($settings['grade_max']) && $settings['grade_max'] > 0) {
            $gi->grademax = (float)$settings['grade_max'];
        }
        if (isset($settings['grade_pass']) && $settings['grade_pass'] !== null) {
            $gi->gradepass = (float)$settings['grade_pass'];
        }

        // Visibility
        if (isset($settings['hidden_until']) && !empty($settings['hidden_until'])) {
            $gi->hidden = (int)$settings['hidden_until'];
        } elseif (isset($settings['hidden'])) {
            $gi->hidden = (bool)$settings['hidden'] ? 1 : 0;
        }

        // Locking
        if (isset($settings['lock_time']) && !empty($settings['lock_time'])) {
            $gi->locktime = (int)$settings['lock_time'];
            $gi->locked = 0;
        } elseif (!empty($settings['locked'])) {
            $gi->locked = time(); // lock now
            $gi->locktime = 0;
        } elseif (array_key_exists('locked', $settings)) {
            $gi->locked = 0;
            $gi->locktime = 0;
        }

        // Display format (GRADE_DISPLAY_TYPE_* constants: 0=default, 1=real, 2=pct, 3=letter ...)
        if (isset($settings['display_type'])) {
            $gi->display = (int)$settings['display_type'];
        }

        // Decimal places (-1 = course default)
        if (isset($settings['decimals']) && (int)$settings['decimals'] >= -1) {
            $gi->decimals = (int)$settings['decimals'];
        }

        $gi->update('block_ai_assistant');
    }

    private static function is_syllabus_like_filename(string $filename): bool
    {
        $name = strtolower(trim($filename));
        if ($name === '') {
            return false;
        }

        $tokens = [
            'syllabus',
            'syllabi',
            'syllabe',
            'plan de cours',
            'plan_du_cours',
            'plan-du-cours',
            'outline',
            'course outline',
            'programme',
        ];

        foreach ($tokens as $token) {
            if (strpos($name, $token) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function persist_gradebook_uploaded_file(int $courseid, string $filename, string $base64): void
    {
        global $DB, $USER;

        if ($courseid <= 0 || trim($filename) === '' || trim($base64) === '') {
            return;
        }

        $context = \context_course::instance($courseid);
        $fs = get_file_storage();

        $is_syllabus = self::is_syllabus_like_filename($filename);
        $filearea = $is_syllabus ? 'syllabus' : 'gradebookdocs';

        // Replace existing syllabus file to keep one canonical syllabus in this area.
        if ($is_syllabus) {
            $existing = $fs->get_area_files($context->id, 'block_ai_assistant', 'syllabus', $courseid, 'itemid', false);
            foreach ($existing as $file) {
                $file->delete();
            }
        }

        $file_record = [
            'contextid' => $context->id,
            'component' => 'block_ai_assistant',
            'filearea' => $filearea,
            'itemid' => $courseid,
            'filepath' => '/',
            'filename' => $filename,
            'userid' => (int)($USER->id ?? 0),
        ];

        // Upsert behavior for same name in same area.
        if ($old = $fs->get_file($context->id, 'block_ai_assistant', $filearea, $courseid, '/', $filename)) {
            $old->delete();
        }
        $fs->create_file_from_string($file_record, base64_decode($base64));

        if ($is_syllabus) {
            $DB->set_field('block_aia_settings', 'syllabus_document_name', $filename, ['courseid' => $courseid]);
        }
    }

    public static function gradebook_upload(int $courseid, string $session_id, string $filename, string $filetype, string $base64): string
    {
        try {
            self::persist_gradebook_uploaded_file($courseid, $filename, $base64);
        } catch (\Throwable $e) {
            // Never block backend upload on Moodle local persistence failure.
        }

        $method = 'cria_gradebook_upload';
        $data = array(
            'session_id' => trim($session_id),
            'filename' => $filename,
            'filetype' => $filetype,
            'base64' => $base64,
        );
        return webservice::exec($method, $data);
    }

    /**
     * Persist (upsert) per-user, per-course gradebook UI state in Moodle DB.
     * Any provided field overwrites the existing one; pass null to leave unchanged.
     *
     * @param int $courseid
     * @param int $userid
     * @param string|null $session_id
     * @param string|null $phase
     * @param string|null $chat_history_json  JSON array of {role, text}
     * @param string|null $confirmed_mapping_json
     * @param string|null $result_json
     * @return array Normalised record persisted.
     */
    public static function gradebook_save_state(
        int $courseid,
        int $userid,
        ?string $session_id = null,
        ?string $phase = null,
        ?string $chat_history_json = null,
        ?string $confirmed_mapping_json = null,
        ?string $result_json = null,
        int $last_known_timemodified = 0,
        bool $force = false
    ): array {
        global $DB;

        $now = time();
        $existing = $DB->get_record(
            'block_aia_gradebook_state',
            ['courseid' => $courseid, 'userid' => $userid]
        );

        $conflict = false;
        if ($existing) {
            // Optimistic concurrency: if the server has a newer record than the client last saw,
            // reject this write (unless caller explicitly asked to force).
            if (!$force
                && $last_known_timemodified > 0
                && (int)$existing->timemodified > $last_known_timemodified) {
                $conflict = true;
            }

            if (!$conflict) {
                $updates = new \stdClass();
                $updates->id = $existing->id;
                $updates->timemodified = $now;
                if ($session_id !== null) {
                    $updates->session_id = $session_id;
                }
                if ($phase !== null) {
                    $updates->phase = $phase;
                }
                // Never overwrite a non-empty stored history/mapping/result with an empty value
                // unless the caller explicitly forced it (e.g. reset). This guarantees a stale
                // tab with empty localStorage cannot wipe a good server record.
                if ($chat_history_json !== null
                    && self::gradebook_should_write_field($existing->chat_history_json, $chat_history_json, $force)) {
                    $updates->chat_history_json = $chat_history_json;
                }
                if ($confirmed_mapping_json !== null
                    && self::gradebook_should_write_field($existing->confirmed_mapping_json, $confirmed_mapping_json, $force)) {
                    $updates->confirmed_mapping_json = $confirmed_mapping_json;
                }
                if ($result_json !== null
                    && self::gradebook_should_write_field($existing->result_json, $result_json, $force)) {
                    $updates->result_json = $result_json;
                }
                $DB->update_record('block_aia_gradebook_state', $updates);
            }
            $record = $DB->get_record('block_aia_gradebook_state', ['id' => $existing->id]);
        } else {
            $insert = new \stdClass();
            $insert->courseid = $courseid;
            $insert->userid = $userid;
            $insert->session_id = $session_id;
            $insert->phase = $phase;
            $insert->chat_history_json = $chat_history_json;
            $insert->confirmed_mapping_json = $confirmed_mapping_json;
            $insert->result_json = $result_json;
            $insert->timecreated = $now;
            $insert->timemodified = $now;
            $id = $DB->insert_record('block_aia_gradebook_state', $insert);
            $record = $DB->get_record('block_aia_gradebook_state', ['id' => $id]);
        }

        return [
            'found' => true,
            'conflict' => $conflict,
            'session_id' => (string)($record->session_id ?? ''),
            'phase' => (string)($record->phase ?? ''),
            'chat_history_json' => (string)($record->chat_history_json ?? ''),
            'confirmed_mapping_json' => (string)($record->confirmed_mapping_json ?? ''),
            'result_json' => (string)($record->result_json ?? ''),
            'timemodified' => (int)($record->timemodified ?? 0),
        ];
    }

    /**
     * Decide whether an incoming JSON blob should replace the stored one.
     *
     * Rules (in order):
     *  - If $force is true, always write.
     *  - If the stored value is empty/null/"[]"/"null", always write (we have nothing to lose).
     *  - If the incoming value is empty or represents an empty array/null but the stored value is non-empty,
     *    DO NOT write — that protects against a stale/new tab clobbering a good record.
     *  - Otherwise, write.
     */
    protected static function gradebook_should_write_field($stored, string $incoming, bool $force): bool
    {
        if ($force) {
            return true;
        }
        $stored_str = (string)($stored ?? '');
        $stored_trim = trim($stored_str);
        $stored_is_empty = ($stored_trim === '' || $stored_trim === '[]' || $stored_trim === 'null' || $stored_trim === '{}');
        if ($stored_is_empty) {
            return true;
        }
        $incoming_trim = trim($incoming);
        $incoming_is_empty = ($incoming_trim === '' || $incoming_trim === '[]' || $incoming_trim === 'null' || $incoming_trim === '{}');
        if ($incoming_is_empty) {
            return false;
        }
        return true;
    }

    /**
     * Load per-user, per-course gradebook UI state. Always returns an array; 'found' flag tells if a row exists.
     */
    public static function gradebook_get_state(int $courseid, int $userid): array
    {
        global $DB;

        $record = $DB->get_record(
            'block_aia_gradebook_state',
            ['courseid' => $courseid, 'userid' => $userid]
        );

        if (!$record) {
            return [
                'found' => false,
                'session_id' => '',
                'phase' => '',
                'chat_history_json' => '',
                'confirmed_mapping_json' => '',
                'result_json' => '',
                'timemodified' => 0,
            ];
        }

        return [
            'found' => true,
            'session_id' => (string)($record->session_id ?? ''),
            'phase' => (string)($record->phase ?? ''),
            'chat_history_json' => (string)($record->chat_history_json ?? ''),
            'confirmed_mapping_json' => (string)($record->confirmed_mapping_json ?? ''),
            'result_json' => (string)($record->result_json ?? ''),
            'timemodified' => (int)($record->timemodified ?? 0),
        ];
    }

    /**
     * Clear the persisted gradebook UI state for a user+course (used when session expired or user resets).
     */
    public static function gradebook_clear_state(int $courseid, int $userid): void
    {
        global $DB;
        $DB->delete_records(
            'block_aia_gradebook_state',
            ['courseid' => $courseid, 'userid' => $userid]
        );
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