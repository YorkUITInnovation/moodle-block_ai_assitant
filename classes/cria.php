<?php

namespace block_ai_assistant;


use block_ai_assistant\webservice;
use Exception;
use Throwable;

class cria
{

    /**
     * Recursively serialize a gradebook tree node and collect summary stats.
     *
     * @param array $node Tree node from grade_category::fetch_course_tree().
     * @param array $stats Mutable summary counters.
     * @return array
     */
    private static function serialize_gradebook_tree_node(array $node, array &$stats): array
    {
        $object = $node['object'] ?? null;
        $type = (string)($node['type'] ?? 'unknown');
        $depth = (int)($node['depth'] ?? 0);

        if ($type === 'category') {
            $stats['category_count'] = (int)($stats['category_count'] ?? 0) + 1;
        } else {
            $stats['item_count'] = (int)($stats['item_count'] ?? 0) + 1;
        }
        $stats['max_depth'] = max((int)($stats['max_depth'] ?? 0), $depth);

        $serialized = [
            'type' => $type,
            'depth' => $depth,
            'children' => new \stdClass(),
        ];

        if (is_object($object)) {
            if (method_exists($object, 'get_name')) {
                $serialized['name'] = (string)$object->get_name();
            } else if (property_exists($object, 'fullname')) {
                $serialized['name'] = (string)$object->fullname;
            } else if (property_exists($object, 'itemname')) {
                $serialized['name'] = (string)$object->itemname;
            }

            if (property_exists($object, 'id')) {
                $serialized['id'] = (int)$object->id;
            }
            if (property_exists($object, 'itemtype')) {
                $serialized['itemtype'] = (string)$object->itemtype;
                $stats['item_types'] = $stats['item_types'] ?? [];
                $stats['item_types'][$serialized['itemtype']] = (int)($stats['item_types'][$serialized['itemtype']] ?? 0) + 1;
            }
            if (property_exists($object, 'itemmodule')) {
                $serialized['itemmodule'] = (string)$object->itemmodule;
            }
            if (property_exists($object, 'iteminstance')) {
                $serialized['iteminstance'] = (int)$object->iteminstance;
            }
            if (property_exists($object, 'itemnumber')) {
                $serialized['itemnumber'] = (int)$object->itemnumber;
            }
            if (property_exists($object, 'aggregation')) {
                $serialized['aggregation'] = (int)$object->aggregation;
            }
            if (property_exists($object, 'keephigh')) {
                $serialized['keephigh'] = (int)$object->keephigh;
            }
            if (property_exists($object, 'droplow')) {
                $serialized['droplow'] = (int)$object->droplow;
            }
            if (property_exists($object, 'aggregateonlygraded')) {
                $serialized['aggregateonlygraded'] = (bool)$object->aggregateonlygraded;
            }
            if (property_exists($object, 'aggregateoutcomes')) {
                $serialized['aggregateoutcomes'] = (bool)$object->aggregateoutcomes;
            }
            if (property_exists($object, 'aggregationcoef')) {
                $serialized['aggregationcoef'] = (float)$object->aggregationcoef;
            }
            if (property_exists($object, 'aggregationcoef2')) {
                $serialized['aggregationcoef2'] = (float)$object->aggregationcoef2;
            }
            if (property_exists($object, 'weightoverride')) {
                $serialized['weightoverride'] = (bool)$object->weightoverride;
            }
            if (property_exists($object, 'hidden')) {
                $serialized['hidden'] = (int)$object->hidden;
                if ((int)$object->hidden !== 0) {
                    $stats['has_hidden_items'] = true;
                }
            }
            if (property_exists($object, 'hiddenuntil')) {
                $serialized['hiddenuntil'] = (int)$object->hiddenuntil;
                if ((int)$object->hiddenuntil > 0) {
                    $stats['has_hidden_items'] = true;
                }
            }
            if (method_exists($object, 'is_locked')) {
                $serialized['locked'] = (bool)$object->is_locked();
                if ($serialized['locked']) {
                    $stats['has_locked_items'] = true;
                }
            }
            if (property_exists($object, 'locktime')) {
                $serialized['locktime'] = (int)$object->locktime;
                if ((int)$object->locktime > 0) {
                    $stats['has_locked_items'] = true;
                }
            }
            if (property_exists($object, 'calculation') && trim((string)$object->calculation) !== '') {
                $serialized['calculation'] = (string)$object->calculation;
                $stats['has_formula'] = true;
            }
            if (property_exists($object, 'display')) {
                $serialized['display'] = (int)$object->display;
            }
            if (property_exists($object, 'decimals')) {
                $serialized['decimals'] = (int)$object->decimals;
            }
            if (property_exists($object, 'grademin')) {
                $serialized['grademin'] = (float)$object->grademin;
            }
            if (property_exists($object, 'grademax')) {
                $serialized['grademax'] = (float)$object->grademax;
            }
            if (property_exists($object, 'gradepass')) {
                $serialized['gradepass'] = (float)$object->gradepass;
            }
        }

        if (!empty($node['children']) && is_array($node['children'])) {
            $serialized['children'] = [];
            foreach ($node['children'] as $sortorder => $child) {
                $serialized['children'][(int)$sortorder] = self::serialize_gradebook_tree_node($child, $stats);
            }
        }

        return $serialized;
    }

    /**
     * Detect if a category name is the AI wrapper root (e.g. "<course> - AI Assistant - Gradebook").
     *
     * @param string $name
     * @return bool
     */
    private static function is_ai_gradebook_wrapper_name(string $name): bool
    {
        $normalized = strtolower(trim($name));
        return $normalized !== '' && strpos($normalized, 'ai assistant - gradebook') !== false;
    }

    /**
     * For baseline import, unwrap a single AI wrapper category so backend sees
     * the real top-level grading categories directly.
     *
     * @param array $tree
     * @return array
     */
    private static function normalize_baseline_tree_for_import(array $tree): array
    {
        $children = is_array($tree['children'] ?? null) ? $tree['children'] : [];
        if (empty($children)) {
            return $tree;
        }

        $category_children = [];
        $non_category_children = [];
        foreach ($children as $child) {
            if (is_array($child) && (string)($child['type'] ?? '') === 'category') {
                $category_children[] = $child;
            } else {
                $non_category_children[] = $child;
            }
        }
        if (count($category_children) !== 1) {
            return $tree;
        }

        $only_child = $category_children[0];

        $only_child_name = (string)($only_child['name'] ?? '');
        if (!self::is_ai_gradebook_wrapper_name($only_child_name)) {
            return $tree;
        }

        $grandchildren = is_array($only_child['children'] ?? null) ? $only_child['children'] : [];
        if (empty($grandchildren)) {
            return $tree;
        }

        // Preserve root metadata, expose wrapper children directly, and retain
        // non-category children (for example course total items) at root level.
        // Keep dictionary-like shape (map keyed by sortorder) expected by backend schema.
        $merged_children = [];
        $sortorder = 1;
        foreach ($grandchildren as $child) {
            $merged_children[$sortorder++] = $child;
        }
        foreach ($non_category_children as $child) {
            $merged_children[$sortorder++] = $child;
        }
        $tree['children'] = $merged_children;
        return $tree;
    }

    /**
     * Build a versioned baseline snapshot for an existing Moodle gradebook.
     *
     * @param int $courseid
     * @return array
     */
    private static function build_gradebook_baseline_snapshot(int $courseid): array
    {
        global $CFG;

        try {
            require_once($CFG->libdir . '/gradelib.php');
            require_once($CFG->libdir . '/grade/grade_category.php');
            require_once($CFG->libdir . '/grade/grade_item.php');

            $course_category = \grade_category::fetch_course_category($courseid);
            $tree = \grade_category::fetch_course_tree($courseid, true);

            $stats = [
                'category_count' => 0,
                'item_count' => 0,
                'max_depth' => 0,
                'has_formula' => false,
                'has_locked_items' => false,
                'has_hidden_items' => false,
                'item_types' => [],
            ];

            $serialized_tree = self::serialize_gradebook_tree_node($tree, $stats);
            $serialized_tree = self::normalize_baseline_tree_for_import($serialized_tree);
            // Treat baseline as available only when there is at least one real
            // non-root category. Root-only item/activity setups should start fresh.
            $available = !empty($serialized_tree['children']) && (int)($stats['category_count'] ?? 0) > 1;

            return [
                'contract_name' => 'baseline_gradebook_v1',
                'schema_version' => 1,
                'available' => $available,
                'courseid' => (string)$courseid,
                'root_category' => [
                    'id' => (int)$course_category->id,
                    'name' => (string)$course_category->get_name(),
                    'aggregation' => (int)$course_category->aggregation,
                    'keephigh' => (int)$course_category->keephigh,
                    'droplow' => (int)$course_category->droplow,
                    'aggregateonlygraded' => (bool)$course_category->aggregateonlygraded,
                    'aggregateoutcomes' => (bool)$course_category->aggregateoutcomes,
                ],
                'tree' => $serialized_tree,
                'stats' => $stats,
            ];
        } catch (\Throwable $e) {
            return [
                'contract_name' => 'baseline_gradebook_v1',
                'schema_version' => 1,
                'available' => false,
                'courseid' => (string)$courseid,
                'error' => 'baseline_snapshot_unavailable',
            ];
        }
    }

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

            // Serialize same course/file training uploads to avoid burst re-ingestion
            // that can OOM-kill backend index services.
            $lock_dir = make_temp_directory('block_ai_assistant/training_locks');
            $lock_key = sha1((string)$course_id . '|' . (string)$file_name);
            $lock_path = $lock_dir . '/upload_' . $lock_key . '.lock';
            $lock_handle = @fopen($lock_path, 'c');
            if (!$lock_handle) {
                error_log('block_ai_assistant: upload_content_to_bot failed opening lock file for course ' . (int)$course_id . ' file=' . $file_name);
                return '';
            }

            $lock_wait_ms = (int)($get($local, 'training_upload_lock_wait_ms') ?: $get($block, 'training_upload_lock_wait_ms') ?: 20000);
            $lock_wait_ms = max(5000, min($lock_wait_ms, 120000));
            $lock_started_at = microtime(true);
            $lock_acquired = false;
            while (((microtime(true) - $lock_started_at) * 1000) < $lock_wait_ms) {
                if (@flock($lock_handle, LOCK_EX | LOCK_NB)) {
                    $lock_acquired = true;
                    break;
                }
                usleep(250000);
            }
            if (!$lock_acquired) {
                @fclose($lock_handle);
                error_log('block_ai_assistant: upload_content_to_bot lock timeout for course ' . (int)$course_id . ' file=' . $file_name . ' wait_ms=' . $lock_wait_ms);
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

            $queue_raw = '';
            $job_id = '';
            for ($queue_attempt = 1; $queue_attempt <= 3; $queue_attempt++) {
                $queue_raw = (string)$curl->post($queue_url, $queue_params, $queue_opts);
                $queued = json_decode($queue_raw, true);
                $job_id = is_array($queued) ? (string)($queued['job']['job_id'] ?? '') : '';
                if ($job_id !== '') {
                    break;
                }
                if ($queue_attempt < 3) {
                    sleep($queue_attempt);
                }
            }
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
            $update_url = $criabot_url . '/bots/' . rawurlencode($bot_name) . '/documents/update';
            $upload_opts = [
                'CURLOPT_TIMEOUT' => 120,
                'CURLOPT_HTTPHEADER' => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'X-API-Key: ' . $api_key
                ]
            ];

            $upload_throttle_ms = (int)($get($local, 'training_upload_throttle_ms') ?: $get($block, 'training_upload_throttle_ms') ?: 250);
            if ($upload_throttle_ms > 0) {
                usleep(max(0, min($upload_throttle_ms, 5000)) * 1000);
            }

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

            $try_update = static function () use ($curl, $update_url, $upload_body, $upload_opts): array {
                $update_opts = $upload_opts;
                $update_opts['CURLOPT_CUSTOMREQUEST'] = 'PATCH';
                $update_raw = (string)$curl->post($update_url, json_encode($upload_body), $update_opts);
                $updated = json_decode((string)$update_raw, true);
                $status = is_array($updated) ? (int)($updated['status'] ?? 0) : 0;
                return [
                    'raw' => $update_raw,
                    'updated' => $updated,
                    'status' => $status,
                ];
            };

            $attempt = $try_upload();
            $uploaded = $attempt['uploaded'];
            $upload_raw = $attempt['raw'];
            $is_duplicate = (bool)$attempt['duplicate'];
            $upload_status = (int)$attempt['status'];

            // If document already exists, update in place instead of delete-then-reupload.
            // This prevents data loss if the operation fails mid-flight.
            if ($is_duplicate) {
                $update_attempt = $try_update();
                $updated = $update_attempt['updated'];
                $update_status = (int)$update_attempt['status'];
                if (is_array($updated) && $update_status === 200) {
                    $uploaded = $updated;
                    $upload_raw = (string)$update_attempt['raw'];
                    $upload_status = 200;
                    $is_duplicate = false;
                } else {
                    error_log('block_ai_assistant: upload_content_to_bot failed updating existing document for bot ' . $bot_name . ' status=' . $update_status . ' response=' . substr((string)$update_attempt['raw'], 0, 500));
                    return '';
                }
            }

            // Retry once for transient validation/network race failures.
            if ($upload_status !== 200 && ($upload_status === 422 || $upload_status >= 500)) {
                $attempt = $try_upload();
                $uploaded = $attempt['uploaded'];
                $upload_raw = $attempt['raw'];
                $is_duplicate = (bool)$attempt['duplicate'];
                $upload_status = (int)$attempt['status'];

                if ($is_duplicate) {
                    $update_attempt = $try_update();
                    $updated = $update_attempt['updated'];
                    $update_status = (int)$update_attempt['status'];
                    if (is_array($updated) && $update_status === 200) {
                        $uploaded = $updated;
                        $upload_raw = (string)$update_attempt['raw'];
                        $upload_status = 200;
                        $is_duplicate = false;
                    }
                }
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

            @flock($lock_handle, LOCK_UN);
            @fclose($lock_handle);

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
        $embed_code = '';
        $embed_loader_url = webservice::get_embed_loader_url($bot_name);
        if ($embed_loader_url !== '') {
            $embed_code = '<script type="text/javascript" src="' . $embed_loader_url . '" async> </script>';
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

    /**
     * Build gradeable course activities for Criabot from current Moodle grade items.
     *
     * @param int $courseid
     * @return array<int, array<string, mixed>>
     */
    private static function build_course_activities_for_gradebook(int $courseid): array
    {
        global $DB;

        if ($courseid < 1) {
            return [];
        }

        $activities = [];
        try {
            $modinfo = get_fast_modinfo($courseid);

            $gradeitemsql = "
                SELECT
                    gi.id AS grade_item_id,
                    gi.itemtype,
                    gi.itemmodule,
                    gi.iteminstance,
                    gi.itemname,
                    cm.id AS cmid
                FROM {grade_items} gi
                LEFT JOIN {modules} m
                    ON m.name = gi.itemmodule
                LEFT JOIN {course_modules} cm
                    ON cm.course = gi.courseid
                   AND cm.module = m.id
                   AND cm.instance = gi.iteminstance
                WHERE gi.courseid = :courseid
                  AND gi.itemtype <> 'course'
                  AND gi.itemtype <> 'category'
                  AND (
                        gi.itemtype = 'manual'
                        OR (
                            gi.itemtype = 'mod'
                            AND gi.itemmodule IS NOT NULL
                            AND gi.itemmodule <> ''
                        )
                  )
                ORDER BY gi.id ASC
            ";
            $gradeitems = $DB->get_records_sql($gradeitemsql, ['courseid' => $courseid]);

            foreach ($gradeitems as $gi) {
                $cmid = isset($gi->cmid) ? (int)$gi->cmid : 0;
                $itemtype = trim((string)($gi->itemtype ?? ''));
                $module = trim((string)($gi->itemmodule ?? ''));
                if ($itemtype === 'mod' && $module === '') {
                    continue;
                }

                $activityname = trim((string)($gi->itemname ?? ''));

                if ($cmid > 0) {
                    try {
                        $cm = $modinfo->get_cm($cmid);
                        if ($cm && !$cm->uservisible) {
                            continue;
                        }
                        if ($activityname === '' && $cm) {
                            $activityname = (string)$cm->name;
                        }
                    } catch (\Throwable $e) {
                        // Keep grade item even if cm lookup fails.
                    }
                }

                if ($activityname === '') {
                    if ($itemtype === 'manual') {
                        $activityname = 'Manual Grade Item #' . (int)($gi->grade_item_id ?? 0);
                    } else {
                        $activityname = $module . ' #' . (int)($gi->iteminstance ?? 0);
                    }
                }

                $activities[] = [
                    'cmid' => $cmid > 0 ? $cmid : null,
                    'module' => $module !== '' ? $module : null,
                    'name' => $activityname,
                    'grade_item_id' => (int)$gi->grade_item_id,
                    'itemtype' => $itemtype !== '' ? $itemtype : 'mod',
                ];
            }
        } catch (\Throwable $e) {
            debugging('Could not build gradebook activities for course ' . $courseid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $activities;
    }

    /**
     * @param array $normalized_mapping
     * @return int[]
     */
    private static function extract_preserve_grade_item_ids(array $normalized_mapping, ?array $proposal = null, int $courseid = 0): array
    {
        $ids = [];
        $labelkeys = [];

        foreach ($normalized_mapping as $row) {
            if (!is_array($row)) {
                continue;
            }
            $itemid = (int)($row['grade_item_id'] ?? 0);
            if ($itemid > 0) {
                $ids[$itemid] = true;
            }

            $label = strtolower(trim((string)($row['activity_name'] ?? $row['grade_item_name'] ?? '')));
            if ($label !== '') {
                $labelkeys[$label] = true;
            }
        }

        if (is_array($proposal) && !empty($proposal['categories']) && is_array($proposal['categories'])) {
            foreach ($proposal['categories'] as $cat) {
                if (!is_array($cat)) {
                    continue;
                }
                foreach (($cat['items'] ?? []) as $rawitem) {
                    $label = strtolower(trim((string)$rawitem));
                    if ($label !== '') {
                        $labelkeys[$label] = true;
                    }
                }
            }
        }

        if ($courseid > 0 && !empty($labelkeys)) {
            global $DB;
            foreach (self::_get_ai_owned_grade_item_ids($courseid) as $ownedid) {
                $ownedid = (int)$ownedid;
                if ($ownedid < 1 || !empty($ids[$ownedid])) {
                    continue;
                }
                $record = $DB->get_record('grade_items', ['id' => $ownedid, 'courseid' => $courseid], 'id, itemname', IGNORE_MISSING);
                if (!$record) {
                    continue;
                }
                $namekey = strtolower(trim((string)($record->itemname ?? '')));
                if ($namekey !== '' && !empty($labelkeys[$namekey])) {
                    $ids[$ownedid] = true;
                }
            }
        }

        return array_values(array_map('intval', array_keys($ids)));
    }

    /**
     * Push current Moodle activities (and optional mapping) into the Criabot session.
     */
    private static function sync_gradebook_session_context(
        int $courseid,
        string $session_id,
        ?array $confirmed_mapping = null
    ): void {
        $session_id = trim($session_id);
        if ($courseid < 1 || $session_id === '') {
            return;
        }

        $payload = [
            'session_id' => $session_id,
            'course_activities' => self::build_course_activities_for_gradebook($courseid),
        ];
        if ($confirmed_mapping !== null) {
            $payload['confirmed_mapping'] = $confirmed_mapping;
        }

        try {
            webservice::exec('cria_gradebook_sync', $payload);
        } catch (\Throwable $e) {
            debugging('Gradebook session sync failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    public static function gradebook_start(int $courseid, int $professorid, string $import_mode = ''): string
    {
        global $DB;
        $correlationid = self::build_gradebook_correlation_id('gradebook_start', $courseid, (string)$professorid);
        $bot_name = (string)$DB->get_field('block_aia_settings', 'bot_name', ['courseid' => $courseid]);
        if ($bot_name === '') {
            self::log_gradebook_audit_event(
                $courseid,
                '',
                'gradebook_start',
                'blocked',
                [
                    'reason' => 'bot_not_configured',
                    'import_mode' => trim($import_mode),
                ],
                0,
                $correlationid
            );
            return json_encode([
                'status' => 500,
                'code' => 'BOT_NOT_CONFIGURED',
                'message' => 'Bot is not configured for this course.',
                'data' => ['gradebook_audit_correlation_id' => $correlationid],
            ]);
        }

        $activities = self::build_course_activities_for_gradebook($courseid);
        $resources = [];
        try {
            $modinfo = get_fast_modinfo($courseid);

            foreach ($modinfo->get_cms() as $cm) {
                if (!$cm->uservisible) {
                    continue;
                }
                $sectionnum = isset($cm->sectionnum) ? (string)$cm->sectionnum : '';
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

            // Patch: enumerate all files in section 0 and add to moodle_resources if not already present
            $context = \context_course::instance($courseid);
            $fs = get_file_storage();
            $files = $fs->get_area_files($context->id, 'course', 'section', 0, 'filename', false);
            foreach ($files as $file) {
                if ($file->get_filesize() <= 0 || $file->is_directory()) {
                    continue;
                }
                $filename = (string)$file->get_filename();
                $already = false;
                foreach ($resources as $r) {
                    if (isset($r['name']) && $r['name'] === $filename) {
                        $already = true;
                        break;
                    }
                }
                if (!$already) {
                    $resources[] = [
                        'cmid' => null,
                        'type' => 'file',
                        'name' => $filename,
                        'section' => '0',
                        'content_url' => '',
                        'content_preview' => 'File in section 0',
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

        // Intentionally do not include gradebook chat uploads from previous sessions
        // to avoid stale context leaking into new gradebook sessions.

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

        $baseline_snapshot = self::build_gradebook_baseline_snapshot($courseid);
        $normalized_import_mode = strtolower(trim($import_mode));
        if ($normalized_import_mode !== 'fresh' && $normalized_import_mode !== 'baseline') {
            $normalized_import_mode = !empty($baseline_snapshot['available']) ? 'baseline' : 'fresh';
        }
        if ($normalized_import_mode === 'baseline' && empty($baseline_snapshot['available'])) {
            $normalized_import_mode = 'fresh';
        }

        $resources = self::dedupe_moodle_resources_by_name($resources);

        self::log_gradebook_audit_event(
            $courseid,
            '',
            'gradebook_start',
            'requested',
            [
                'professor_id' => $professorid,
                'import_mode' => $normalized_import_mode,
                'baseline_available' => !empty($baseline_snapshot['available']),
                'baseline_policy' => !empty($baseline_snapshot['available']) ? 'mirror_then_override' : 'generate_fresh',
                'context_source' => !empty($baseline_snapshot['available']) ? 'baseline_import' : 'syllabus_generation',
            ],
            0,
            $correlationid
        );

        $method = 'cria_gradebook_start';
        $data = array(
            'course_id' => (string)$courseid,
            'professor_id' => (string)$professorid,
            'bot_name' => $bot_name,
            'moodle_resources' => $resources,
            'course_activities' => $activities,
            'baseline_snapshot' => $baseline_snapshot,
            'import_mode' => $normalized_import_mode,
        );
        $response_json = webservice::exec($method, $data);
        $response = json_decode($response_json, true);
        if (is_array($response)) {
            $sessionid = trim((string)($response['session_id'] ?? ''));
            if (
                $normalized_import_mode === 'baseline'
                && !empty($baseline_snapshot['available'])
                && $sessionid !== ''
            ) {
                $startwarnings = [];
                self::ensure_preapply_baseline_snapshot($courseid, $sessionid, $startwarnings, $correlationid);
            }

            $response['data'] = array_merge(
                is_array($response['data'] ?? null) ? $response['data'] : [],
                [
                    'gradebook_audit_correlation_id' => $correlationid,
                    'gradebook_audit_event' => 'gradebook_start',
                ]
            );
            $response_json = json_encode($response);
        }
        return $response_json;
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
        $hascoursechanges = $courseid > 0 ? self::course_needs_gradebook_cleanup($courseid) : false;
        $localuploadedfilesremoved = 0;

        if ($courseid > 0) {
            $localuploadedfilesremoved = self::remove_gradebook_chat_uploaded_files($courseid);
        }

        $method = 'cria_gradebook_delete';
        $data = array(
            'session_id' => $session_id,
        );
        $result = webservice::exec($method, $data);
        $decoded = json_decode($result, true);

        $backendnotfound = is_array($decoded)
            && ((int)($decoded['status'] ?? 0) === 404 || (string)($decoded['code'] ?? '') === 'NOT_FOUND');

        $cleanup_result = [
            'cleaned' => false,
            'message' => '',
        ];
        if ($courseid > 0) {
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
        } else if ($backendnotfound && !$hascoursechanges) {
            // No active backend session and no Moodle grade setup changes.
            // Keep this as success so UI can still clear local chat/session state.
            $decoded['status'] = 200;
            $decoded['code'] = 'SUCCESS';
            $decoded['message'] = get_string('gradebook_delete_completed', 'block_ai_assistant');
        }

        $decoded['data'] = array_merge(
            is_array($decoded['data'] ?? null) ? $decoded['data'] : [],
            [
                'success' => true,
                'existed' => !$backendnotfound,
                'local_gradebook_files_removed' => $localuploadedfilesremoved,
                'grade_setup_cleaned' => (bool)($cleanup_result['cleaned'] ?? false),
                'grade_setup_message' => $hascoursechanges
                    ? (string)($cleanup_result['message'] ?? '')
                    : get_string('gradebook_delete_nothing', 'block_ai_assistant'),
                'course_changes_detected' => $hascoursechanges,
            ]
        );

        return json_encode($decoded);
    }

    /**
     * Revert Moodle grade setup to an immutable baseline snapshot.
     */
    public static function gradebook_revert(int $courseid, string $session_id, int $revision = 0): string
    {
        $session_id = trim($session_id);
        $correlationid = self::build_gradebook_correlation_id('gradebook_revert', $courseid, $session_id);
        if ($courseid < 1 || $session_id === '') {
            self::log_gradebook_audit_event(
                $courseid,
                $session_id,
                'gradebook_revert',
                'blocked',
                ['reason' => 'missing_course_or_session', 'revision' => $revision],
                $revision,
                $correlationid
            );
            return json_encode([
                'status' => 400,
                'code' => 'INVALID_ARGUMENTS',
                'message' => 'Missing course/session reference for gradebook revert.',
                'data' => ['gradebook_audit_correlation_id' => $correlationid],
            ]);
        }

        $details = [];
        self::log_gradebook_audit_event(
            $courseid,
            $session_id,
            'gradebook_revert_requested',
            'requested',
            ['revision' => $revision],
            $revision,
            $correlationid
        );

        $restored = self::restore_gradebook_from_snapshot($courseid, $session_id, $revision, $details, $correlationid);
        if ($restored) {
            self::ensure_course_gradebook_integrity($courseid);
        }
        if (!$restored) {
            self::log_gradebook_audit_event(
                $courseid,
                $session_id,
                'gradebook_revert_failed',
                'failed',
                $details,
                $revision,
                $correlationid
            );
            return json_encode([
                'status' => 422,
                'code' => 'REVERT_FAILED',
                'message' => get_string('gradebook_revert_failed', 'block_ai_assistant'),
                'data' => array_merge($details, ['gradebook_audit_correlation_id' => $correlationid]),
            ]);
        }

        $backendDeleted = false;
        try {
            $deleteRaw = self::gradebook_delete($session_id, 0);
            $deleteDecoded = json_decode($deleteRaw, true);
            $backendDeleted = is_array($deleteDecoded)
                && (int)($deleteDecoded['status'] ?? 0) >= 200
                && (int)($deleteDecoded['status'] ?? 0) < 300;
        } catch (\Throwable $e) {
            $backendDeleted = false;
            $details['warnings'] = is_array($details['warnings'] ?? null) ? $details['warnings'] : [];
            $details['warnings'][] = '⚠ Revert completed but backend session cleanup failed: ' . $e->getMessage();
        }

        $details['backend_session_deleted'] = $backendDeleted;

        self::log_gradebook_audit_event(
            $courseid,
            $session_id,
            'gradebook_revert_completed',
            'success',
            $details,
            $revision,
            $correlationid
        );

        return json_encode([
            'status' => 200,
            'code' => 'REVERTED',
            'message' => get_string('gradebook_revert_completed', 'block_ai_assistant'),
            'data' => array_merge($details, ['gradebook_audit_correlation_id' => $correlationid]),
        ]);
    }

    private static function course_has_ai_gradebook(int $courseid): bool
    {
        return self::count_ai_gradebook_roots($courseid) > 0;
    }

    /**
     * Resolve gradebook session import mode from a status payload or Criabot session.
     */
    private static function resolve_gradebook_session_import_mode(
        int $courseid,
        string $sessionid,
        ?array $status = null,
        bool $allowstatusfetch = true
    ): string {
        if (is_array($status)) {
            $candidates = [
                $status['import_mode'] ?? null,
                $status['session']['extraction']['import_mode'] ?? null,
                $status['extraction']['import_mode'] ?? null,
                $status['session']['import_mode'] ?? null,
            ];
            foreach ($candidates as $candidate) {
                $mode = strtolower(trim((string)$candidate));
                if ($mode === 'baseline' || $mode === 'fresh') {
                    return $mode;
                }
            }
        }

        $sessionid = trim($sessionid);
        if (!$allowstatusfetch || $courseid < 1 || $sessionid === '') {
            return '';
        }

        try {
            $raw = self::gradebook_status($sessionid);
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return '';
            }
            return self::resolve_gradebook_session_import_mode($courseid, $sessionid, $decoded, false);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Revert is only meaningful when the session started from an existing gradebook baseline.
     * Fresh sessions should use Delete instead (same outcome, clearer UX).
     */
    public static function gradebook_revert_available(int $courseid, string $sessionid = '', ?array $sessionstatus = null): bool
    {
        $sessionid = trim($sessionid);
        if ($courseid < 1 || $sessionid === '') {
            return false;
        }
        if (!self::course_has_ai_gradebook($courseid)) {
            return false;
        }
        if (self::resolve_gradebook_session_import_mode($courseid, $sessionid, $sessionstatus) !== 'baseline') {
            return false;
        }
        if (!self::gradebook_snapshot_ledger_available()) {
            return false;
        }

        return self::load_gradebook_snapshot_record($courseid, $sessionid, 0) !== null;
    }

    /**
     * Restore category/item placement from snapshot ledger payload.
     *
     * @param int $courseid
     * @param string $sessionid
     * @param int $revision
     * @param array $details
     * @return bool
     */
    private static function restore_gradebook_from_snapshot(int $courseid, string $sessionid, int $revision, array &$details = [], string $correlationid = ''): bool
    {
        global $DB, $CFG;

        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->libdir . '/grade/grade_category.php');
        require_once($CFG->libdir . '/grade/grade_item.php');

        $details = [
            'requested_revision' => $revision,
            'restored_revision' => null,
            'snapshot_type' => 'baseline',
            'restored_categories' => 0,
            'restored_items' => 0,
            'warnings' => [],
        ];

        if (!self::gradebook_snapshot_ledger_available()) {
            $details['warnings'][] = 'Snapshot ledger table is missing. Run plugin upgrade first.';
            self::log_gradebook_audit_event($courseid, $sessionid, 'gradebook_restore_snapshot', 'blocked', $details, $revision, $correlationid);
            return false;
        }

        $snapshotrecord = self::load_gradebook_snapshot_record($courseid, $sessionid, $revision);
        if (!$snapshotrecord) {
            $details['warnings'][] = 'No baseline snapshot was found for this session.';
            self::log_gradebook_audit_event($courseid, $sessionid, 'gradebook_restore_snapshot', 'missing_snapshot', $details, $revision, $correlationid);
            return false;
        }

        $payload = self::decode_gradebook_snapshot_payload($snapshotrecord);
        if (!$payload) {
            $details['warnings'][] = 'Snapshot payload is not readable.';
            self::log_gradebook_audit_event($courseid, $sessionid, 'gradebook_restore_snapshot', 'corrupt_snapshot', $details, $revision, $correlationid);
            return false;
        }

        $gradebooksnapshot = is_array($payload['gradebook_snapshot'] ?? null) ? $payload['gradebook_snapshot'] : [];
        $tree = is_array($gradebooksnapshot['tree'] ?? null) ? $gradebooksnapshot['tree'] : [];
        if (empty($tree)) {
            $details['warnings'][] = 'Snapshot tree is empty.';
            self::log_gradebook_audit_event($courseid, $sessionid, 'gradebook_restore_snapshot', 'empty_snapshot', $details, $revision, $correlationid);
            return false;
        }

        $details['restored_revision'] = (int)($snapshotrecord->revision ?? 0);
        $details['snapshot_type'] = (string)($snapshotrecord->snapshot_type ?? 'baseline');

        $categorysettings = [];
        $itemcategorymap = [];
        $categoryitemsettings = [];
        self::collect_snapshot_restore_targets($tree, 0, $categorysettings, $itemcategorymap, $categoryitemsettings);

        $coursecategory = \grade_category::fetch_course_category($courseid);
        if (!$coursecategory) {
            $details['warnings'][] = 'Could not resolve course root category during restore.';
            self::log_gradebook_audit_event($courseid, $sessionid, 'gradebook_restore_snapshot', 'failed', $details, $revision, $correlationid);
            return false;
        }

        $snapshotrootid = (int)($gradebooksnapshot['root_category']['id'] ?? 0);
        if ($snapshotrootid < 1) {
            $snapshotrootid = (int)$coursecategory->id;
        }

        try {
            $tx = $DB->start_delegated_transaction();

            $cleanup = self::remove_ai_gradebook_from_course($courseid);
            $details['warnings'][] = (string)($cleanup['message'] ?? '');

            // Resolve/recreate snapshot categories only after AI-tree cleanup,
            // otherwise IDs can point to categories that are removed above.
            $resolvedcategoryids = [
                0 => 0,
                $snapshotrootid => (int)$coursecategory->id,
            ];
            foreach (array_keys($categorysettings) as $snapshotcategoryid) {
                self::resolve_snapshot_category_target(
                    $courseid,
                    (int)$snapshotcategoryid,
                    $categorysettings,
                    $resolvedcategoryids,
                    (int)$coursecategory->id,
                    $details['warnings']
                );
            }

            $restoredcategories = 0;
            $createdcategories = 0;
            foreach ($categorysettings as $categoryid => $meta) {
                $categoryid = (int)$categoryid;
                $targetcategoryid = (int)($resolvedcategoryids[$categoryid] ?? 0);
                if ($targetcategoryid < 1) {
                    continue;
                }

                if ($targetcategoryid !== $categoryid) {
                    $createdcategories++;
                }

                $record = $DB->get_record('grade_categories', ['id' => $targetcategoryid, 'courseid' => $courseid], '*', IGNORE_MISSING);
                if (!$record) {
                    continue;
                }

                $update = new \stdClass();
                $update->id = $targetcategoryid;
                $changed = false;

                $fields = ['aggregation', 'keephigh', 'droplow', 'aggregateonlygraded', 'aggregateoutcomes', 'hidden'];
                foreach ($fields as $field) {
                    if (!array_key_exists($field, $meta)) {
                        continue;
                    }
                    $newValue = $meta[$field];
                    if ($field === 'aggregateonlygraded' || $field === 'aggregateoutcomes') {
                        $newValue = (int)((bool)$newValue);
                    } else {
                        $newValue = (int)$newValue;
                    }

                    if ((int)$record->{$field} !== $newValue) {
                        $update->{$field} = $newValue;
                        $changed = true;
                    }
                }

                if (array_key_exists('parent', $meta)) {
                    $parentsnapshotid = (int)$meta['parent'];
                    $parentid = (int)($resolvedcategoryids[$parentsnapshotid] ?? 0);
                    if ($parentid !== $targetcategoryid && (int)$record->parent !== $parentid) {
                        if ($parentid === 0 || $DB->record_exists('grade_categories', ['id' => $parentid, 'courseid' => $courseid])) {
                            $update->parent = $parentid;
                            $changed = true;
                        }
                    }
                }

                if ($changed) {
                    $DB->update_record('grade_categories', $update);
                    $restoredcategories++;
                }
            }

            foreach ($categoryitemsettings as $snapshotcategoryid => $meta) {
                $snapshotcategoryid = (int)$snapshotcategoryid;
                $targetcategoryid = (int)($resolvedcategoryids[$snapshotcategoryid] ?? 0);
                if ($targetcategoryid < 1) {
                    continue;
                }

                $categoryitem = $DB->get_record(
                    'grade_items',
                    [
                        'courseid' => $courseid,
                        'itemtype' => 'category',
                        'iteminstance' => $targetcategoryid,
                    ],
                    '*',
                    IGNORE_MISSING
                );
                if (!$categoryitem) {
                    continue;
                }

                $update = new \stdClass();
                $update->id = (int)$categoryitem->id;
                $changed = false;

                $parentsnapshotid = (int)($categorysettings[$snapshotcategoryid]['parent'] ?? 0);
                $targetparentid = (int)($resolvedcategoryids[$parentsnapshotid] ?? 0);
                if ($targetparentid > 0 && (int)$categoryitem->categoryid !== $targetparentid) {
                    $update->categoryid = $targetparentid;
                    $changed = true;
                }

                if (array_key_exists('aggregationcoef', $meta) && $meta['aggregationcoef'] !== null) {
                    $coef = (float)$meta['aggregationcoef'];
                    if ((float)$categoryitem->aggregationcoef !== $coef) {
                        $update->aggregationcoef = $coef;
                        $changed = true;
                    }
                }

                if (array_key_exists('aggregationcoef2', $meta) && $meta['aggregationcoef2'] !== null) {
                    $coef2 = (float)$meta['aggregationcoef2'];
                    if ((float)$categoryitem->aggregationcoef2 !== $coef2) {
                        $update->aggregationcoef2 = $coef2;
                        $changed = true;
                    }
                }

                if (array_key_exists('weightoverride', $meta) && $meta['weightoverride'] !== null) {
                    $weightoverride = (int)((bool)$meta['weightoverride']);
                    if ((int)$categoryitem->weightoverride !== $weightoverride) {
                        $update->weightoverride = $weightoverride;
                        $changed = true;
                    }
                }

                if ($changed) {
                    $DB->update_record('grade_items', $update);
                }
            }

            $restorestats = [
                'restored_items' => 0,
                'created_items' => 0,
                'categories_updated' => 0,
            ];
            self::restore_snapshot_tree_from_node(
                $courseid,
                $tree,
                (int)$coursecategory->id,
                $categorysettings,
                $resolvedcategoryids,
                (int)$coursecategory->id,
                $details['warnings'],
                $restorestats
            );

            $integrity = self::ensure_course_gradebook_integrity($courseid);

            $tx->allow_commit();
            grade_regrade_final_grades($courseid);

            $details['restored_categories'] = $restoredcategories;
            $details['recreated_or_remapped_categories'] = $createdcategories;
            $details['restored_items'] = (int)($restorestats['restored_items'] ?? 0);
            $details['created_items'] = (int)($restorestats['created_items'] ?? 0);
            $details['categories_updated'] = (int)($restorestats['categories_updated'] ?? 0);
            $details['orphaned_items_removed'] = (int)$integrity['removed'];
            $details['structural_items_repaired'] = (int)$integrity['fixed'];
            $details['course_total_items'] = (int)$integrity['course_items'];

            self::log_gradebook_audit_event($courseid, $sessionid, 'gradebook_restore_snapshot', 'success', $details, $revision, $correlationid);

            return true;
        } catch (\Throwable $e) {
            $details['warnings'][] = 'Revert transaction failed: ' . $e->getMessage();
            self::log_gradebook_audit_event($courseid, $sessionid, 'gradebook_restore_snapshot', 'failed', $details, $revision, $correlationid);
            return false;
        }
    }

    private static function load_gradebook_snapshot_record(int $courseid, string $sessionid, int $revision = 0): ?\stdClass
    {
        global $DB;

        if ($courseid < 1 || trim($sessionid) === '') {
            return null;
        }

        if ($revision > 0) {
            $record = $DB->get_record(
                'block_aia_gradebook_snapshots',
                [
                    'courseid' => $courseid,
                    'session_id' => $sessionid,
                    'revision' => $revision,
                ]
            );
            return $record ?: null;
        }

        $sql = 'SELECT *
                  FROM {block_aia_gradebook_snapshots}
                 WHERE courseid = ?
                   AND session_id = ?
                   AND snapshot_type = ?
              ORDER BY revision DESC';
        $records = $DB->get_records_sql($sql, [$courseid, $sessionid, 'baseline'], 0, 1);
        if (!$records) {
            return null;
        }

        return reset($records) ?: null;
    }

    private static function decode_gradebook_snapshot_payload(\stdClass $record): ?array
    {
        $json = trim((string)($record->payload_json ?? ''));
        if ($json === '' && !empty($record->payload_compressed) && function_exists('gzdecode')) {
            $raw = @base64_decode((string)$record->payload_compressed, true);
            if ($raw !== false && $raw !== '') {
                $decoded = @gzdecode($raw);
                if (is_string($decoded) && $decoded !== '') {
                    $json = $decoded;
                }
            }
        }

        if ($json === '') {
            return null;
        }

        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            return null;
        }

        return $payload;
    }

    private static function collect_snapshot_restore_targets(
        array $node,
        int $parentcategoryid,
        array &$categorysettings,
        array &$itemcategorymap,
        array &$categoryitemsettings
    ): void
    {
        $type = (string)($node['type'] ?? '');
        $nodeid = (int)($node['id'] ?? 0);

        $nextparentid = $parentcategoryid;

        if ($type === 'category' && $nodeid > 0) {
            $categorysettings[$nodeid] = [
                'parent' => $parentcategoryid,
                'fullname' => trim((string)($node['name'] ?? '')),
            ];

            $fields = ['aggregation', 'keephigh', 'droplow', 'aggregateonlygraded', 'aggregateoutcomes', 'hidden'];
            foreach ($fields as $field) {
                if (array_key_exists($field, $node)) {
                    $categorysettings[$nodeid][$field] = $node[$field];
                }
            }

            $nextparentid = $nodeid;
        }

        $itemtype = (string)($node['itemtype'] ?? '');
        if ($type !== 'category' && $itemtype === 'category' && $parentcategoryid > 0) {
            $categoryitemsettings[$parentcategoryid] = [
                'aggregationcoef' => array_key_exists('aggregationcoef', $node) ? $node['aggregationcoef'] : null,
                'aggregationcoef2' => array_key_exists('aggregationcoef2', $node) ? $node['aggregationcoef2'] : null,
                'weightoverride' => array_key_exists('weightoverride', $node) ? $node['weightoverride'] : null,
            ];
        }

        if ($type !== 'category' && $itemtype !== 'category' && $nodeid > 0 && $parentcategoryid > 0) {
            $itemcategorymap[$nodeid] = $parentcategoryid;
        }

        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        foreach ($children as $child) {
            if (!is_array($child)) {
                continue;
            }
            self::collect_snapshot_restore_targets($child, $nextparentid, $categorysettings, $itemcategorymap, $categoryitemsettings);
        }
    }

    /**
     * Resolve snapshot category id to an existing/current Moodle category id.
     * Recreates missing categories from snapshot name+parent when necessary.
     */
    private static function resolve_snapshot_category_target(
        int $courseid,
        int $snapshotcategoryid,
        array $categorysettings,
        array &$resolved,
        int $coursecategoryid,
        array &$warnings = []
    ): int {
        global $DB;

        if ($snapshotcategoryid < 1) {
            return 0;
        }

        if (array_key_exists($snapshotcategoryid, $resolved)) {
            return (int)$resolved[$snapshotcategoryid];
        }

        $meta = $categorysettings[$snapshotcategoryid] ?? null;
        if (!is_array($meta)) {
            $resolved[$snapshotcategoryid] = 0;
            return 0;
        }

        $parentsnapshotid = (int)($meta['parent'] ?? 0);
        $resolvedparentid = $parentsnapshotid > 0
            ? self::resolve_snapshot_category_target($courseid, $parentsnapshotid, $categorysettings, $resolved, $coursecategoryid, $warnings)
            : 0;

        if ($resolvedparentid < 1) {
            $resolvedparentid = $coursecategoryid;
        }

        $record = $DB->get_record('grade_categories', ['id' => $snapshotcategoryid, 'courseid' => $courseid], 'id,parent,fullname', IGNORE_MISSING);

        if (!$record) {
            $fullname = trim((string)($meta['fullname'] ?? ''));
            if ($fullname !== '') {
                $existinglist = \grade_category::fetch_all([
                    'courseid' => $courseid,
                    'fullname' => $fullname,
                ]);

                if ($existinglist) {
                    foreach ($existinglist as $existing) {
                        if ((int)$existing->parent === (int)$resolvedparentid) {
                            $record = (object)[
                                'id' => (int)$existing->id,
                                'parent' => (int)$existing->parent,
                                'fullname' => (string)$fullname,
                            ];
                            break;
                        }
                    }
                }
            }
        }

        if (!$record) {
            $fullname = trim((string)($meta['fullname'] ?? ''));
            if ($fullname === '') {
                $resolved[$snapshotcategoryid] = 0;
                return 0;
            }

            try {
                $gc = new \grade_category(['courseid' => $courseid], false);
                $gc->fullname = $fullname;
                $gc->courseid = $courseid;
                if ($resolvedparentid > 0) {
                    $gc->parent = $resolvedparentid;
                }
                $gc->insert('block_ai_assistant');
                if ($resolvedparentid > 0 && (int)$gc->parent !== (int)$resolvedparentid) {
                    $gc->set_parent((int)$resolvedparentid);
                }

                $record = (object)[
                    'id' => (int)$gc->id,
                    'parent' => (int)($gc->parent ?? 0),
                    'fullname' => $fullname,
                ];
                $warnings[] = 'ℹ Recreated missing snapshot category "' . $fullname . '" during restore.';
            } catch (\Throwable $e) {
                $warnings[] = '⚠ Could not recreate snapshot category "' . $fullname . '": ' . $e->getMessage();
                $resolved[$snapshotcategoryid] = 0;
                return 0;
            }
        }

        $resolved[$snapshotcategoryid] = (int)$record->id;
        return (int)$record->id;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function iter_snapshot_tree_children(array $node): array
    {
        $children = $node['children'] ?? null;
        if ($children instanceof \stdClass) {
            $children = (array)$children;
        }
        if (!is_array($children)) {
            return [];
        }

        $keys = array_keys($children);
        $islist = ($keys === range(0, count($children) - 1));
        if ($islist) {
            return array_values(array_filter($children, 'is_array'));
        }

        $list = [];
        foreach ($children as $child) {
            if (is_array($child)) {
                $list[] = $child;
            }
        }
        return $list;
    }

    private static function is_snapshot_category_node(array $node): bool
    {
        return strtolower(trim((string)($node['type'] ?? ''))) === 'category';
    }

    private static function is_snapshot_non_restorable_grade_node(array $node): bool
    {
        $itemtype = strtolower(trim((string)($node['itemtype'] ?? '')));
        return $itemtype === 'course' || $itemtype === 'category';
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function find_snapshot_category_total_child(array $categorynode): ?array
    {
        foreach (self::iter_snapshot_tree_children($categorynode) as $child) {
            if (strtolower(trim((string)($child['itemtype'] ?? ''))) === 'category') {
                return $child;
            }
        }
        return null;
    }

    private static function apply_snapshot_grade_category_settings(int $courseid, int $categoryid, array $node, array &$warnings): bool
    {
        global $DB;

        if ($categoryid < 1) {
            return false;
        }

        $record = $DB->get_record('grade_categories', ['id' => $categoryid, 'courseid' => $courseid], '*', IGNORE_MISSING);
        if (!$record) {
            return false;
        }

        $update = new \stdClass();
        $update->id = $categoryid;
        $changed = false;
        $fields = ['aggregation', 'keephigh', 'droplow', 'aggregateonlygraded', 'aggregateoutcomes', 'hidden'];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $node)) {
                continue;
            }
            $newvalue = $node[$field];
            if ($field === 'aggregateonlygraded' || $field === 'aggregateoutcomes') {
                $newvalue = (int)((bool)$newvalue);
            } else {
                $newvalue = (int)$newvalue;
            }
            if ((int)$record->{$field} !== $newvalue) {
                $update->{$field} = $newvalue;
                $changed = true;
            }
        }

        if ($changed) {
            $DB->update_record('grade_categories', $update);
        }

        return $changed;
    }

    private static function apply_snapshot_category_total_settings(int $courseid, int $categoryid, array $categorynode, array &$warnings): bool
    {
        global $DB;

        if ($categoryid < 1) {
            return false;
        }

        $gi = \grade_item::fetch(['courseid' => $courseid, 'itemtype' => 'category', 'iteminstance' => $categoryid]);
        if (!$gi) {
            return false;
        }

        $totalchild = self::find_snapshot_category_total_child($categorynode);
        $sources = [$categorynode];
        if (is_array($totalchild)) {
            $sources[] = $totalchild;
        }

        $changed = false;
        foreach (['aggregationcoef', 'aggregationcoef2', 'weightoverride', 'hidden', 'hiddenuntil', 'locktime', 'display', 'decimals', 'grademin', 'grademax', 'gradepass'] as $field) {
            $value = null;
            foreach ($sources as $source) {
                if (array_key_exists($field, $source) && $source[$field] !== null) {
                    $value = $source[$field];
                    break;
                }
            }
            if ($value === null) {
                continue;
            }

            if ($field === 'weightoverride') {
                $newvalue = (int)((bool)$value);
            } else if (in_array($field, ['hidden', 'hiddenuntil', 'locktime', 'display', 'decimals'], true)) {
                $newvalue = (int)$value;
            } else {
                $newvalue = (float)$value;
            }

            $current = $gi->{$field} ?? null;
            if (is_float($newvalue)) {
                if ($current === null || abs((float)$current - $newvalue) > 0.00001) {
                    $gi->{$field} = $newvalue;
                    $changed = true;
                }
            } else if ((int)$current !== $newvalue) {
                $gi->{$field} = $newvalue;
                $changed = true;
            }
        }

        $calculation = '';
        foreach ($sources as $source) {
            $candidate = trim((string)($source['calculation'] ?? ''));
            if ($candidate !== '') {
                $calculation = $candidate;
                break;
            }
        }
        if ($calculation !== '') {
            try {
                if (method_exists($gi, 'set_calculation')) {
                    $gi->set_calculation($calculation, null);
                } else {
                    $gi->calculation = $calculation;
                }
                $changed = true;
            } catch (\Throwable $e) {
                $warnings[] = '⚠ Could not restore category formula for category #' . $categoryid . ': ' . $e->getMessage();
            }
        }

        if ($changed) {
            $gi->update('block_ai_assistant');
        }

        return $changed;
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function find_snapshot_grade_item(int $courseid, array $node): ?\grade_item
    {
        global $DB;

        $itemtype = strtolower(trim((string)($node['itemtype'] ?? '')));
        $itemmodule = strtolower(trim((string)($node['itemmodule'] ?? '')));
        $iteminstance = (int)($node['iteminstance'] ?? 0);
        $name = trim((string)($node['name'] ?? ''));

        if ($itemtype === 'mod' && $itemmodule !== '' && $iteminstance > 0) {
            $gi = \grade_item::fetch([
                'courseid' => $courseid,
                'itemtype' => 'mod',
                'itemmodule' => $itemmodule,
                'iteminstance' => $iteminstance,
            ]);
            if ($gi) {
                return $gi;
            }
        }

        if ($name === '' || $itemtype === '') {
            return null;
        }

        $records = $DB->get_records_select(
            'grade_items',
            "courseid = ? AND itemtype = ? AND LOWER(itemname) = ?",
            [$courseid, $itemtype, strtolower($name)],
            'id ASC',
            'id'
        );
        if (count($records) === 1) {
            $record = reset($records);
            return \grade_item::fetch(['id' => (int)$record->id]);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function create_manual_grade_item_from_snapshot(int $courseid, int $categoryid, array $node, array &$warnings): ?\grade_item
    {
        $name = trim((string)($node['name'] ?? ''));
        if ($categoryid < 1 || $name === '') {
            return null;
        }

        try {
            $gi = new \grade_item();
            $gi->courseid = $courseid;
            $gi->categoryid = $categoryid;
            $gi->itemtype = 'manual';
            $gi->itemname = $name;
            $gi->grademin = array_key_exists('grademin', $node) ? (float)$node['grademin'] : 0.0;
            $gi->grademax = array_key_exists('grademax', $node) ? (float)$node['grademax'] : 100.0;
            if (defined('GRADE_TYPE_VALUE')) {
                $gi->gradetype = GRADE_TYPE_VALUE;
            }
            $gi->insert('block_ai_assistant');
            $warnings[] = 'ℹ Recreated manual grade item "' . $name . '" during baseline restore.';
            return $gi;
        } catch (\Throwable $e) {
            $warnings[] = '⚠ Could not recreate manual grade item "' . $name . '": ' . $e->getMessage();
            return null;
        }
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function apply_snapshot_grade_item_settings(\grade_item $gi, array $node): bool
    {
        $changed = false;
        $fields = [
            'aggregationcoef' => 'float',
            'aggregationcoef2' => 'float',
            'weightoverride' => 'bool',
            'hidden' => 'int',
            'hiddenuntil' => 'int',
            'locktime' => 'int',
            'display' => 'int',
            'decimals' => 'int',
            'grademin' => 'float',
            'grademax' => 'float',
            'gradepass' => 'float',
        ];

        foreach ($fields as $field => $kind) {
            if (!array_key_exists($field, $node)) {
                continue;
            }
            $value = $node[$field];
            if ($kind === 'bool') {
                $newvalue = (int)((bool)$value);
            } else if ($kind === 'int') {
                $newvalue = (int)$value;
            } else {
                $newvalue = (float)$value;
            }

            $current = $gi->{$field} ?? null;
            if ($kind === 'float') {
                if ($current === null || abs((float)$current - $newvalue) > 0.00001) {
                    $gi->{$field} = $newvalue;
                    $changed = true;
                }
            } else if ((int)$current !== $newvalue) {
                $gi->{$field} = $newvalue;
                $changed = true;
            }
        }

        $calculation = trim((string)($node['calculation'] ?? ''));
        if ($calculation !== '') {
            try {
                if (method_exists($gi, 'set_calculation')) {
                    $gi->set_calculation($calculation, null);
                } else {
                    $gi->calculation = $calculation;
                }
                $changed = true;
            } catch (\Throwable $e) {
                // Item-level formulas are uncommon; keep restore resilient.
            }
        }

        return $changed;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, mixed> $stats
     */
    private static function restore_snapshot_grade_item_from_node(
        int $courseid,
        int $liveparentcategoryid,
        array $node,
        array &$warnings,
        array &$stats
    ): void {
        if ($liveparentcategoryid < 1 || self::is_snapshot_non_restorable_grade_node($node)) {
            return;
        }

        $itemtype = strtolower(trim((string)($node['itemtype'] ?? '')));
        if ($itemtype === '') {
            return;
        }

        $created = false;
        $gi = self::find_snapshot_grade_item($courseid, $node);
        if (!$gi && $itemtype === 'manual') {
            $gi = self::create_manual_grade_item_from_snapshot($courseid, $liveparentcategoryid, $node, $warnings);
            $created = $gi !== null;
        }
        if (!$gi) {
            $label = trim((string)($node['name'] ?? ''));
            if ($label !== '') {
                $warnings[] = '⚠ Could not restore grade item "' . $label . '" from baseline snapshot.';
            }
            return;
        }

        if ((int)$gi->categoryid !== $liveparentcategoryid) {
            $gi->categoryid = $liveparentcategoryid;
        }

        if (self::apply_snapshot_grade_item_settings($gi, $node)) {
            $gi->update('block_ai_assistant');
        } else if ((int)$gi->categoryid === $liveparentcategoryid) {
            $gi->update('block_ai_assistant');
        }

        if ($created) {
            $stats['created_items'] = (int)($stats['created_items'] ?? 0) + 1;
        } else {
            $stats['restored_items'] = (int)($stats['restored_items'] ?? 0) + 1;
        }
    }

    /**
     * Recursively restore categories, subcategories, grade items, and category formulas.
     *
     * @param array<string, mixed> $stats
     */
    private static function restore_snapshot_tree_from_node(
        int $courseid,
        array $node,
        int $liveparentcategoryid,
        array $categorysettings,
        array &$resolvedcategoryids,
        int $coursecategoryid,
        array &$warnings,
        array &$stats
    ): void {
        foreach (self::iter_snapshot_tree_children($node) as $child) {
            if (self::is_snapshot_category_node($child)) {
                $snapshotcategoryid = (int)($child['id'] ?? 0);
                if ($snapshotcategoryid < 1) {
                    continue;
                }

                $livecategoryid = self::resolve_snapshot_category_target(
                    $courseid,
                    $snapshotcategoryid,
                    $categorysettings,
                    $resolvedcategoryids,
                    $coursecategoryid,
                    $warnings
                );
                if ($livecategoryid < 1) {
                    continue;
                }

                if (self::apply_snapshot_grade_category_settings($courseid, $livecategoryid, $child, $warnings)) {
                    $stats['categories_updated'] = (int)($stats['categories_updated'] ?? 0) + 1;
                }
                self::apply_snapshot_category_total_settings($courseid, $livecategoryid, $child, $warnings);

                self::restore_snapshot_tree_from_node(
                    $courseid,
                    $child,
                    $livecategoryid,
                    $categorysettings,
                    $resolvedcategoryids,
                    $coursecategoryid,
                    $warnings,
                    $stats
                );
                continue;
            }

            if (self::is_snapshot_non_restorable_grade_node($child)) {
                continue;
            }

            self::restore_snapshot_grade_item_from_node($courseid, $liveparentcategoryid, $child, $warnings, $stats);
        }
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

    private static function _get_ai_owned_grade_item_ids(int $courseid): array
    {
        $raw = get_config('block_ai_assistant', 'ai_gb_items_' . $courseid);
        if (!$raw) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? array_values(array_unique(array_map('intval', $decoded))) : [];
    }

    private static function _register_ai_owned_grade_item(int $courseid, int $gradeitemid): void
    {
        if ($courseid < 1 || $gradeitemid < 1) {
            return;
        }
        $existing = self::_get_ai_owned_grade_item_ids($courseid);
        $existing[] = $gradeitemid;
        set_config('ai_gb_items_' . $courseid, json_encode(array_values(array_unique($existing))), 'block_ai_assistant');
    }

    private static function _clear_ai_owned_grade_item_ids(int $courseid): void
    {
        unset_config('ai_gb_items_' . $courseid, 'block_ai_assistant');
    }

    /**
     * @param int $courseid
     * @return int number of grade items removed
     */
    private static function _remove_ai_owned_grade_items(int $courseid, array $preserve_grade_item_ids = []): int
    {
        global $DB;

        if ($courseid < 1) {
            return 0;
        }

        $preserve = [];
        foreach ($preserve_grade_item_ids as $preserveid) {
            $preserveid = (int)$preserveid;
            if ($preserveid > 0) {
                $preserve[$preserveid] = true;
            }
        }

        $removed = 0;
        $remainingids = [];
        foreach (self::_get_ai_owned_grade_item_ids($courseid) as $itemid) {
            $itemid = (int)$itemid;
            if ($itemid < 1) {
                continue;
            }
            if (!empty($preserve[$itemid])) {
                $remainingids[] = $itemid;
                continue;
            }
            $record = $DB->get_record('grade_items', ['id' => $itemid, 'courseid' => $courseid], 'id, itemtype', IGNORE_MISSING);
            if (!$record) {
                continue;
            }
            if ((string)($record->itemtype ?? '') !== 'manual') {
                continue;
            }
            self::_delete_grade_item_by_id($itemid);
            $removed++;
        }

        if (empty($remainingids)) {
            self::_clear_ai_owned_grade_item_ids($courseid);
        } else {
            set_config(
                'ai_gb_items_' . $courseid,
                json_encode(array_values(array_unique($remainingids))),
                'block_ai_assistant'
            );
        }
        return $removed;
    }

    private static function _delete_grade_item_by_id(int $itemid): void
    {
        global $DB;

        if ($itemid < 1) {
            return;
        }

        try {
            $gi = \grade_item::fetch(['id' => $itemid]);
            if ($gi) {
                $gi->delete('block_ai_assistant');
                return;
            }
        } catch (\Throwable $e) {
            debugging('grade_item delete failed for id ' . $itemid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        $DB->delete_records('grade_grades', ['itemid' => $itemid]);
        $DB->delete_records('grade_items', ['id' => $itemid]);
    }

    /**
     * Delete manual grade items that live inside categories about to be removed.
     *
     * @param int $courseid
     * @param int[] $categoryids
     * @return int
     */
    private static function _delete_manual_items_in_categories(int $courseid, array $categoryids, array $preserve_grade_item_ids = []): int
    {
        global $DB;

        if ($courseid < 1 || empty($categoryids)) {
            return 0;
        }

        $preserve = [];
        foreach ($preserve_grade_item_ids as $preserveid) {
            $preserveid = (int)$preserveid;
            if ($preserveid > 0) {
                $preserve[$preserveid] = true;
            }
        }

        list($insql, $inparams) = $DB->get_in_or_equal($categoryids);
        $items = $DB->get_records_select(
            'grade_items',
            "courseid = ? AND categoryid {$insql} AND itemtype = 'manual'",
            array_merge([$courseid], $inparams)
        );

        $removed = 0;
        foreach (($items ?: []) as $item) {
            $itemid = (int)$item->id;
            if (!empty($preserve[$itemid])) {
                continue;
            }
            self::_delete_grade_item_by_id($itemid);
            $removed++;
        }

        return $removed;
    }

    private static function course_needs_gradebook_cleanup(int $courseid): bool
    {
        if ($courseid < 1) {
            return false;
        }

        return self::course_has_ai_gradebook($courseid)
            || !empty(self::_get_ai_ownership_ids($courseid))
            || !empty(self::_get_ai_owned_grade_item_ids($courseid));
    }

    private static function gradebook_tree_missing_config_key(int $courseid): string
    {
        return 'ai_gb_tree_missing_' . $courseid;
    }

    private static function mark_gradebook_tree_missing(int $courseid): void
    {
        set_config(self::gradebook_tree_missing_config_key($courseid), (string)time(), 'block_ai_assistant');
    }

    private static function clear_gradebook_tree_missing(int $courseid): void
    {
        unset_config(self::gradebook_tree_missing_config_key($courseid), 'block_ai_assistant');
    }

    public static function gradebook_tree_missing_flag(int $courseid): bool
    {
        $raw = get_config('block_ai_assistant', self::gradebook_tree_missing_config_key($courseid));
        return trim((string)$raw) !== '';
    }

    public static function has_ai_gradebook_tree(int $courseid): bool
    {
        if ($courseid < 1) {
            return false;
        }
        return self::course_has_ai_gradebook($courseid);
    }

    /**
     * Handle Moodle gradebook deletion events and mark stale UI state when an AI tree disappears.
     */
    public static function handle_gradebook_structure_deleted_event(int $courseid, string $eventname, int $objectid = 0): void
    {
        if ($courseid < 1) {
            return;
        }

        try {
            $hasaitree = self::course_has_ai_gradebook($courseid);
            if ($hasaitree) {
                self::clear_gradebook_tree_missing($courseid);
                return;
            }

            self::_clear_ai_ownership_ids($courseid);
            self::mark_gradebook_tree_missing($courseid);
            error_log(
                'block_ai_assistant: detected missing AI gradebook tree after event=' . $eventname
                . ' courseid=' . (int)$courseid
                . ' objectid=' . (int)$objectid
            );
        } catch (\Throwable $e) {
            error_log(
                'block_ai_assistant: gradebook structure event handling failed event=' . $eventname
                . ' courseid=' . (int)$courseid
                . ' objectid=' . (int)$objectid
                . ' error=' . $e->getMessage()
            );
        }
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
    public static function remove_ai_gradebook_from_course(int $courseid, array $preserve_grade_item_ids = []): array
    {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->libdir . '/grade/grade_category.php');
        require_once($CFG->libdir . '/grade/grade_item.php');

        $result = [
            'cleaned' => false,
            'message' => 'No AI Assistant gradebook categories found to remove.',
        ];

        try {
            $manualremoved = self::_remove_ai_owned_grade_items($courseid, $preserve_grade_item_ids);

            $roots = self::get_ai_gradebook_root_ids($courseid);
            if (empty($roots)) {
                self::_clear_ai_ownership_ids($courseid);
                self::_purge_orphan_category_grade_items($courseid);
                if ($manualremoved > 0) {
                    $result = [
                        'cleaned' => true,
                        'message' => 'Removed ' . $manualremoved . ' AI-created manual grade item'
                            . ($manualremoved === 1 ? '' : 's') . '.',
                    ];
                }
                return $result;
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
                self::_purge_orphan_category_grade_items($courseid);
                $result['message'] = 'Could not resolve AI gradebook category tree IDs.';
                return $result;
            }

            debugging('Removing AI gradebook tree (' . implode(',', $tree_ids) . ') from course ' . $courseid, DEBUG_DEVELOPER);

            $manualremoved += self::_delete_manual_items_in_categories($courseid, $tree_ids, $preserve_grade_item_ids);

            foreach ($roots as $rootid) {
                $rootid = (int)$rootid;
                if ($rootid < 1 || !$DB->record_exists('grade_categories', ['id' => $rootid, 'courseid' => $courseid])) {
                    continue;
                }
                self::_delete_grade_category_tree($courseid, $rootid, $children_by_parent);
            }

            self::_purge_orphan_category_grade_items($courseid);

            $remaining_roots = self::count_ai_gradebook_roots($courseid);
            if ($remaining_roots > 0) {
                $result['message'] = $remaining_roots . ' AI Assistant gradebook ' . ($remaining_roots === 1 ? 'category' : 'categories') . ' could not be removed. Check Moodle grade setup manually.';
                return $result;
            }

            if (self::_repair_dangling_grade_items($courseid, $tree_ids) > 0) {
                $result['message'] = 'Categories removed but some grade items still referenced deleted AI categories. Please re-run delete session once.';
                return $result;
            }

            try {
                grade_regrade_final_grades($courseid);
            } catch (\Throwable $regradeex) {
                debugging('grade_regrade_final_grades failed after AI category delete for course ' . $courseid . ': ' . $regradeex->getMessage(), DEBUG_DEVELOPER);
            }

            self::_clear_ai_ownership_ids($courseid);

            $rootcount = count($roots);
            $parts = ['Removed ' . $rootcount . ' AI Assistant gradebook ' . ($rootcount === 1 ? 'category' : 'categories') . '.'];
            if ($manualremoved > 0) {
                $parts[] = 'Removed ' . $manualremoved . ' AI-created manual grade item' . ($manualremoved === 1 ? '' : 's') . '.';
            }
            $result = [
                'cleaned' => true,
                'message' => implode(' ', $parts),
            ];
            return $result;
        } catch (Throwable $e) {
            debugging('Error removing AI gradebook from course: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $result = [
                'cleaned' => false,
                'message' => 'Error during cleanup: ' . $e->getMessage(),
            ];
            return $result;
        } finally {
            if ($courseid > 0) {
                self::_purge_orphan_category_grade_items($courseid);
                $integrity = self::ensure_course_gradebook_integrity($courseid);
                if ($integrity['removed'] > 0) {
                    debugging(
                        'Removed ' . (int)$integrity['removed'] . ' orphaned gradebook structural row(s) after AI cleanup for course ' . $courseid,
                        DEBUG_DEVELOPER
                    );
                }
            }
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
            if ((string)($item->itemtype ?? '') === 'manual') {
                self::_delete_grade_item_by_id((int)$item->id);
                continue;
            }
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

        try {
            grade_regrade_final_grades($courseid);
        } catch (\Throwable $regradeex) {
            debugging('grade_regrade_final_grades (repair dangling) failed for course ' . $courseid . ': ' . $regradeex->getMessage(), DEBUG_DEVELOPER);
        }

        return (int)$DB->count_records_select(
            'grade_items',
            'courseid = ? AND categoryid ' . $inSql . ' AND itemtype <> ?',
            array_merge([$courseid], $inParams, ['category'])
        );
    }

    /**
     * Delete category-total grade_items whose iteminstance category row is gone.
     * Prevents grade/edit/tree "sortorder on null" fatals after AI tree removal.
     *
     * @param int $courseid
     * @return int
     */
    private static function _purge_orphan_category_grade_items(int $courseid): int
    {
        global $DB;

        if ($courseid < 1) {
            return 0;
        }

        $orphans = $DB->get_records_sql(
            'SELECT gi.id
               FROM {grade_items} gi
              WHERE gi.courseid = ?
                AND gi.itemtype = ?
                AND NOT EXISTS (
                    SELECT 1
                      FROM {grade_categories} gc
                     WHERE gc.id = gi.iteminstance
                       AND gc.courseid = gi.courseid
                )',
            [$courseid, 'category']
        );

        $removed = 0;
        foreach (($orphans ?: []) as $orphan) {
            $itemid = (int)$orphan->id;
            $DB->delete_records('grade_grades', ['itemid' => $itemid]);
            $DB->delete_records('grade_items', ['id' => $itemid]);
            $removed++;
        }

        return $removed;
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
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->libdir . '/grade/grade_item.php');
        require_once($CFG->libdir . '/grade/grade_category.php');
        $correlationid = self::build_gradebook_correlation_id('gradebook_finalize', $courseid, trim($session_id));

        if ($courseid > 0) {
            self::ensure_course_gradebook_integrity($courseid);
        }

        $applywarnings = [];
        $normalized_mapping = self::normalize_confirmed_mapping($courseid, $confirmed_mapping, $applywarnings);
        $mappingconstraints = self::extract_mapping_constraints($normalized_mapping);

        if ($courseid > 0 && !empty($confirmed_mapping) && empty($normalized_mapping)) {
            $applywarnings[] = '⚠ Finalize was blocked because no valid Moodle grade items could be resolved from the submitted mapping rows.';
            self::log_gradebook_audit_event(
                $courseid,
                trim($session_id),
                'gradebook_finalize_guard_failed',
                'blocked',
                [
                    'reason' => 'normalized_mapping_empty',
                    'submitted_rows' => count($confirmed_mapping),
                    'normalized_rows' => 0,
                    'warnings' => $applywarnings,
                ],
                0,
                $correlationid
            );

            return json_encode([
                'status' => 422,
                'code' => 'MAPPING_INVALID',
                'phase' => 'REFINEMENT',
                'message' => 'Finalize blocked: mapping rows could not be resolved to Moodle grade items. Regenerate mapping and try again.',
                'data' => [
                    'grade_setup_skipped' => true,
                    'grade_setup_apply_rolled_back' => false,
                    'grade_setup_skip_reason' => 'normalized_mapping_empty',
                    'grade_setup_warnings' => $applywarnings,
                    'gradebook_audit_correlation_id' => $correlationid,
                ],
            ]);
        }

        $method = 'cria_gradebook_finalize';
        $data = array(
            'session_id' => trim($session_id),
            'confirmed_mapping' => $confirmed_mapping,
            'create_categories' => true,
            'reorganize_resources' => false,
        );

        if ($courseid > 0) {
            self::sync_gradebook_session_context($courseid, trim($session_id), $confirmed_mapping);
        }

        $response_json = webservice::exec($method, $data);

        self::log_gradebook_audit_event(
            $courseid,
            trim($session_id),
            'gradebook_finalize_requested',
            'requested',
            [
                'mapping_rows' => count($normalized_mapping),
                'constraint_rows' => count($mappingconstraints),
            ],
            0,
            $correlationid
        );

        if ($courseid > 0) {
            try {
                $response = json_decode($response_json, true);
                $proposal = self::extract_finalize_proposal($response);
                if ((!is_array($proposal) || !is_array($proposal['categories'] ?? null)) && trim($session_id) !== '') {
                    $proposalresponse = json_decode(self::gradebook_proposal(trim($session_id)), true);
                    $fallbackproposal = self::extract_finalize_proposal($proposalresponse);
                    if (is_array($fallbackproposal) && is_array($fallbackproposal['categories'] ?? null)) {
                        $proposal = $fallbackproposal;
                        $applywarnings[] = 'ℹ Finalize response did not include proposal payload; loaded latest proposal before Moodle apply.';
                        if (is_array($response)) {
                            $response['proposal'] = $proposal;
                        }
                    }
                }

                if (is_array($response) && is_array($proposal) && is_array($proposal['categories'] ?? null)) {
                    if (trim($session_id) !== '') {
                        $freshresponse = json_decode(self::gradebook_proposal(trim($session_id)), true);
                        $freshproposal = self::extract_finalize_proposal($freshresponse);
                        if (is_array($freshproposal) && is_array($freshproposal['categories'] ?? null)) {
                            $proposal = $freshproposal;
                            $response['proposal'] = $proposal;
                        }
                    }

                    $phase = strtoupper(trim((string)($response['phase'] ?? '')));
                    $validation = is_array($response['content_mapping']['validation'] ?? null)
                        ? $response['content_mapping']['validation']
                        : [];
                    $canproceed = !isset($validation['can_proceed']) ? true : (bool)$validation['can_proceed'];

                    if ($phase !== 'COMPLETED' || !$canproceed) {
                        self::log_gradebook_audit_event(
                            $courseid,
                            trim($session_id),
                            'gradebook_finalize_guard_failed',
                            'blocked',
                            [
                                'phase' => $phase,
                                'can_proceed' => $canproceed,
                                'validation' => $validation,
                            ],
                            0,
                            $correlationid
                        );
                        $response['phase'] = 'REFINEMENT';
                        $response['data'] = array_merge(
                            is_array($response['data'] ?? null) ? $response['data'] : [],
                            [
                                'grade_setup_skipped' => true,
                                'grade_setup_apply_rolled_back' => false,
                                'grade_setup_skip_reason' => 'defensive_guard_validation_failed',
                            ]
                        );
                        if (!empty($mappingconstraints)) {
                            $response['data']['grade_setup_constraints'] = $mappingconstraints;
                        }
                        $response['data']['gradebook_audit_correlation_id'] = $correlationid;
                        if (!empty($applywarnings)) {
                            $response['data']['grade_setup_warnings'] = $applywarnings;
                        }
                        if (empty($response['message'])) {
                            $response['message'] = 'Finalize blocked by validation; no Moodle gradebook changes were applied.';
                        }
                        return json_encode($response);
                    }

                    $transaction = $DB->start_delegated_transaction();
                    try {
                        if (!self::ensure_preapply_baseline_snapshot($courseid, trim($session_id), $applywarnings, $correlationid)) {
                            throw new \moodle_exception('Missing immutable baseline snapshot; finalize apply aborted.');
                        }

                        // Treat repeated finalize as override, not append: clear prior AI tree first.
                        $preserveids = self::extract_preserve_grade_item_ids($normalized_mapping, $proposal, $courseid);
                        $precleanup = self::remove_ai_gradebook_from_course($courseid, $preserveids);
                        if (!empty($precleanup['message']) && empty($precleanup['cleaned'])) {
                            $applywarnings[] = (string)$precleanup['message'];
                        }

                        $localwarnings = self::apply_gradebook_to_course($courseid, $proposal, $normalized_mapping);
                        if (!empty($localwarnings)) {
                            $applywarnings = array_merge($applywarnings, $localwarnings);
                        }

                        $applywarnings = array_merge(
                            $applywarnings,
                            self::gradebook_integrity_warnings($courseid, 'finalize apply')
                        );

                        $revisionmeta = self::capture_gradebook_snapshot(
                            $courseid,
                            trim($session_id),
                            'apply_revision',
                            [
                                'phase' => strtoupper(trim((string)($response['phase'] ?? ''))),
                                'mapping_rows' => count($normalized_mapping),
                            ]
                            ,
                            $correlationid
                        );
                        if ($revisionmeta === null) {
                            $applywarnings[] = '⚠ Could not store apply revision snapshot in gradebook ledger.';
                        } else {
                            $applywarnings[] = 'ℹ Stored gradebook revision snapshot #' . (int)$revisionmeta['revision'] . '.';
                        }

                        $transaction->allow_commit();
                    } catch (Throwable $applyexception) {
                        try {
                            $transaction->rollback($applyexception);
                        } catch (Throwable $rollbackexception) {
                            debugging('Error rolling back gradebook finalize transaction: ' . $rollbackexception->getMessage(), DEBUG_DEVELOPER);
                        }

                        // Compensating cleanup: force-purge AI-owned tree then repair common integrity pitfalls.
                        $compensating = self::_force_purge_ai_gradebook_tree($courseid);
                        if (!empty($compensating['message'])) {
                            $applywarnings[] = (string)$compensating['message'];
                        }

                        $applywarnings = array_merge(
                            $applywarnings,
                            self::gradebook_integrity_warnings($courseid, 'finalize rollback recovery')
                        );

                        debugging('Error applying gradebook to course (rolled back): ' . $applyexception->getMessage(), DEBUG_DEVELOPER);

                        $response['phase'] = 'REFINEMENT';
                        $response['message'] = 'Finalize blocked: Moodle gradebook apply was rolled back after an internal error.';
                        $response['data'] = array_merge(
                            is_array($response['data'] ?? null) ? $response['data'] : [],
                            [
                                'grade_setup_skipped' => true,
                                'grade_setup_apply_rolled_back' => true,
                                'grade_setup_skip_reason' => 'apply_exception_rolled_back',
                            ]
                        );
                        if (!empty($mappingconstraints)) {
                            $response['data']['grade_setup_constraints'] = $mappingconstraints;
                        }
                        $response['data']['gradebook_audit_correlation_id'] = $correlationid;
                        if (!empty($applywarnings)) {
                            $response['data']['grade_setup_warnings'] = $applywarnings;
                        }
                        self::log_gradebook_audit_event(
                            $courseid,
                            trim($session_id),
                            'gradebook_finalize_apply_rolled_back',
                            'failed',
                            [
                                'message' => $applyexception->getMessage(),
                                'warnings' => $applywarnings,
                            ],
                            0,
                            $correlationid
                        );
                        return json_encode($response);
                    }

                    if (!empty($applywarnings)) {
                        $warningtext = implode(' ', $applywarnings);
                        $response['message'] = trim((string)($response['message'] ?? 'Gradebook finalized.')) . ' ' . $warningtext;
                        $response['data'] = array_merge(
                            is_array($response['data'] ?? null) ? $response['data'] : [],
                            ['grade_setup_warnings' => $applywarnings]
                        );
                        if (!empty($mappingconstraints)) {
                            $response['data']['grade_setup_constraints'] = $mappingconstraints;
                        }
                        $response['data']['gradebook_audit_correlation_id'] = $correlationid;
                        $response_json = json_encode($response);
                    } else if (!empty($mappingconstraints)) {
                        $response['data'] = array_merge(
                            is_array($response['data'] ?? null) ? $response['data'] : [],
                            ['grade_setup_constraints' => $mappingconstraints]
                        );
                        $response['data']['gradebook_audit_correlation_id'] = $correlationid;
                        $response_json = json_encode($response);
                    } else {
                        $response = is_array($response) ? $response : [];
                        $response['data'] = array_merge(
                            is_array($response['data'] ?? null) ? $response['data'] : [],
                            ['gradebook_audit_correlation_id' => $correlationid]
                        );
                        $response_json = json_encode($response);
                    }

                    self::log_gradebook_audit_event(
                        $courseid,
                        trim($session_id),
                        'gradebook_finalize_completed',
                        'success',
                        [
                            'warnings' => $applywarnings,
                            'constraints' => $mappingconstraints,
                        ],
                        0,
                        $correlationid
                    );
                } else if (is_array($response)) {
                    $response['data'] = array_merge(
                        is_array($response['data'] ?? null) ? $response['data'] : [],
                        [
                            'grade_setup_skipped' => true,
                            'grade_setup_skip_reason' => 'missing_proposal_payload',
                            'gradebook_audit_correlation_id' => $correlationid,
                        ]
                    );
                    $response['message'] = trim((string)($response['message'] ?? 'Gradebook finalized.'))
                        . ' Moodle grade setup apply skipped because no proposal payload was available.';
                    $response_json = json_encode($response);
                    self::log_gradebook_audit_event(
                        $courseid,
                        trim($session_id),
                        'gradebook_finalize_apply_skipped',
                        'blocked',
                        ['reason' => 'missing_proposal_payload'],
                        0,
                        $correlationid
                    );
                }
            } catch (Throwable $e) {
                debugging('Error applying gradebook to course: ' . $e->getMessage(), DEBUG_DEVELOPER);
                self::log_gradebook_audit_event(
                    $courseid,
                    trim($session_id),
                    'gradebook_finalize_exception',
                    'failed',
                    ['message' => $e->getMessage()],
                    0,
                    $correlationid
                );
                return json_encode([
                    'status' => 500,
                    'code' => 'MOODLE_APPLY_FAILED',
                    'phase' => 'REFINEMENT',
                    'message' => 'Finalize blocked: Moodle gradebook apply failed before commit.',
                    'data' => [
                        'grade_setup_skipped' => true,
                        'grade_setup_apply_rolled_back' => true,
                        'grade_setup_skip_reason' => 'unhandled_apply_exception',
                        'grade_setup_warnings' => ['⚠ A server-side issue occurred while applying gradebook changes. Check logs with the reference ID.'],
                        'gradebook_audit_correlation_id' => $correlationid,
                    ],
                ]);
            }
        }

        return $response_json;
    }

    public static function gradebook_create_manual_item(int $courseid, string $itemname): array
    {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->libdir . '/grade/grade_item.php');
        require_once($CFG->libdir . '/grade/grade_category.php');

        $cleanname = trim($itemname);
        if ($courseid <= 0 || $cleanname === '') {
            return [
                'success' => false,
                'message' => 'Manual grade item name is required.',
                'grade_item_id' => 0,
                'activity_name' => '',
                'moodle_cmid' => 0,
                'itemtype' => 'manual',
                'module' => '',
            ];
        }

        try {
            $coursecategory = \grade_category::fetch_course_category($courseid);
            if (!$coursecategory) {
                return [
                    'success' => false,
                    'message' => 'Could not resolve the course gradebook category.',
                    'grade_item_id' => 0,
                    'activity_name' => '',
                    'moodle_cmid' => 0,
                    'itemtype' => 'manual',
                    'module' => '',
                ];
            }

            $gradeitem = new \grade_item();
            $gradeitem->courseid = $courseid;
            $gradeitem->categoryid = (int)$coursecategory->id;
            $gradeitem->itemtype = 'manual';
            $gradeitem->itemname = $cleanname;
            $gradeitem->grademin = 0;
            $gradeitem->grademax = 100;
            if (defined('GRADE_TYPE_VALUE')) {
                $gradeitem->gradetype = GRADE_TYPE_VALUE;
            }
            $gradeitem->insert();

            if (empty($gradeitem->id)) {
                throw new \moodle_exception('Manual grade item insert did not return an id.');
            }

            self::_register_ai_owned_grade_item($courseid, (int)$gradeitem->id);

            return [
                'success' => true,
                'message' => 'Manual grade item created.',
                'grade_item_id' => (int)$gradeitem->id,
                'activity_name' => $cleanname,
                'moodle_cmid' => 0,
                'itemtype' => 'manual',
                'module' => '',
            ];
        } catch (\Throwable $e) {
            debugging('Could not create manual grade item: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [
                'success' => false,
                'message' => 'Could not create manual grade item.',
                'grade_item_id' => 0,
                'activity_name' => '',
                'moodle_cmid' => 0,
                'itemtype' => 'manual',
                'module' => '',
            ];
        }
    }

    /**
     * Best-effort repair for null/invalid sortorder values in gradebook tables.
     *
     * @param int $courseid
     * @return int number of repaired rows
     */
    private static function normalize_null_sortorders(int $courseid): int
    {
        global $DB;

        if ($courseid < 1) {
            return 0;
        }

        $fixed = 0;

        $categorycolumns = $DB->get_columns('grade_categories');
        $categoryhassortorder = is_array($categorycolumns) && array_key_exists('sortorder', $categorycolumns);

        // Moodle schemas can differ across versions; skip category sortorder repair if column is absent.
        if ($categoryhassortorder) {
            $nextcategorysortorder = (int)$DB->get_field_sql(
                'SELECT COALESCE(MAX(sortorder), 0) FROM {grade_categories} WHERE courseid = ?',
                [$courseid]
            ) + 1;

            $categoryrows = $DB->get_records_select(
                'grade_categories',
                'courseid = ? AND (sortorder IS NULL OR sortorder < 1)',
                [$courseid],
                'id ASC',
                'id, sortorder'
            );
            foreach ($categoryrows as $row) {
                $update = new \stdClass();
                $update->id = (int)$row->id;
                $update->sortorder = $nextcategorysortorder++;
                $DB->update_record('grade_categories', $update);
                $fixed++;
            }
        }

        $nextitemsortorder = (int)$DB->get_field_sql(
            'SELECT COALESCE(MAX(sortorder), 0) FROM {grade_items} WHERE courseid = ?',
            [$courseid]
        ) + 1;

        $itemrows = $DB->get_records_select(
            'grade_items',
            'courseid = ? AND (sortorder IS NULL OR sortorder < 1)',
            [$courseid],
            'id ASC',
            'id, sortorder'
        );
        foreach ($itemrows as $row) {
            $update = new \stdClass();
            $update->id = (int)$row->id;
            $update->sortorder = $nextitemsortorder++;
            $DB->update_record('grade_items', $update);
            $fixed++;
        }

        return $fixed;
    }

    /**
     * Ensure immutable pre-first-apply baseline snapshot exists for this course/session.
     *
     * @param int $courseid
     * @param string $sessionid
     * @param array $warnings output accumulator
     * @return bool
     */
    private static function ensure_preapply_baseline_snapshot(int $courseid, string $sessionid, array &$warnings = [], string $correlationid = ''): bool
    {
        global $DB;

        if ($courseid < 1 || $sessionid === '') {
            $warnings[] = '⚠ Baseline snapshot prerequisite failed: missing course/session reference.';
            return false;
        }

        if (!self::gradebook_snapshot_ledger_available()) {
            $warnings[] = '⚠ Baseline snapshot prerequisite failed: snapshot ledger table is missing (run plugin upgrade).';
            self::log_gradebook_audit_event($courseid, $sessionid, 'gradebook_baseline_snapshot', 'blocked', ['reason' => 'snapshot_ledger_missing'], 0, $correlationid);
            return false;
        }

        $exists = $DB->record_exists(
            'block_aia_gradebook_snapshots',
            ['courseid' => $courseid, 'session_id' => $sessionid, 'snapshot_type' => 'baseline']
        );
        if ($exists) {
            self::log_gradebook_audit_event($courseid, $sessionid, 'gradebook_baseline_snapshot', 'exists', ['reason' => 'already_present'], 0, $correlationid);
            return true;
        }

        $captured = self::capture_gradebook_snapshot($courseid, $sessionid, 'baseline', ['immutable' => true], $correlationid);
        if ($captured === null) {
            $warnings[] = '⚠ Failed to capture immutable baseline snapshot before apply.';
            self::log_gradebook_audit_event($courseid, $sessionid, 'gradebook_baseline_snapshot', 'failed', ['reason' => 'capture_failed'], 0, $correlationid);
            return false;
        }

        $warnings[] = 'ℹ Captured immutable baseline snapshot #' . (int)$captured['revision'] . ' before apply.';
        self::log_gradebook_audit_event($courseid, $sessionid, 'gradebook_baseline_snapshot', 'success', ['revision' => (int)$captured['revision']], (int)$captured['revision'], $correlationid);
        return true;
    }

    /**
     * Store a gradebook snapshot entry in the ledger table.
     *
     * @param int $courseid
     * @param string $sessionid
     * @param string $snapshottype
     * @param array $extra
     * @return array|null
     */
    private static function capture_gradebook_snapshot(int $courseid, string $sessionid, string $snapshottype, array $extra = [], string $correlationid = ''): ?array
    {
        global $DB;

        if ($courseid < 1 || trim($sessionid) === '' || !self::gradebook_snapshot_ledger_available()) {
            return null;
        }

        try {
            $nextrevision = (int)$DB->get_field_sql(
                'SELECT COALESCE(MAX(revision), -1) + 1 FROM {block_aia_gradebook_snapshots} WHERE courseid = ? AND session_id = ?',
                [$courseid, $sessionid]
            );

            $snapshot = self::build_gradebook_baseline_snapshot($courseid);
            $payload = [
                'contract_name' => 'gradebook_snapshot_ledger_v1',
                'schema_version' => 1,
                'courseid' => (string)$courseid,
                'session_id' => $sessionid,
                'revision' => $nextrevision,
                'snapshot_type' => $snapshottype,
                'captured_at' => time(),
                'gradebook_snapshot' => $snapshot,
                'extra' => $extra,
            ];

            $payloadjson = json_encode($payload);
            if (!is_string($payloadjson) || $payloadjson === '') {
                return null;
            }

            $payloadcompressed = null;
            if (function_exists('gzencode')) {
                $compressed = @gzencode($payloadjson, 6);
                if ($compressed !== false) {
                    $payloadcompressed = base64_encode($compressed);
                }
            }

            $record = new \stdClass();
            $record->courseid = $courseid;
            $record->session_id = $sessionid;
            $record->revision = $nextrevision;
            $record->snapshot_type = substr(trim($snapshottype), 0, 32);
            $record->schema_version = 1;
            $record->checksum = hash('sha256', $payloadjson);
            $record->payload_json = $payloadjson;
            $record->payload_compressed = $payloadcompressed;
            $record->timecreated = time();
            $record->timemodified = time();

            $DB->insert_record('block_aia_gradebook_snapshots', $record);

            self::log_gradebook_audit_event(
                $courseid,
                $sessionid,
                'gradebook_snapshot_captured',
                'success',
                [
                    'snapshot_type' => $record->snapshot_type,
                    'revision' => $nextrevision,
                    'checksum' => $record->checksum,
                    'extra' => $extra,
                ],
                $nextrevision,
                $correlationid
            );

            return [
                'revision' => $nextrevision,
                'checksum' => $record->checksum,
                'snapshot_type' => $record->snapshot_type,
            ];
        } catch (\Throwable $e) {
            debugging('Snapshot capture failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }

    /**
     * @return bool
     */
    private static function gradebook_snapshot_ledger_available(): bool
    {
        global $DB;

        try {
            $dbman = $DB->get_manager();
            $table = new \xmldb_table('block_aia_gradebook_snapshots');
            return $dbman->table_exists($table);
        } catch (\Throwable $e) {
            debugging('Snapshot ledger availability check failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * @return bool
     */
    private static function gradebook_audit_ledger_available(): bool
    {
        global $DB;

        try {
            $dbman = $DB->get_manager();
            $table = new \xmldb_table('block_aia_gradebook_audit');
            return $dbman->table_exists($table);
        } catch (\Throwable $e) {
            debugging('Gradebook audit ledger availability check failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    private static function build_gradebook_correlation_id(string $seed, int $courseid, string $sessionid = ''): string
    {
        $material = implode('|', [
            trim($seed),
            (string)$courseid,
            trim($sessionid),
            (string)microtime(true),
            (string)random_int(0, PHP_INT_MAX),
        ]);

        return substr(hash('sha256', $material), 0, 32);
    }

    private static function log_gradebook_audit_event(
        int $courseid,
        string $sessionid,
        string $eventname,
        string $outcome,
        array $details = [],
        int $revision = 0,
        string $correlationid = ''
    ): ?string {
        global $DB;

        if ($courseid < 1 || !self::gradebook_audit_ledger_available()) {
            return null;
        }

        try {
            $correlationid = trim($correlationid) !== ''
                ? trim($correlationid)
                : self::build_gradebook_correlation_id($eventname, $courseid, $sessionid);

            $payload = [
                'event' => trim($eventname),
                'outcome' => trim($outcome),
                'courseid' => $courseid,
                'session_id' => trim($sessionid),
                'revision' => $revision,
                'details' => $details,
            ];
            $payloadjson = json_encode($payload);
            if (!is_string($payloadjson) || $payloadjson === '') {
                $payloadjson = null;
            }

            $record = new \stdClass();
            $record->courseid = $courseid;
            $record->session_id = trim($sessionid) !== '' ? trim($sessionid) : null;
            $record->revision = $revision;
            $record->correlation_id = $correlationid;
            $record->event_name = substr(trim($eventname), 0, 64);
            $record->outcome = substr(trim($outcome), 0, 32);
            $record->details_json = $payloadjson;
            $record->timecreated = time();
            $record->timemodified = time();

            $DB->insert_record('block_aia_gradebook_audit', $record);
            return $correlationid;
        } catch (\Throwable $e) {
            debugging('Gradebook audit event insert failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }

    /**
     * Extract a gradebook proposal payload from known response shapes.
     *
     * @param mixed $response
     * @return array|null
     */
    private static function extract_finalize_proposal($response): ?array
    {
        if (!is_array($response)) {
            return null;
        }

        $candidates = [
            $response['proposal'] ?? null,
            $response['data']['proposal'] ?? null,
            $response['result']['proposal'] ?? null,
            $response['data']['result']['proposal'] ?? null,
            $response['payload']['proposal'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Normalize confirmed mapping rows to a grade-item-first shape while
     * preserving compatibility with legacy activity-only payloads.
     *
     * Supported input row fields:
     * - Legacy: moodle_cmid, category
     * - New: grade_item_id, grade_item_type, itemmodule, iteminstance, moodle_cmid, category
     *
     * @param int $courseid
     * @param array $confirmed_mapping
     * @param array $warnings (output accumulator)
     * @return array normalized rows
     */
    private static function normalize_confirmed_mapping(int $courseid, array $confirmed_mapping, array &$warnings = []): array
    {
        global $DB;

        $normalized = [];
        $modinfo = null;

        foreach ($confirmed_mapping as $row) {
            if (!is_array($row)) {
                continue;
            }

            $category = trim((string)($row['category'] ?? ''));
            if ($category === '' || strtolower($category) === '__not_graded__') {
                continue;
            }

            $cmid = (int)($row['moodle_cmid'] ?? 0);
            $gradeitemid = (int)($row['grade_item_id'] ?? 0);
            $activityname = trim((string)($row['activity_name'] ?? $row['grade_item_name'] ?? ''));
            $subcategory = trim((string)($row['subcategory'] ?? ''));

            $entry = [
                'category' => $category,
                'subcategory' => $subcategory,
                'moodle_cmid' => $cmid,
                'activity_name' => $activityname,
                'grade_item_id' => 0,
                'grade_item_type' => '',
                'itemmodule' => '',
                'iteminstance' => 0,
                'is_constrained' => false,
                'constraint_reason' => '',
            ];

            $gi = null;
            if ($gradeitemid > 0) {
                $candidate = \grade_item::fetch(['id' => $gradeitemid]);
                if ($candidate && (int)$candidate->courseid === $courseid) {
                    $gi = $candidate;
                }
            }

            // Legacy fallback: resolve by cmid for mod-type grade items.
            if (!$gi && $cmid > 0) {
                try {
                    if ($modinfo === null) {
                        $modinfo = get_fast_modinfo($courseid);
                    }
                    $cm = $modinfo->get_cm($cmid);
                    if ($cm) {
                        $found = \grade_item::fetch_all([
                            'courseid' => $courseid,
                            'iteminstance' => $cm->instance,
                            'itemmodule' => $cm->modname,
                            'itemtype' => 'mod',
                        ]);
                        if ($found) {
                            $gi = reset($found);
                        }
                    }
                } catch (Throwable $e) {
                    debugging('Could not resolve mapping CMID ' . $cmid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
                }
            }

            if (!$gi && !empty($row['itemmodule']) && !empty($row['iteminstance'])) {
                $module = trim((string)$row['itemmodule']);
                $instance = (int)$row['iteminstance'];
                if ($module !== '' && $instance > 0) {
                    $found = \grade_item::fetch_all([
                        'courseid' => $courseid,
                        'iteminstance' => $instance,
                        'itemmodule' => $module,
                        'itemtype' => 'mod',
                    ]);
                    if ($found) {
                        $gi = reset($found);
                    }
                }
            }

            if (!$gi && $activityname !== '') {
                $manualrecords = $DB->get_records_select(
                    'grade_items',
                    "courseid = :courseid AND itemtype = 'manual'",
                    ['courseid' => $courseid],
                    'id DESC',
                    'id, courseid, itemtype, itemname'
                );
                foreach (($manualrecords ?: []) as $manualrecord) {
                    if (strcasecmp(trim((string)($manualrecord->itemname ?? '')), $activityname) === 0) {
                        $gi = \grade_item::fetch(['id' => (int)$manualrecord->id]);
                        if ($gi) {
                            break;
                        }
                    }
                }
            }

            if ($gi) {
                $entry['grade_item_id'] = (int)$gi->id;
                $entry['grade_item_type'] = (string)($gi->itemtype ?? '');
                $entry['itemmodule'] = (string)($gi->itemmodule ?? '');
                $entry['iteminstance'] = (int)($gi->iteminstance ?? 0);

                $constraint = self::gradebook_item_mutation_constraint($gi);
                if ($constraint !== null) {
                    $entry['is_constrained'] = true;
                    $entry['constraint_reason'] = $constraint;
                }

                if ($entry['activity_name'] === '') {
                    $entry['activity_name'] = trim((string)($gi->itemname ?? ''));
                }

                if ((int)$entry['moodle_cmid'] < 1 && $entry['grade_item_type'] === 'mod' && $entry['itemmodule'] !== '' && $entry['iteminstance'] > 0) {
                    $cmidrec = $DB->get_record(
                        'course_modules',
                        ['course' => $courseid, 'module' => self::resolve_module_id($entry['itemmodule']), 'instance' => $entry['iteminstance']],
                        'id',
                        IGNORE_MISSING
                    );
                    if ($cmidrec) {
                        $entry['moodle_cmid'] = (int)$cmidrec->id;
                    }
                }

                $normalized[] = $entry;
                continue;
            }

            if ($cmid > 0) {
                $normalized[] = $entry;
                continue;
            }

            $warnings[] = '⚠ Skipped one mapping row because no valid Moodle CMID or grade item could be resolved for category ' . $category . '.';
        }

        return $normalized;
    }

    /**
     * Build compact constraint list for finalize response payload.
     *
     * @param array $normalized_mapping
     * @return array
     */
    private static function extract_mapping_constraints(array $normalized_mapping): array
    {
        $constraints = [];
        foreach ($normalized_mapping as $row) {
            if (!is_array($row) || empty($row['is_constrained'])) {
                continue;
            }

            $constraints[] = [
                'grade_item_id' => (int)($row['grade_item_id'] ?? 0),
                'activity_name' => trim((string)($row['activity_name'] ?? '')),
                'itemmodule' => trim((string)($row['itemmodule'] ?? '')),
                'reason' => trim((string)($row['constraint_reason'] ?? 'constrained item')),
            ];
        }

        return $constraints;
    }

    /**
     * Collapse a subcategory label for tolerant matching (e.g. "In-Lab" vs "In-Lab Work").
     */
    private static function normalize_subcategory_label(string $label): string
    {
        $clean = strtolower(trim($label));
        if ($clean === '') {
            return '';
        }
        return (string)preg_replace('/[^a-z0-9]+/', '', $clean);
    }

    /**
     * Resolve a subcategory id under a parent using exact then prefix-normalized matching.
     *
     * @param string $subcategory_name
     * @param array<string,int> $subcategory_map lowercase subcategory name => category id
     * @return int|null
     */
    private static function resolve_subcategory_id_for_parent(string $subcategory_name, array $subcategory_map): ?int
    {
        $subcategory_name = trim($subcategory_name);
        if ($subcategory_name === '' || empty($subcategory_map)) {
            return null;
        }

        $sub_key = strtolower($subcategory_name);
        if (isset($subcategory_map[$sub_key])) {
            return (int)$subcategory_map[$sub_key];
        }

        $normalized_request = self::normalize_subcategory_label($subcategory_name);
        if ($normalized_request === '') {
            return null;
        }

        $best_id = null;
        $best_len = -1;
        foreach ($subcategory_map as $name_key => $sub_id) {
            $normalized_candidate = self::normalize_subcategory_label((string)$name_key);
            if ($normalized_candidate === '') {
                continue;
            }
            if ($normalized_request === $normalized_candidate) {
                return (int)$sub_id;
            }
            if (
                str_starts_with($normalized_candidate, $normalized_request)
                || str_starts_with($normalized_request, $normalized_candidate)
            ) {
                $matchlen = min(strlen($normalized_request), strlen($normalized_candidate));
                if ($matchlen > $best_len) {
                    $best_len = $matchlen;
                    $best_id = (int)$sub_id;
                }
            }
        }

        return $best_id;
    }

    /**
     * Resolve the Moodle grade category id for a mapping row (parent or nested subcategory).
     */
    private static function resolve_mapping_target_category_id(
        array $mapping,
        array $category_id_map,
        array $subcategory_by_parent,
        array $subcategory_global_map,
        array &$warnings = [],
        string $item_label = ''
    ): ?int {
        $category_name = trim((string)($mapping['category'] ?? ''));
        $subcategory_name = trim((string)($mapping['subcategory'] ?? ''));
        $category_key = strtolower($category_name);

        if ($category_key === '') {
            return null;
        }

        if (!isset($category_id_map[$category_key])) {
            if (isset($subcategory_global_map[$category_key])) {
                return (int)$subcategory_global_map[$category_key];
            }
            return null;
        }

        $target_category_id = (int)$category_id_map[$category_key];
        if ($subcategory_name === '') {
            return $target_category_id;
        }

        $sub_map = is_array($subcategory_by_parent[$category_key] ?? null) ? $subcategory_by_parent[$category_key] : [];
        $resolved_sub_id = self::resolve_subcategory_id_for_parent($subcategory_name, $sub_map);
        if ($resolved_sub_id !== null) {
            // #region agent log
            @file_put_contents(
                '/Users/kiarash/Desktop/project/Prog/Cria/.cursor/debug-bc8bf1.log',
                json_encode([
                    'sessionId' => 'bc8bf1',
                    'hypothesisId' => 'H1',
                    'location' => 'cria.php:resolve_mapping_target_category_id',
                    'message' => 'subcategory_resolved',
                    'data' => [
                        'category' => $category_name,
                        'requested_subcategory' => $subcategory_name,
                        'resolved_subcategory_id' => $resolved_sub_id,
                        'item_label' => $item_label,
                    ],
                    'timestamp' => (int)round(microtime(true) * 1000),
                ]) . "\n",
                FILE_APPEND
            );
            // #endregion
            return $resolved_sub_id;
        }

        $warnings[] = '⚠ Subcategory "' . $subcategory_name . '" was not found under category "' . $category_name
            . ($item_label !== '' ? '" for item "' . $item_label : '')
            . '". Kept under parent category instead.';

        // #region agent log
        @file_put_contents(
            '/Users/kiarash/Desktop/project/Prog/Cria/.cursor/debug-bc8bf1.log',
            json_encode([
                'sessionId' => 'bc8bf1',
                'hypothesisId' => 'H1',
                'location' => 'cria.php:resolve_mapping_target_category_id',
                'message' => 'subcategory_unresolved_fallback_parent',
                'data' => [
                    'category' => $category_name,
                    'requested_subcategory' => $subcategory_name,
                    'available_subcategories' => array_keys($sub_map),
                    'item_label' => $item_label,
                ],
                'timestamp' => (int)round(microtime(true) * 1000),
            ]) . "\n",
            FILE_APPEND
        );
        // #endregion

        return $target_category_id;
    }

    /**
     * Apply the finalized gradebook proposal to the Moodle course gradebook.
     * Creates grade categories with the chosen aggregation method, sets weights,
     * drop/keep rules, and moves grade items for mapped activities into their categories.
     */
    private static function apply_gradebook_to_course(int $courseid, array $proposal, array $confirmed_mapping): array
    {
        global $CFG, $DB;
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

        // Get course name for root category naming
        $course = $DB->get_record('course', ['id' => $courseid], 'shortname, fullname', MUST_EXIST);
        $course_name = trim((string)($course->shortname ?? '')) !== '' ? trim($course->shortname) : trim($course->fullname);
        $root_category_name = $course_name . ' - AI Assistant - Gradebook';

        // Get or create top-level parent category for this AI-generated structure
        $parent = self::get_or_create_grade_category(
            $courseid,
            $root_category_name,
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
        self::clear_gradebook_tree_missing($courseid);

        // Legacy runs could have created proposal categories directly under course total
        // (without the AI root wrapper). Rehome empty legacy shells so we reuse them
        // instead of creating duplicate Assignment/Quizzes/Midterm/Final trees.
        self::rehome_legacy_top_level_categories($courseid, $parent, $proposal['categories'], $warnings);

        // Create / update each top-level category and optional nested subcategories.
        $category_id_map = [];
        $subcategory_by_parent = [];
        $subcategory_global_map = [];
        $category_formula_map = [];
        $formula_reference_alias_map = [];
        $category_visibility_by_id = [];
        $root_visibility = self::normalize_visibility_settings([
            'hidden' => false,
            'hidden_until' => null,
        ]);

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
            $settings = self::merge_visibility_with_parent($settings, $root_visibility);

            $cat_obj = self::get_or_create_grade_category(
                $courseid,
                $name,
                $aggregation_method,
                $parent,
                $weight_fraction,
                $settings
            );
            $category_id_map[$category_key] = $cat_obj->id;
            $category_visibility_by_id[(int)$cat_obj->id] = self::normalize_visibility_settings($settings);
            self::register_formula_aliases($formula_reference_alias_map, $name, (int)$cat_obj->id);

            $formula_text = trim((string)($cat['calculation_formula'] ?? ''));
            if ($formula_text !== '') {
                $category_formula_map[$category_key] = [
                    'category_id' => (int)$cat_obj->id,
                    'formula' => $formula_text,
                    'explicit_override' => (bool)($cat['formula_override'] ?? false),
                    'formula_item_refs' => is_array($cat['formula_item_refs'] ?? null) ? $cat['formula_item_refs'] : [],
                ];
            }

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
                    'hidden' => (bool)($sub['hidden'] ?? false),
                    'hidden_until' => isset($sub['hidden_until']) && $sub['hidden_until'] !== null ? (int)$sub['hidden_until'] : null,
                    'locked' => false,
                    'lock_time' => null,
                    'display_type' => 0,
                    'decimals' => -1,
                ];
                $sub_settings = self::merge_visibility_with_parent(
                    $sub_settings,
                    $category_visibility_by_id[(int)$cat_obj->id] ?? $root_visibility
                );

                $sub_obj = self::get_or_create_grade_category(
                    $courseid,
                    $sub_name,
                    (int)($sub['aggregation_method'] ?? $aggregation_method),
                    $cat_obj,
                    $sub_weight_fraction,
                    $sub_settings
                );

                $sub_key = strtolower($sub_name);
                $subcategory_by_parent[$category_key][$sub_key] = $sub_obj->id;
                $subcategory_global_map[$sub_key] = $sub_obj->id;
                $category_visibility_by_id[(int)$sub_obj->id] = self::normalize_visibility_settings($sub_settings);
                self::register_formula_aliases($formula_reference_alias_map, $sub_name, (int)$sub_obj->id);
            }

        }

        // Final authoritative pass: enforce top-level category weights from proposal.
        // This guarantees requested percentages are applied even when categories are
        // reused/reparented from legacy structures.
        $applied_top_level_weights = [];
        $top_level_weight_expectations = [];
        foreach ($proposal['categories'] as $cat) {
            $name = trim((string)($cat['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $category_key = strtolower($name);
            if (empty($category_id_map[$category_key])) {
                continue;
            }

            $category_id = (int)$category_id_map[$category_key];
            $gi_record = $DB->get_record(
                'grade_items',
                ['courseid' => $courseid, 'itemtype' => 'category', 'iteminstance' => $category_id],
                'id, categoryid, aggregationcoef, aggregationcoef2, weightoverride',
                IGNORE_MISSING
            );
            if (!$gi_record) {
                continue;
            }

            $weight = max(0.0, min(1.0, floatval($cat['weight'] ?? 0) / 100.0));
            $extra_credit = (bool)($cat['extra_credit'] ?? false);
            $parent_aggregation = (int)($parent->aggregation ?? $aggregation_method);
            $expected_parent_categoryid = (int)$parent->id;

            $update = new \stdClass();
            $update->id = (int)$gi_record->id;
            $changed = false;

            if ((int)($gi_record->categoryid ?? 0) !== $expected_parent_categoryid) {
                $update->categoryid = $expected_parent_categoryid;
                $changed = true;
            }

            if ($parent_aggregation === 13) {
                $target_coef = (float)((int)$extra_credit);
                $target_coef2 = (float)$weight;
                $target_override = 1;
            } elseif ($parent_aggregation === 10 || $parent_aggregation === 11) {
                $target_coef = (float)($weight * 100.0);
                $target_coef2 = 0.0;
                $target_override = 0;
            } else {
                $target_coef = (float)((int)$extra_credit);
                $target_coef2 = 0.0;
                $target_override = 0;
            }

            if (abs((float)($gi_record->aggregationcoef ?? 0.0) - $target_coef) > 0.00001) {
                $update->aggregationcoef = $target_coef;
                $changed = true;
            }
            if (abs((float)($gi_record->aggregationcoef2 ?? 0.0) - $target_coef2) > 0.00001) {
                $update->aggregationcoef2 = $target_coef2;
                $changed = true;
            }
            if ((int)($gi_record->weightoverride ?? 0) !== $target_override) {
                $update->weightoverride = $target_override;
                $changed = true;
            }

            if ($changed) {
                $DB->update_record('grade_items', $update);
            }

            $applied_top_level_weights[] = $name . '=' . round($weight * 100.0, 2) . '%';
            $top_level_weight_expectations[(int)$gi_record->id] = [
                'name' => $name,
                'aggregation' => $parent_aggregation,
                'target_coef' => (float)$target_coef,
                'target_coef2' => (float)$target_coef2,
                'target_override' => (int)$target_override,
            ];
        }

        if (!empty($applied_top_level_weights)) {
            $warnings[] = 'ℹ Applied top-level category weights: ' . implode(', ', $applied_top_level_weights) . '.';
        }

        if (!empty($top_level_weight_expectations)) {
            $records = $DB->get_records_list(
                'grade_items',
                'id',
                array_keys($top_level_weight_expectations),
                '',
                'id, aggregationcoef, aggregationcoef2, weightoverride'
            );

            foreach ($top_level_weight_expectations as $grade_item_id => $expected) {
                $record = $records[$grade_item_id] ?? null;
                if (!$record) {
                    $warnings[] = '⚠ Post-apply verification could not load grade item for category "'
                        . (string)$expected['name'] . '".';
                    continue;
                }

                $actual_coef = (float)($record->aggregationcoef ?? 0.0);
                $actual_coef2 = (float)($record->aggregationcoef2 ?? 0.0);
                $actual_override = (int)($record->weightoverride ?? 0);

                $coef_mismatch = abs($actual_coef - (float)$expected['target_coef']) > 0.00001;
                $coef2_mismatch = abs($actual_coef2 - (float)$expected['target_coef2']) > 0.00001;
                $override_mismatch = $actual_override !== (int)$expected['target_override'];

                if ($coef_mismatch || $coef2_mismatch || $override_mismatch) {
                    $warnings[] = '⚠ Post-apply category weight mismatch for "' . (string)$expected['name']
                        . '": expected coef=' . round((float)$expected['target_coef'], 6)
                        . ', coef2=' . round((float)$expected['target_coef2'], 6)
                        . ', override=' . (int)$expected['target_override']
                        . '; got coef=' . round($actual_coef, 6)
                        . ', coef2=' . round($actual_coef2, 6)
                        . ', override=' . $actual_override
                        . ' (aggregation=' . (int)$expected['aggregation'] . ').';

                    error_log(
                        'block_ai_assistant: gradebook finalize weight mismatch'
                        . ' courseid=' . (int)$courseid
                        . ' category=' . (string)$expected['name']
                        . ' aggregation=' . (int)$expected['aggregation']
                        . ' expected_coef=' . (float)$expected['target_coef']
                        . ' expected_coef2=' . (float)$expected['target_coef2']
                        . ' expected_override=' . (int)$expected['target_override']
                        . ' actual_coef=' . $actual_coef
                        . ' actual_coef2=' . $actual_coef2
                        . ' actual_override=' . $actual_override
                    );
                }
            }
        }

        if (!empty($keephighoverriddencategories)) {
            $warnings[] = '⚠ WARNING: Keep-highest rules were overridden by Moodle site grade settings for: '
                . implode(', ', array_values(array_unique($keephighoverriddencategories)))
                . '.';
        }

        // Build per-category item_weights lookup (name → [item_name → weight_pct]).
        // Also count how many items are assigned to each category to handle single-item weight enforcement.
        $category_item_weights = [];
        $category_item_counts = [];

        foreach ($proposal['categories'] as $cat) {
            $cat_key = strtolower(trim((string)($cat['name'] ?? '')));
            $weights = is_array($cat['item_weights'] ?? null) ? $cat['item_weights'] : [];
            if (!empty($weights)) {
                $category_item_weights[$cat_key] = $weights;
            }
        }

        foreach ($confirmed_mapping as $mapping) {
            $cat_name = trim((string)($mapping['category'] ?? ''));
            if ($cat_name !== '') {
                $ckey = strtolower($cat_name);
                $category_item_counts[$ckey] = ($category_item_counts[$ckey] ?? 0) + 1;
            }
        }

        // Materialize proposal-only manual grade items before mapped items are moved.
        // This covers chat prompts like "add item exam to Midterm" which backend
        // proposal validation accepts, but which were previously never created in Moodle.
        $course_category = \grade_category::fetch_course_category($courseid);
        $course_category_id = $course_category ? (int)$course_category->id : 0;
        $mapped_grade_item_ids = [];
        $mapped_labels_global = [];
        $mapping_by_activity = [];
        foreach ($confirmed_mapping as $mapping) {
            if (!is_array($mapping)) {
                continue;
            }

            $mapped_grade_item_id = (int)($mapping['grade_item_id'] ?? 0);
            if ($mapped_grade_item_id > 0) {
                $mapped_grade_item_ids[$mapped_grade_item_id] = true;
            }

            $mapped_activity_name = strtolower(trim((string)($mapping['activity_name'] ?? $mapping['grade_item_name'] ?? '')));
            if ($mapped_activity_name !== '') {
                $mapped_labels_global[$mapped_activity_name] = true;
                $mapping_by_activity[$mapped_activity_name] = $mapping;
            }
        }

        $course_manual_items = $DB->get_records_select(
            'grade_items',
            "courseid = ? AND itemtype = 'manual'",
            [$courseid],
            'id ASC',
            'id, courseid, categoryid, itemtype, itemname, grademin, grademax, gradetype, aggregationcoef, aggregationcoef2, weightoverride, hidden, locked, locktime, display, decimals, gradepass'
        );
        $manual_items_by_name = [];
        foreach (($course_manual_items ?: []) as $manual_item) {
            $manual_name_key = strtolower(trim((string)($manual_item->itemname ?? '')));
            if ($manual_name_key === '') {
                continue;
            }
            if (!isset($manual_items_by_name[$manual_name_key])) {
                $manual_items_by_name[$manual_name_key] = [];
            }
            $manual_items_by_name[$manual_name_key][] = $manual_item;
        }

        $mod_grade_item_names = [];
        $modgradeitems = $DB->get_records_select(
            'grade_items',
            "courseid = ? AND itemtype = 'mod'",
            [$courseid],
            'id ASC',
            'id, itemname'
        );
        foreach (($modgradeitems ?: []) as $moditem) {
            $modnamekey = strtolower(trim((string)($moditem->itemname ?? '')));
            if ($modnamekey !== '') {
                $mod_grade_item_names[$modnamekey] = true;
            }
        }

        foreach ($proposal['categories'] as $cat) {
            $category_name = trim((string)($cat['name'] ?? ''));
            if ($category_name === '') {
                continue;
            }

            $category_key = strtolower($category_name);
            if (empty($category_id_map[$category_key])) {
                continue;
            }

            $target_category_id = (int)$category_id_map[$category_key];
            $manual_items = is_array($cat['items'] ?? null) ? $cat['items'] : [];
            foreach ($manual_items as $manual_item_name_raw) {
                $manual_item_name = trim((string)$manual_item_name_raw);
                if ($manual_item_name === '') {
                    continue;
                }

                $manual_item_key = strtolower($manual_item_name);
                if (!empty($mod_grade_item_names[$manual_item_key])) {
                    continue;
                }
                if (!empty($mapped_labels_global[$manual_item_key])) {
                    $labelalreadyapplied = false;
                    foreach (($manual_items_by_name[$manual_item_key] ?? []) as $candidate) {
                        if (!empty($mapped_grade_item_ids[(int)$candidate->id])) {
                            $labelalreadyapplied = true;
                            break;
                        }
                    }
                    if ($labelalreadyapplied) {
                        continue;
                    }
                }

                // If not in confirmed mapping, this item contributes to its category item count.
                $category_item_counts[$category_key] = ($category_item_counts[$category_key] ?? 0) + 1;

                if (isset($mapping_by_activity[$manual_item_key])) {
                    $resolved_target = self::resolve_mapping_target_category_id(
                        $mapping_by_activity[$manual_item_key],
                        $category_id_map,
                        $subcategory_by_parent,
                        $subcategory_global_map,
                        $warnings,
                        $manual_item_name
                    );
                    if ($resolved_target !== null) {
                        $target_category_id = $resolved_target;
                    }
                }

                $manual_grade_item = null;
                foreach (($manual_items_by_name[$manual_item_key] ?? []) as $candidate) {
                    if (!empty($mapped_grade_item_ids[(int)$candidate->id])) {
                        continue;
                    }
                    if ((int)($candidate->categoryid ?? 0) === $target_category_id) {
                        $manual_grade_item = \grade_item::fetch(['id' => (int)$candidate->id]);
                        break;
                    }
                    if ($manual_grade_item === null && $course_category_id > 0 && (int)($candidate->categoryid ?? 0) === $course_category_id) {
                        $manual_grade_item = \grade_item::fetch(['id' => (int)$candidate->id]);
                    }
                    if ($manual_grade_item === null) {
                        $manual_grade_item = \grade_item::fetch(['id' => (int)$candidate->id]);
                    }
                }

                if (!$manual_grade_item) {
                    $manual_grade_item = new \grade_item();
                    $manual_grade_item->courseid = $courseid;
                    $manual_grade_item->categoryid = $target_category_id;
                    $manual_grade_item->itemtype = 'manual';
                    $manual_grade_item->itemname = $manual_item_name;
                    $manual_grade_item->grademin = 0;
                    $manual_grade_item->grademax = 100;
                    if (defined('GRADE_TYPE_VALUE')) {
                        $manual_grade_item->gradetype = GRADE_TYPE_VALUE;
                    }
                    $manual_grade_item->insert('block_ai_assistant');
                    self::_register_ai_owned_grade_item($courseid, (int)$manual_grade_item->id);
                    $placement_label = $category_name;
                    if (isset($mapping_by_activity[$manual_item_key])) {
                        $mapped_sub = trim((string)($mapping_by_activity[$manual_item_key]['subcategory'] ?? ''));
                        if ($mapped_sub !== '' && $target_category_id !== (int)$category_id_map[$category_key]) {
                            $placement_label = $mapped_sub;
                        }
                    }
                    $warnings[] = 'ℹ Created manual grade item "' . $manual_item_name . '" in ' . $placement_label . '.';
                }

                if ((int)($manual_grade_item->categoryid ?? 0) !== $target_category_id) {
                    $manual_grade_item->categoryid = $target_category_id;
                }

                if (isset($category_visibility_by_id[$target_category_id])) {
                    self::apply_visibility_to_grade_item(
                        $manual_grade_item,
                        $category_visibility_by_id[$target_category_id]
                    );
                }

                if (isset($category_item_weights[$category_key][$manual_item_name])) {
                    $item_weight_pct = (float)$category_item_weights[$category_key][$manual_item_name];
                    $item_weight_frac = $item_weight_pct / 100.0;
                    if ($aggregation_method === 13) {
                        $manual_grade_item->weightoverride = 1;
                        $manual_grade_item->aggregationcoef2 = $item_weight_frac;
                    } elseif ($aggregation_method === 10 || $aggregation_method === 11) {
                        $manual_grade_item->aggregationcoef = $item_weight_pct;
                        $manual_grade_item->aggregationcoef2 = 0;
                        $manual_grade_item->weightoverride = 0;
                    }
                } else {
                    // Reset to natural/equal distribution if no explicit weight is requested.
                    // This is especially important for single-item categories to ensure 100% weight.
                    $manual_grade_item->weightoverride = 0;
                    if ($aggregation_method === 13) {
                        $manual_grade_item->aggregationcoef2 = 0;
                    }
                }

                $manual_grade_item->update('block_ai_assistant');
                $mapped_grade_item_ids[(int)$manual_grade_item->id] = true;
            }
        }

        // Move mapped grade items into mapped categories/subcategories.
        foreach ($confirmed_mapping as $mapping) {
            if (!is_array($mapping)) {
                continue;
            }

            $grade_item_id = intval($mapping['grade_item_id'] ?? 0);
            $category_name = trim((string)($mapping['category'] ?? ''));
            $category_key = strtolower($category_name);
            $activity_name = trim((string)($mapping['activity_name'] ?? ''));

            if ($grade_item_id <= 0 && $activity_name !== '') {
                $activity_key = strtolower($activity_name);
                foreach (($manual_items_by_name[$activity_key] ?? []) as $candidate) {
                    $grade_item_id = (int)$candidate->id;
                    break;
                }
            }

            if ($grade_item_id <= 0 || $category_name === '') {
                continue;
            }

            try {
                $target_category_id = self::resolve_mapping_target_category_id(
                    $mapping,
                    $category_id_map,
                    $subcategory_by_parent,
                    $subcategory_global_map,
                    $warnings,
                    $activity_name !== '' ? $activity_name : ('#' . $grade_item_id)
                );

                if ($target_category_id === null) {
                    continue;
                }

                $gi = \grade_item::fetch(['id' => $grade_item_id]);
                if (!$gi || (int)$gi->courseid !== $courseid) {
                    continue;
                }

                // Skip meta items (course and category totals are not movable)
                if ((string)($gi->itemtype ?? '') === 'course' || (string)($gi->itemtype ?? '') === 'category') {
                    continue;
                }

                $constraint = self::gradebook_item_mutation_constraint($gi);
                if ($constraint !== null) {
                    $itemlabel = trim((string)($gi->itemname ?? ''));
                    if ($itemlabel === '') {
                        $itemlabel = 'item #' . $grade_item_id;
                    }
                    $warnings[] = '⚠ Skipped moving ' . $itemlabel . ': ' . $constraint . '.';
                    continue;
                }

                $gi->categoryid = $target_category_id;

                if (isset($category_visibility_by_id[(int)$target_category_id])) {
                    self::apply_visibility_to_grade_item(
                        $gi,
                        $category_visibility_by_id[(int)$target_category_id]
                    );
                }

                // Apply per-item weight override if present for this category.
                if (isset($category_item_weights[$category_key][$activity_name])) {
                    $item_weight_pct = (float)$category_item_weights[$category_key][$activity_name];
                    $item_weight_frac = $item_weight_pct / 100.0;
                    if ($aggregation_method === 13) {
                        $gi->weightoverride   = 1;
                        $gi->aggregationcoef2 = $item_weight_frac;
                    } elseif ($aggregation_method === 10 || $aggregation_method === 11) {
                        $gi->aggregationcoef  = $item_weight_pct;
                        $gi->aggregationcoef2 = 0;
                        $gi->weightoverride   = 0;
                    }
                } else {
                    // Reset to natural/equal distribution if no explicit weight is requested.
                    // This is especially important for single-item categories to ensure 100% weight.
                    $gi->weightoverride = 0;
                    if ($aggregation_method === 13) {
                        $gi->aggregationcoef2 = 0;
                    }
                }

                $gi->update('block_ai_assistant');
            } catch (Throwable $e) {
                $warnings[] = '⚠ Failed to move grade item #' . $grade_item_id . ' to category ' . $category_name . ': ' . $e->getMessage();
                debugging('Error mapping grade_item_id ' . $grade_item_id . ' to category ' . $category_name . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // Apply category calculation formulas after category/item mapping is complete.
        if (!empty($category_formula_map)) {
            $formula_item_ref_map = self::build_formula_item_reference_map($courseid);
            $formula_warnings = self::apply_category_formulas(
                $courseid,
                $category_formula_map,
                $formula_reference_alias_map,
                $formula_item_ref_map
            );
            if (!empty($formula_warnings)) {
                $warnings = array_merge($warnings, $formula_warnings);
            }
        }

        // Clean up orphaned items and fix null sortorders before regrade to prevent null property errors.
        $applywarnings = self::gradebook_integrity_warnings($courseid, 'gradebook apply');
        if (!empty($applywarnings)) {
            $warnings = array_merge($warnings, $applywarnings);
        }

        try {
            grade_regrade_final_grades($courseid);
        } catch (\Throwable $regradeex) {
            debugging('grade_regrade_final_grades failed after gradebook apply for course ' . $courseid . ': ' . $regradeex->getMessage(), DEBUG_DEVELOPER);
            $warnings[] = '⚠ Grade regrade failed after apply: ' . $regradeex->getMessage();
        }

        return $warnings;
    }

    /**
     * Rehome legacy top-level categories into the AI root to avoid duplicate trees.
     *
    * Rehome when:
    * - Category name matches a proposal category.
    * - It is currently a direct child of course total.
    * - No same-name category already exists under the AI root.
    *
    * This keeps reruns idempotent and prevents duplicate top-level + AI-root trees.
    * Any existing items/subcategories are preserved because set_parent moves the branch.
     */
    private static function rehome_legacy_top_level_categories(
        int $courseid,
        \grade_category $root_parent,
        array $proposal_categories,
        array &$warnings
    ): void {
        $coursecat = \grade_category::fetch_course_category($courseid);
        if (!$coursecat) {
            return;
        }

        $names = [];
        foreach ($proposal_categories as $cat) {
            $name = trim((string)($cat['name'] ?? ''));
            if ($name !== '') {
                $names[$name] = true;
            }
        }

        foreach (array_keys($names) as $name) {
            $existing_list = \grade_category::fetch_all([
                'courseid' => $courseid,
                'fullname' => $name,
            ]);
            if (!$existing_list) {
                continue;
            }

            $already_under_root = false;
            $legacy_top_level = null;
            foreach ($existing_list as $existing) {
                if ((int)$existing->id === (int)$root_parent->id) {
                    continue;
                }
                if ((int)$existing->parent === (int)$root_parent->id) {
                    $already_under_root = true;
                    break;
                }
                if ((int)$existing->parent === (int)$coursecat->id) {
                    $legacy_top_level = $existing;
                }
            }

            if ($legacy_top_level === null) {
                continue;
            }

            if ($already_under_root) {
                $legacy_id = (int)$legacy_top_level->id;
                if (self::category_has_non_structural_items($courseid, $legacy_id)) {
                    continue;
                }
                if (self::category_has_children($courseid, $legacy_id)) {
                    continue;
                }

                try {
                    self::_delete_category_total_item($courseid, $legacy_id);
                    $legacy_obj = \grade_category::fetch(['id' => $legacy_id]);
                    if ($legacy_obj) {
                        $legacy_obj->delete('block_ai_assistant');
                    }
                    $warnings[] = 'ℹ Removed legacy duplicate top-level category "' . $name
                        . '" that was outside "' . (string)$root_parent->fullname . '".';
                } catch (\Throwable $e) {
                    $warnings[] = '⚠ Could not remove legacy duplicate category "' . $name . '": ' . $e->getMessage();
                }
                continue;
            }

            try {
                $legacy_top_level->set_parent((int)$root_parent->id);
                $warnings[] = 'ℹ Moved legacy top-level category "' . $name
                    . '" under "' . (string)$root_parent->fullname
                    . '" to avoid duplicate category trees.';
            } catch (\Throwable $e) {
                $warnings[] = '⚠ Could not rehome legacy category "' . $name . '": ' . $e->getMessage();
            }
        }
    }

    /**
     * Return true when category contains real grade items (mod/manual/etc).
     */
    private static function category_has_non_structural_items(int $courseid, int $category_id): bool
    {
        global $DB;
        return (int)$DB->count_records_select(
            'grade_items',
            "courseid = ? AND categoryid = ? AND itemtype <> 'category'",
            [$courseid, $category_id]
        ) > 0;
    }

    /**
     * Return true when category has direct child categories.
     */
    private static function category_has_children(int $courseid, int $category_id): bool
    {
        global $DB;
        return $DB->record_exists('grade_categories', ['courseid' => $courseid, 'parent' => $category_id]);
    }

    /**
     * Return non-null reason when a grade item should not be structurally moved by AI apply.
     */
    private static function gradebook_item_mutation_constraint(\grade_item $gi): ?string
    {
        try {
            if (method_exists($gi, 'is_locked') && (bool)$gi->is_locked()) {
                return 'item is locked';
            }
        } catch (\Throwable $e) {
        }

        if ((int)($gi->locked ?? 0) > 0) {
            return 'item is locked';
        }

        $itemmodule = strtolower(trim((string)($gi->itemmodule ?? '')));
        if (in_array($itemmodule, ['lti', 'tool', 'external'], true)) {
            return 'item is external/LTI managed';
        }

        return null;
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

        // Ensure categoryid is correct: should point to parent category (or NULL if top-level)
        $expected_categoryid = $parent ? (int)$parent->id : null;
        if ((int)($gi->categoryid ?? 0) !== ((int)($expected_categoryid ?? 0))) {
            $gi->categoryid = $expected_categoryid;
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
        self::apply_visibility_to_grade_item($gi, self::normalize_visibility_settings($settings));

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

    /**
     * Normalize visibility settings to Moodle hidden semantics.
     *
     * @param array $settings
     * @return array{hidden: bool, hidden_until: ?int}
     */
    private static function normalize_visibility_settings(array $settings): array
    {
        $hidden = !empty($settings['hidden']);
        $hidden_until = isset($settings['hidden_until']) && (int)$settings['hidden_until'] > 0
            ? (int)$settings['hidden_until']
            : null;

        if ($hidden) {
            $hidden_until = null;
        }

        return [
            'hidden' => $hidden,
            'hidden_until' => $hidden_until,
        ];
    }

    /**
     * Inherit visibility from a parent category using Moodle behavior.
     *
     * @param array $settings
     * @param array $parent_visibility
     * @return array
     */
    private static function merge_visibility_with_parent(array $settings, array $parent_visibility): array
    {
        $current = self::normalize_visibility_settings($settings);
        $parent = self::normalize_visibility_settings($parent_visibility);

        if (!empty($parent['hidden'])) {
            $current['hidden'] = true;
            $current['hidden_until'] = null;
        } elseif (!empty($parent['hidden_until']) && empty($current['hidden'])) {
            $current['hidden_until'] = max((int)$current['hidden_until'], (int)$parent['hidden_until']);
        }

        $settings['hidden'] = (bool)$current['hidden'];
        $settings['hidden_until'] = $current['hidden_until'];
        return $settings;
    }

    /**
     * Apply normalized visibility settings to a grade item.
     *
     * @param \grade_item $grade_item
     * @param array $visibility
     * @return void
     */
    private static function apply_visibility_to_grade_item(\grade_item $grade_item, array $visibility): void
    {
        $normalized = self::normalize_visibility_settings($visibility);
        if (!empty($normalized['hidden'])) {
            $grade_item->hidden = 1;
            return;
        }

        if (!empty($normalized['hidden_until'])) {
            $grade_item->hidden = (int)$normalized['hidden_until'];
            return;
        }

        $grade_item->hidden = 0;
    }

    /**
     * Convert a user-facing formula into Moodle-compatible calculation syntax.
     *
     * - Normalizes separators to comma (YorkU standard).
     * - Accepts both [ref] and [[ref]] input.
     * - Resolves refs against grade_item idnumber first, then itemname.
     * - Ensures output uses [[idnumber]] references.
     *
     * @param array<string, string> $item_alias_map
     */
    private static function resolve_moodle_formula(
        int $courseid,
        string $formula,
        array $alias_category_map = [],
        array $item_alias_map = []
    ): array {
        global $DB;

        $text = trim($formula);
        if ($text === '') {
            return ['formula' => '', 'warnings' => [], 'errors' => []];
        }
        if ($text[0] !== '=') {
            $text = '=' . $text;
        }
        $text = str_replace(';', ',', $text);

        $warnings = [];
        $errors = [];

        if (!preg_match_all('/\[\[([A-Za-z0-9_]+)\]\]|\[([A-Za-z0-9_]+)\]/', $text, $matches, PREG_SET_ORDER)) {
            return ['formula' => $text, 'warnings' => [], 'errors' => []];
        }

        $resolved = [];
        foreach ($matches as $m) {
            $ref = isset($m[1]) && $m[1] !== '' ? $m[1] : (isset($m[2]) ? $m[2] : '');
            if ($ref === '') {
                continue;
            }
            $key = strtolower($ref);
            if (isset($resolved[$key])) {
                continue;
            }

            if (array_key_exists($key, $alias_category_map)) {
                $alias_category_id = (int)$alias_category_map[$key];
                if ($alias_category_id < 1) {
                    $errors[] = 'Reference [' . $ref . '] is ambiguous (multiple categories match this alias).';
                    continue;
                }

                $alias_item = $DB->get_record(
                    'grade_items',
                    ['courseid' => $courseid, 'itemtype' => 'category', 'iteminstance' => $alias_category_id],
                    'id, idnumber, itemname',
                    IGNORE_MISSING
                );

                if ($alias_item) {
                    $resolved[$key] = self::ensure_formula_item_idnumber(
                        $courseid,
                        $alias_item,
                        $ref,
                        $ref,
                        $warnings
                    );
                    continue;
                }
            }

            if (array_key_exists($key, $item_alias_map)) {
                $resolved[$key] = (string)$item_alias_map[$key];
                continue;
            }

            if (ctype_digit($key)) {
                $item_by_id = $DB->get_record(
                    'grade_items',
                    ['id' => (int)$key, 'courseid' => $courseid],
                    'id, idnumber, itemname',
                    IGNORE_MISSING
                );
                if ($item_by_id) {
                    $resolved[$key] = self::ensure_formula_item_idnumber(
                        $courseid,
                        $item_by_id,
                        'gi_' . $item_by_id->id,
                        $ref,
                        $warnings
                    );
                    continue;
                }
            }

            $items_by_idnumber = $DB->get_records_select(
                'grade_items',
                'courseid = ? AND LOWER(idnumber) = ?',
                [$courseid, $key],
                '',
                'id, idnumber, itemname'
            );

            $item = null;
            if ($items_by_idnumber && count($items_by_idnumber) === 1) {
                $item = reset($items_by_idnumber);
            } else if ($items_by_idnumber && count($items_by_idnumber) > 1) {
                $errors[] = 'Reference [' . $ref . '] is ambiguous (multiple grade items share this idnumber).';
                continue;
            }

            if (!$item) {
                $items_by_name = $DB->get_records_select(
                    'grade_items',
                    'courseid = ? AND LOWER(itemname) = ?',
                    [$courseid, $key],
                    '',
                    'id, idnumber, itemname'
                );

                if (!$items_by_name) {
                    $errors[] = 'Reference [' . $ref . '] does not match any grade item idnumber or item name.';
                    continue;
                }

                if (count($items_by_name) > 1) {
                    $errors[] = 'Reference [' . $ref . '] is ambiguous (multiple grade items share this item name).';
                    continue;
                }

                $item = reset($items_by_name);
                $warnings[] = 'Reference [' . $ref . '] resolved by item name. Prefer using exact idnumber references.';
            }

            $resolved[$key] = self::ensure_formula_item_idnumber(
                $courseid,
                $item,
                trim((string)($item->itemname ?? '')),
                $ref,
                $warnings
            );
        }

        $converted = preg_replace_callback(
            '/\[\[([A-Za-z0-9_]+)\]\]|\[([A-Za-z0-9_]+)\]/',
            static function (array $m) use ($resolved): string {
                $ref = isset($m[1]) && $m[1] !== '' ? $m[1] : (isset($m[2]) ? $m[2] : '');
                if ($ref === '') {
                    return $m[0];
                }
                $key = strtolower($ref);
                $target = $resolved[$key] ?? $ref;
                return '[[' . $target . ']]';
            },
            $text
        );

        return [
            'formula' => is_string($converted) ? $converted : $text,
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    private static function build_formula_idnumber(string $value): string
    {
        $id = strtolower(trim($value));
        $id = preg_replace('/[^a-z0-9_]+/', '_', $id) ?? '';
        $id = trim($id, '_');
        if ($id === '') {
            $id = 'ai_formula_ref';
        }
        if (strlen($id) > 90) {
            $id = substr($id, 0, 90);
        }
        return $id;
    }

    /**
     * Build a formula reference token from a user-facing label.
     */
    private static function build_formula_reference_token(string $value): string
    {
        $token = strtolower(trim($value));
        $token = preg_replace('/[^a-z0-9_]+/', '_', $token) ?? '';
        return trim($token, '_');
    }

    /**
     * Register formula aliases for a category/subcategory label.
     * Alias collisions are marked as ambiguous (0) and skipped during resolution.
     */
    private static function register_formula_aliases(array &$alias_map, string $label, int $categoryid): void
    {
        $label = trim($label);
        if ($label === '' || $categoryid < 1) {
            return;
        }

        $aliases = [];
        $token = self::build_formula_reference_token($label);
        if ($token !== '') {
            $aliases[] = $token;

            $compact = str_replace('_', '', $token);
            if ($compact !== '' && $compact !== $token) {
                $aliases[] = $compact;
            }

            if (str_ends_with($token, 's') && strlen($token) > 3) {
                $aliases[] = rtrim($token, 's');
            } else if (!str_ends_with($token, 's') && strlen($token) > 2) {
                $aliases[] = $token . 's';
            }
        }

        $parts = preg_split('/\s+/', $label) ?: [];
        if (!empty($parts)) {
            $first = self::build_formula_reference_token((string)($parts[0] ?? ''));
            if ($first !== '' && strlen($first) > 2) {
                $aliases[] = $first;
            }
        }

        $aliases = array_values(array_unique(array_filter($aliases, static fn($a) => $a !== '')));
        foreach ($aliases as $alias) {
            if (!array_key_exists($alias, $alias_map)) {
                $alias_map[$alias] = $categoryid;
                continue;
            }
            if ((int)$alias_map[$alias] !== $categoryid) {
                $alias_map[$alias] = 0;
            }
        }
    }

    /**
     * Ensure a grade item has idnumber and return it.
     */
    private static function ensure_formula_item_idnumber(int $courseid, \stdClass $item, string $seed, string $ref, array &$warnings): string
    {
        global $DB;

        $idnumber = trim((string)($item->idnumber ?? ''));
        if ($idnumber !== '') {
            return $idnumber;
        }

        $base_seed = trim($seed);
        if ($base_seed === '') {
            $base_seed = trim((string)($item->itemname ?? ''));
        }
        if ($base_seed === '') {
            $base_seed = $ref;
        }

        $base = self::build_formula_idnumber($base_seed);
        $candidate = $base;
        $suffix = 2;
        while ($DB->record_exists_select('grade_items', 'courseid = ? AND idnumber = ? AND id <> ?', [$courseid, $candidate, (int)$item->id])) {
            $candidate = $base . '_' . $suffix;
            $suffix++;
            if ($suffix > 100) {
                break;
            }
        }

        $item->idnumber = $candidate;
        $DB->update_record('grade_items', $item);
        $warnings[] = 'Reference [' . $ref . '] had no idnumber; generated idnumber [[' . $candidate . ']] for stable formula resolution.';
        return $candidate;
    }

    /**
     * Apply calculation formulas to category total grade items.
     * Returns warning strings for any categories that could not be updated.
     */
    /**
     * @return array<string, string>
     */
    private static function build_formula_item_reference_map(int $courseid): array
    {
        global $DB;

        if ($courseid < 1) {
            return [];
        }

        $map = [];
        $records = $DB->get_records_select(
            'grade_items',
            "courseid = ? AND itemtype IN ('manual', 'mod')",
            [$courseid],
            'id ASC',
            'id, itemname, idnumber'
        );

        foreach (($records ?: []) as $record) {
            $label = trim((string)($record->itemname ?? ''));
            if ($label === '') {
                continue;
            }
            $idnumberwarnings = [];
            $idnumber = self::ensure_formula_item_idnumber(
                $courseid,
                $record,
                $label,
                $label,
                $idnumberwarnings
            );
            self::register_item_formula_aliases($map, $label, $idnumber);
        }

        return $map;
    }

    /**
     * @param array<string, string> $map
     */
    private static function register_item_formula_aliases(array &$map, string $label, string $idnumber): void
    {
        $label = trim($label);
        $idnumber = trim($idnumber);
        if ($label === '' || $idnumber === '') {
            return;
        }

        $token = self::build_formula_reference_token($label);
        if ($token !== '') {
            $map[$token] = $idnumber;
            $compact = str_replace('_', '', $token);
            if ($compact !== '' && $compact !== $token) {
                $map[$compact] = $idnumber;
            }
        }

        $lower = strtolower($label);
        if (preg_match('/\b(\d{1,3})\b/', $label, $number_match)) {
            $num = (string)($number_match[1] ?? '');
            if ($num !== '') {
                if (strpos($lower, 'hw') !== false || strpos($lower, 'homework') !== false) {
                    $map['hw' . $num] = $idnumber;
                }
                if (strpos($lower, 'lab') !== false) {
                    $map['lab' . $num] = $idnumber;
                }
                if (strpos($lower, 'quiz') !== false || strpos($lower, 'test') !== false) {
                    $map['quiz' . $num] = $idnumber;
                    $map['q' . $num] = $idnumber;
                }
            }
        }

        if (strpos($lower, 'project') !== false) {
            $map['project'] = $idnumber;
            $map['proj'] = $idnumber;
        }
    }

    /**
     * @param array<int, string> $formula_item_refs
     * @param array<string, string> $item_alias_map
     */
    private static function remap_stale_formula_refs_with_item_aliases(
        string $formula,
        array $formula_item_refs,
        array $item_alias_map
    ): string {
        if ($formula === '' || empty($formula_item_refs) || empty($item_alias_map)) {
            return $formula;
        }

        if (!preg_match_all('/\[\[([A-Za-z0-9_]+)\]\]|\[([A-Za-z0-9_]+)\]/', $formula, $matches, PREG_SET_ORDER)) {
            return $formula;
        }

        $converted = $formula;
        $index = 0;
        foreach ($matches as $match) {
            $ref = isset($match[1]) && $match[1] !== '' ? $match[1] : (isset($match[2]) ? $match[2] : '');
            $alias = trim((string)($formula_item_refs[$index] ?? ''));
            $index++;
            if ($ref === '' || $alias === '') {
                continue;
            }
            if (!ctype_digit($ref)) {
                continue;
            }

            $alias_key = self::build_formula_reference_token($alias);
            $target = $item_alias_map[$alias_key]
                ?? $item_alias_map[str_replace('_', '', $alias_key)]
                ?? null;
            if ($target === null || $target === '') {
                continue;
            }

            $converted = str_replace('[[' . $ref . ']]', '[[' . $target . ']]', $converted);
            $converted = str_replace('[' . $ref . ']', '[[' . $target . ']]', $converted);
        }

        return $converted;
    }

    private static function apply_category_formulas(
        int $courseid,
        array $category_formula_map,
        array $formula_reference_alias_map = [],
        array $formula_item_ref_map = []
    ): array {
        $warnings = [];
        foreach ($category_formula_map as $key => $entry) {
            $category_id = (int)($entry['category_id'] ?? 0);
            $formula = trim((string)($entry['formula'] ?? ''));
            $explicit_override = (bool)($entry['explicit_override'] ?? false);
            $formula_item_refs = is_array($entry['formula_item_refs'] ?? null) ? $entry['formula_item_refs'] : [];
            if ($category_id < 1 || $formula === '') {
                continue;
            }

            try {
                $gi = \grade_item::fetch(['itemtype' => 'category', 'iteminstance' => $category_id]);
                if (!$gi) {
                    $warnings[] = '⚠ Formula was not applied for category ' . $key . ' (missing category grade item).';
                    continue;
                }

                if (!empty($formula_item_refs) && !empty($formula_item_ref_map)) {
                    $formula = self::remap_stale_formula_refs_with_item_aliases(
                        $formula,
                        $formula_item_refs,
                        $formula_item_ref_map
                    );
                }

                $resolved_data = self::resolve_moodle_formula(
                    $courseid,
                    $formula,
                    $formula_reference_alias_map,
                    $formula_item_ref_map
                );
                $resolved = trim((string)($resolved_data['formula'] ?? ''));
                $resolver_warnings = is_array($resolved_data['warnings'] ?? null) ? $resolved_data['warnings'] : [];
                $resolver_errors = is_array($resolved_data['errors'] ?? null) ? $resolved_data['errors'] : [];

                $existing_formula = trim((string)($gi->calculation ?? ''));
                if ($existing_formula !== '' && $resolved !== '' && $existing_formula !== $resolved && !$explicit_override) {
                    $warnings[] = '⚠ Preserved existing formula for category ' . $key
                        . ' (explicit override required for formula-backed categories).';
                    continue;
                }

                if (!empty($resolver_errors)) {
                    $warnings[] = '⚠ Formula was not applied for category ' . $key . ': ' . implode(' ', $resolver_errors);
                    continue;
                }

                foreach ($resolver_warnings as $w) {
                    $warnings[] = 'ℹ Formula note for category ' . $key . ': ' . $w;
                }

                if (method_exists($gi, 'set_calculation')) {
                    $gi->set_calculation($resolved, null);
                } else {
                    $gi->calculation = $resolved;
                    $gi->update('block_ai_assistant');
                }
            } catch (Throwable $e) {
                $warnings[] = '⚠ Formula was not applied for category ' . $key . ': ' . $e->getMessage();
            }
        }

        return $warnings;
    }

    /**
     * Clean up orphaned category grade items that may have been left behind
     * when the block was removed/re-added. These items can have NULL categoryid
     * which causes "Attempt to assign property 'sortorder' on null" errors.
     *
     * Removes category grade_items whose iteminstance points to a non-existent
     * grade_category, or have NULL categoryid when they shouldn't.
     *
     * Also removes stale course-total grade_items (itemtype='course') left behind
     * when categories are recreated with new IDs. Those rows break Moodle grade UI
     * AJAX (get_gradeitems) and grade tree rendering.
     *
     * @param int $courseid Course ID to clean (0 = all courses)
     * @return int Number of orphaned items removed
     */
    public static function cleanup_orphaned_category_items(int $courseid = 0): int {
        global $DB;

        if (!class_exists('\grade_item')) {
            return 0;
        }

        $removed = 0;

        // Find all category-type grade items
        $sql = 'SELECT gi.id, gi.courseid, gi.iteminstance, gi.categoryid
                FROM {grade_items} gi
                WHERE gi.itemtype = ?';
        $params = ['category'];

        if ($courseid > 0) {
            $sql .= ' AND gi.courseid = ?';
            $params[] = $courseid;
        }

        $items = $DB->get_records_sql($sql, $params);

        foreach ($items as $item) {
            $category_id = (int)($item->iteminstance ?? 0);

            // Check if the referenced category exists
            if ($category_id < 1 || !$DB->record_exists('grade_categories', ['id' => $category_id])) {
                $DB->delete_records('grade_grades', ['itemid' => (int)$item->id]);
                $DB->delete_records('grade_items', ['id' => (int)$item->id]);
                $removed++;
                continue;
            }

            // Verify the category's parent matches the grade_item's categoryid
            $gc = $DB->get_record('grade_categories', ['id' => $category_id], 'id, parent');
            if ($gc) {
                $expected_categoryid = $gc->parent ? (int)$gc->parent : null;
                $actual_categoryid = (int)($item->categoryid ?? 0);
                $actual_categoryid = $actual_categoryid === 0 ? null : $actual_categoryid;

                if ($expected_categoryid !== $actual_categoryid) {
                    // Fix the categoryid on the orphaned item
                    $update = new \stdClass();
                    $update->id = (int)$item->id;
                    $update->categoryid = $expected_categoryid;
                    $DB->update_record('grade_items', $update);
                }
            }
        }

        $removed += self::cleanup_orphaned_course_items($courseid);

        return $removed;
    }

    /**
     * Repair gradebook structural integrity.
     *
     * Safe to call before grade report/tree pages. Removes stale itemtype='course' rows
     * that reference deleted root category ids (causes morethanonerecordinfetch).
     *
     * @param int $courseid
     * @return array{removed:int, fixed:int, course_items:int}
     */
    public static function ensure_course_gradebook_integrity(int $courseid): array {
        global $CFG, $DB;

        if ($courseid < 1) {
            return ['removed' => 0, 'fixed' => 0, 'course_items' => 0];
        }

        require_once($CFG->libdir . '/grade/grade_category.php');
        require_once($CFG->libdir . '/grade/grade_item.php');

        $removed = self::cleanup_orphaned_category_items($courseid);
        $fixed = self::ensure_gradebook_structural_items($courseid);
        self::normalize_null_sortorders($courseid);

        $root = \grade_category::fetch_course_category($courseid);
        if ($root) {
            $DB->set_field(
                'grade_items',
                'sortorder',
                1,
                ['courseid' => $courseid, 'itemtype' => 'course', 'iteminstance' => (int)$root->id]
            );
        }

        $courseitems = (int)$DB->count_records('grade_items', ['courseid' => $courseid, 'itemtype' => 'course']);

        return [
            'removed' => $removed,
            'fixed' => $fixed,
            'course_items' => $courseitems,
        ];
    }

    /**
     * Run integrity repair and return user-facing warning lines.
     *
     * @param int $courseid
     * @param string $contextlabel
     * @return string[]
     */
    private static function gradebook_integrity_warnings(int $courseid, string $contextlabel = ''): array {
        $warnings = [];
        $integrity = self::ensure_course_gradebook_integrity($courseid);

        if ($integrity['removed'] > 0) {
            $suffix = $contextlabel !== '' ? ' during ' . $contextlabel : '';
            $warnings[] = '⚠ Removed ' . (int)$integrity['removed'] . ' orphaned gradebook structural row(s)' . $suffix . '.';
        }

        if ((int)$integrity['course_items'] !== 1) {
            $warnings[] = '⚠ Gradebook integrity check found ' . (int)$integrity['course_items']
                . ' course-total items (expected 1). Run clean_gradebook_orphans for this course.';
        }

        return $warnings;
    }

    /**
     * Remove stale or duplicate course-total grade_items.
     *
     * @param int $courseid Course ID to clean (0 = all courses)
     * @return int Number of rows removed
     */
    private static function cleanup_orphaned_course_items(int $courseid = 0): int {
        global $DB;

        $removed = 0;
        $coursesql = '';
        $courseparams = [];
        if ($courseid > 0) {
            $coursesql = ' AND gi.courseid = ?';
            $courseparams[] = $courseid;
        }

        $courseitems = $DB->get_records_sql(
            'SELECT gi.id, gi.courseid, gi.iteminstance, gi.categoryid
               FROM {grade_items} gi
              WHERE gi.itemtype = ?' . $coursesql,
            array_merge(['course'], $courseparams)
        );

        $validbycourse = [];
        foreach ($courseitems as $item) {
            $cid = (int)$item->courseid;
            if (!isset($validbycourse[$cid])) {
                $root = \grade_category::fetch_course_category($cid);
                $validbycourse[$cid] = $root ? (int)$root->id : 0;
            }
            $validrootid = (int)$validbycourse[$cid];
            $iteminstance = (int)($item->iteminstance ?? 0);
            $shoulddelete = false;

            if ($validrootid < 1) {
                $shoulddelete = true;
            } else if ($iteminstance !== $validrootid) {
                $shoulddelete = true;
            } else if (!$DB->record_exists('grade_categories', ['id' => $validrootid, 'courseid' => $cid])) {
                $shoulddelete = true;
            } else {
                $actualcategoryid = (int)($item->categoryid ?? 0);
                if ($actualcategoryid !== $validrootid) {
                    $update = new \stdClass();
                    $update->id = (int)$item->id;
                    $update->categoryid = $validrootid;
                    $DB->update_record('grade_items', $update);
                }
            }

            if ($shoulddelete) {
                $DB->delete_records('grade_grades', ['itemid' => (int)$item->id]);
                $DB->delete_records('grade_items', ['id' => (int)$item->id]);
                $removed++;
            }
        }

        $dupesql = 'SELECT gi.courseid, MIN(gi.id) AS keepid
                      FROM {grade_items} gi
                     WHERE gi.itemtype = ?';
        $dupeparams = ['course'];
        if ($courseid > 0) {
            $dupesql .= ' AND gi.courseid = ?';
            $dupeparams[] = $courseid;
        }
        $dupesql .= ' GROUP BY gi.courseid HAVING COUNT(1) > 1';

        $dupes = $DB->get_records_sql($dupesql, $dupeparams);
        foreach ($dupes as $dupe) {
            $extras = $DB->get_records_select(
                'grade_items',
                'courseid = ? AND itemtype = ? AND id <> ?',
                [(int)$dupe->courseid, 'course', (int)$dupe->keepid],
                '',
                'id'
            );
            foreach ($extras as $extra) {
                $DB->delete_records('grade_grades', ['itemid' => (int)$extra->id]);
                $DB->delete_records('grade_items', ['id' => (int)$extra->id]);
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Ensure every grade_category has its structural grade_item.
     *
     * Repairs two corruption patterns that break grade/edit/tree rendering:
     * - Missing course total grade_item (itemtype='course')
     * - Missing category total grade_item (itemtype='category', iteminstance=category id)
     *
     * @param int $courseid
     * @return int number of repaired/created rows
     */
    private static function ensure_gradebook_structural_items(int $courseid): int
    {
        global $DB;

        if ($courseid < 1) {
            return 0;
        }

        if (!class_exists('\\grade_item') || !class_exists('\\grade_category')) {
            return 0;
        }

        self::cleanup_orphaned_category_items($courseid);

        $coursecat = \grade_category::fetch_course_category($courseid);
        if (!$coursecat) {
            return 0;
        }

        $fixed = 0;
        $nextsortorder = (int)$DB->get_field_sql(
            'SELECT COALESCE(MAX(sortorder), 0) FROM {grade_items} WHERE courseid = ?',
            [$courseid]
        ) + 1;

        // Ensure course total grade item exists.
        $courseitem = $DB->get_record(
            'grade_items',
            ['courseid' => $courseid, 'itemtype' => 'course', 'iteminstance' => (int)$coursecat->id],
            'id, categoryid, sortorder',
            IGNORE_MISSING
        );
        if (!$courseitem) {
            try {
                $gi = new \grade_item();
                $gi->courseid = $courseid;
                $gi->itemtype = 'course';
                $gi->iteminstance = (int)$coursecat->id;
                $gi->categoryid = (int)$coursecat->id;
                $gi->gradetype = defined('GRADE_TYPE_VALUE') ? GRADE_TYPE_VALUE : 1;
                $gi->grademin = 0;
                $gi->grademax = 100;
                $gi->sortorder = $nextsortorder++;
                $gi->insert('block_ai_assistant');
                $fixed++;
            } catch (\Throwable $e) {
                debugging('Could not recreate course total grade_item for course ' . $courseid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        $categories = $DB->get_records('grade_categories', ['courseid' => $courseid], 'id ASC', 'id,parent');
        foreach (($categories ?: []) as $cat) {
            $catid = (int)$cat->id;
            if ($catid === (int)$coursecat->id) {
                continue;
            }

            $item = $DB->get_record(
                'grade_items',
                ['courseid' => $courseid, 'itemtype' => 'category', 'iteminstance' => $catid],
                'id, categoryid, sortorder',
                IGNORE_MISSING
            );

            if (!$item) {
                try {
                    $gi = new \grade_item();
                    $gi->courseid = $courseid;
                    $gi->itemtype = 'category';
                    $gi->iteminstance = $catid;
                    $gi->categoryid = !empty($cat->parent) ? (int)$cat->parent : null;
                    $gi->gradetype = defined('GRADE_TYPE_VALUE') ? GRADE_TYPE_VALUE : 1;
                    $gi->grademin = 0;
                    $gi->grademax = 100;
                    $gi->sortorder = $nextsortorder++;
                    $gi->insert('block_ai_assistant');
                    $fixed++;
                } catch (\Throwable $e) {
                    debugging('Could not recreate category total grade_item for category ' . $catid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
                }
                continue;
            }

            $expectedparent = !empty($cat->parent) ? (int)$cat->parent : null;
            $actualparent = isset($item->categoryid) ? (int)$item->categoryid : null;
            $actualparent = ($actualparent === 0) ? null : $actualparent;
            if ($actualparent !== $expectedparent || (int)($item->sortorder ?? 0) < 1) {
                $update = new \stdClass();
                $update->id = (int)$item->id;
                $changed = false;

                if ($actualparent !== $expectedparent) {
                    $update->categoryid = $expectedparent;
                    $changed = true;
                }
                if ((int)($item->sortorder ?? 0) < 1) {
                    $update->sortorder = $nextsortorder++;
                    $changed = true;
                }

                if ($changed) {
                    $DB->update_record('grade_items', $update);
                    $fixed++;
                }
            }
        }

        return $fixed;
    }

    /**
     * @param array<int, array<string, mixed>> $resources
     * @return array<int, array<string, mixed>>
     */
    private static function dedupe_moodle_resources_by_name(array $resources): array
    {
        $seen = [];
        $deduped = [];

        foreach ($resources as $resource) {
            if (!is_array($resource)) {
                continue;
            }
            $name = strtolower(trim((string)($resource['name'] ?? '')));
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            $deduped[] = $resource;
        }

        return $deduped;
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
        global $USER;

        if ($courseid <= 0 || trim($filename) === '' || trim($base64) === '') {
            return;
        }

        $context = \context_course::instance($courseid);
        $fs = get_file_storage();
        $filearea = 'gradebookdocs';

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
    }

    private static function remove_gradebook_chat_uploaded_files(int $courseid): int
    {
        if ($courseid <= 0) {
            return 0;
        }

        try {
            $context = \context_course::instance($courseid);
            $fs = get_file_storage();
            $files = $fs->get_area_files($context->id, 'block_ai_assistant', 'gradebookdocs', $courseid, 'itemid', false);
            $removed = 0;
            foreach ($files as $file) {
                if ($file->is_directory()) {
                    continue;
                }
                $file->delete();
                $removed += 1;
            }
            return $removed;
        } catch (\Throwable $e) {
            return 0;
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
        $mappingchanged = false;
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
                    $incomingmapping = trim($confirmed_mapping_json);
                    $storedmapping = trim((string)($existing->confirmed_mapping_json ?? ''));
                    if ($incomingmapping !== $storedmapping) {
                        $mappingchanged = true;
                    }
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
            $incomingmapping = trim((string)($confirmed_mapping_json ?? ''));
            if ($incomingmapping !== '' && $incomingmapping !== '[]' && $incomingmapping !== 'null') {
                $mappingchanged = true;
            }
        }

        if (!$conflict && $mappingchanged) {
            $sessionid = trim((string)($record->session_id ?? ''));
            if ($sessionid !== '') {
                $decodedmapping = json_decode((string)($record->confirmed_mapping_json ?? ''), true);
                self::sync_gradebook_session_context(
                    $courseid,
                    $sessionid,
                    is_array($decodedmapping) ? $decodedmapping : []
                );
            }
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
        global $DB;

        $bot_identifier = '';
        $record = $DB->get_record('block_aia_settings', ['courseid' => (int)$course_id], 'bot_name, bot_id');
        if ($record) {
            $bot_identifier = trim((string)($record->bot_name ?? ''));
            if ($bot_identifier === '' && !empty($record->bot_id)) {
                $bot_identifier = (string)((int)$record->bot_id);
            }
        }

        if ($bot_identifier === '') {
            $bot_identifier = (string)((int)self::get_bot_id($course_id));
        }

        $session = webservice::exec_embed(
            $bot_identifier,
            $api_key,
            json_encode($payload)
        );
        return $session;
    }

}