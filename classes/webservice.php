<?php

namespace block_ai_assistant;

class webservice
{
    /**
     * Build the preferred embed loader URL for a bot.
     *
     * @param string|int $bot_id
     * @return string
     */
    public static function get_embed_loader_url($bot_id): string
    {
        $candidates = self::get_embed_loader_candidates($bot_id);
        return $candidates[0] ?? '';
    }

    /**
     * Build possible embed loader URLs from the configured base URL.
     *
     * Some environments still point Moodle at the embed app URL. In that case
     * the real JavaScript loader lives on the embed API service instead.
     *
     * @param string|int $bot_id
     * @return array
     */
    private static function get_embed_loader_candidates($bot_id): array
    {
        $config = get_config('block_ai_assistant');
        $base_url = rtrim(trim((string)($config->cria_embed_url ?? '')), '/');
        if ($base_url === '') {
            return [];
        }

        $bot_id = rawurlencode((string)$bot_id);
        $candidates = [];
        $add_candidate = static function(string $url) use (&$candidates, $bot_id): void {
            $url = rtrim(trim($url), '/');
            if ($url === '') {
                return;
            }

            $loader_url = $url . '/embed/' . $bot_id . '/load';
            if (!in_array($loader_url, $candidates, true)) {
                $candidates[] = $loader_url;
            }
        };

        $add_candidate($base_url);

        $parts = parse_url($base_url);
        if ($parts !== false && !empty($parts['host'])) {
            $variants = [];
            $host = (string)$parts['host'];
            $port = isset($parts['port']) ? (int)$parts['port'] : null;

            if (strpos($host, 'criaembed-app') !== false) {
                $variant = $parts;
                $variant['host'] = str_replace('criaembed-app', 'criaembed-api', $host);
                $variant['port'] = 3003;
                $variants[] = self::build_url_from_parts($variant);
            }

            if ($port === 4000) {
                $variant = $parts;
                $variant['port'] = 3003;
                $variants[] = self::build_url_from_parts($variant);
            }

            foreach ($variants as $variant_url) {
                $add_candidate($variant_url);
            }
        }

        return $candidates;
    }

    /**
     * Rebuild a URL from parse_url() parts.
     *
     * @param array $parts
     * @return string
     */
    private static function build_url_from_parts(array $parts): string
    {
        if (empty($parts['host'])) {
            return '';
        }

        $url = '';
        if (!empty($parts['scheme'])) {
            $url .= $parts['scheme'] . '://';
        }

        if (!empty($parts['user'])) {
            $url .= $parts['user'];
            if (isset($parts['pass'])) {
                $url .= ':' . $parts['pass'];
            }
            $url .= '@';
        }

        $url .= $parts['host'];

        if (isset($parts['port']) && (int)$parts['port'] > 0) {
            $url .= ':' . (int)$parts['port'];
        }

        if (!empty($parts['path']) && $parts['path'] !== '/') {
            $url .= rtrim((string)$parts['path'], '/');
        }

        return $url;
    }

    /**
     * Detect an invalid embed loader response.
     *
     * If the configured URL points at the embed app instead of the embed API,
     * the response is usually the full HTML shell instead of JavaScript.
     *
     * @param string $response
     * @return bool
     */
    private static function is_invalid_embed_loader_response(string $response): bool
    {
        $response = ltrim($response);
        if ($response === '') {
            return true;
        }

        if (preg_match('/^(<!DOCTYPE html|<html\b|<head\b|<body\b|<div\b|<meta\b)/i', $response) === 1) {
            return true;
        }

        // Loader must be JavaScript. Any generic HTML-like payload is invalid here.
        if (strpos($response, '<') === 0 && preg_match('/^<\/?[a-z][^>]*>/i', $response) === 1) {
            return true;
        }

        return false;
    }

    /**
     * Execute one embed loader request.
     *
     * @param string $url
     * @param string $api_key
     * @param mixed $payload
     * @return array
     */
    private static function execute_embed_request(string $url, string $api_key, $payload): array
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'accept: application/javascript',
                'X-Api-Key: ' . $api_key,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        $http_status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        curl_close($curl);

        return [
            'ok' => $response !== false && $http_status >= 200 && $http_status < 300,
            'response' => $response === false ? '' : (string)$response,
            'error' => $error,
            'http_status' => $http_status,
        ];
    }

    /**
     * Execute one embed loader GET request.
     *
     * GET /embed/{bot}/load does not require a session payload and is used as
     * a safe fallback when POST tracking/session bootstrap fails.
     *
     * @param string $url
     * @return array
     */
    private static function execute_embed_get_request(string $url): array
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'accept: application/javascript',
            ],
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        $http_status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        curl_close($curl);

        return [
            'ok' => $response !== false && $http_status >= 200 && $http_status < 300,
            'response' => $response === false ? '' : (string)$response,
            'error' => $error,
            'http_status' => $http_status,
        ];
    }

    private static function get_effective_config(): array
    {
        $block = get_config('block_ai_assistant');
        $local = get_config('local_cria');

        $get = static function ($obj, string $key): string {
            if (is_object($obj) && isset($obj->{$key}) && (string)$obj->{$key} !== '') {
                return (string)$obj->{$key};
            }
            return '';
        };

        return [
            'criabot_url' => $get($local, 'criabot_url') ?: $get($block, 'criabot_url'),
            'criadex_url' => $get($local, 'criadex_url') ?: $get($block, 'criadex_url'),
            'criadex_api_key' => $get($local, 'criadex_api_key') ?: $get($block, 'criadex_api_key'),

            'legacy_cria_url' => $get($block, 'cria_url'),
            'legacy_cria_token' => $get($block, 'cria_token'),

            'cria_embed_url' => $get($block, 'cria_embed_url'),
        ];
    }

    private static function request_json(
        string $method,
        string $url,
        array $headers = [],
        ?array $json_body = null,
        int $timeout_seconds = 30
    ): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl();
        $options = [
            'CURLOPT_TIMEOUT' => $timeout_seconds,
            'CURLOPT_RETURNTRANSFER' => true,
        ];

        $header_lines = [];
        foreach ($headers as $k => $v) {
            $header_lines[] = $k . ': ' . $v;
        }
        if (!empty($header_lines)) {
            $options['CURLOPT_HTTPHEADER'] = $header_lines;
        }

        $body = null;
        if ($json_body !== null) {
            $body = json_encode($json_body);
        }

        $raw = '';
        if ($method === 'GET') {
            $raw = (string)$curl->get($url, [], $options);
        } else if ($method === 'POST') {
            $raw = (string)$curl->post($url, $body ?? '', $options);
        } else if ($method === 'DELETE') {
            $options['CURLOPT_CUSTOMREQUEST'] = 'DELETE';
            $raw = (string)$curl->post($url, '', $options);
        } else if ($method === 'PATCH') {
            $options['CURLOPT_CUSTOMREQUEST'] = 'PATCH';
            $raw = (string)$curl->post($url, $body ?? '', $options);
        } else {
            $options['CURLOPT_CUSTOMREQUEST'] = $method;
            $raw = (string)$curl->post($url, $body ?? '', $options);
        }

        $curl_err = $curl->error ? (string)$curl->error : '';
        $info = $curl->get_info();
        $http_status = isset($info['http_code']) ? (int)$info['http_code'] : 0;

        if ($raw === false) {
            return [
                'ok' => false,
                'http_status' => 0,
                'error' => $curl_err ?: 'Request failed',
                'raw' => '',
                'json' => null,
            ];
        }

        $decoded = json_decode($raw, true);
        return [
            'ok' => $http_status >= 200 && $http_status < 300,
            'http_status' => $http_status,
            'error' => '',
            'raw' => $raw,
            'json' => is_array($decoded) ? $decoded : null,
        ];
    }

    /**
     * @param string $method
     * @param array $data
     * @return mixed
     */
    public static function exec($method, $data)
    {
        $cfg = self::get_effective_config();

        $has_criabot = $cfg['criabot_url'] !== '';
        $api_key = $cfg['criadex_api_key'];

        if ($has_criabot && $api_key !== '') {
            $criabot_url = rtrim($cfg['criabot_url'], '/');
            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-API-Key' => $api_key,
            ];

            switch ($method) {
                case 'cria_get_availability': {
                    $resp = self::request_json('GET', $criabot_url . '/health_check', ['Accept' => 'text/plain'], null, 10);
                    if ($resp['ok']) {
                        return json_encode(['exception' => 'success', 'message' => 'Available', 'errorcode' => '']);
                    }
                    return json_encode([[
                        'exception' => 'error',
                        'message' => 'Cria is currently unreachable. Please try again later.',
                        'errorcode' => (string)$resp['http_status']
                    ]]);
                }

                case 'cria_chat_start': {
                    $resp = self::request_json('POST', $criabot_url . '/bots/chats/start', $headers, [], 30);
                    return $resp['raw'];
                }

                case 'cria_chat_exists': {
                    $chat_id = trim((string)($data['chat_id'] ?? ''));
                    $resp = self::request_json('GET', $criabot_url . '/bots/chats/' . rawurlencode($chat_id) . '/exists', $headers, null, 15);
                    return $resp['raw'];
                }

                case 'cria_chat_send': {
                    $chat_id = trim((string)($data['chat_id'] ?? ''));
                    $body = [
                        'prompt' => (string)($data['prompt'] ?? ''),
                        'bot_name' => (string)($data['bot_name'] ?? ''),
                        'extra_bots' => [],
                        'disable_faq_fallback' => (bool)($data['disable_faq_fallback'] ?? false),
                    ];
                    $resp = self::request_json('POST', $criabot_url . '/bots/chats/' . rawurlencode($chat_id) . '/query', $headers, $body, 60);
                    return $resp['raw'];
                }

                case 'cria_chat_end': {
                    $chat_id = trim((string)($data['chat_id'] ?? ''));
                    $resp = self::request_json('DELETE', $criabot_url . '/bots/chats/' . rawurlencode($chat_id) . '/end', $headers, null, 30);
                    return $resp['raw'];
                }

                case 'cria_gradebook_start': {
                    $body = $data;
                    $resp = self::request_json('POST', $criabot_url . '/gradebook/sessions/start', $headers, $body, 120);
                    return $resp['raw'];
                }

                case 'cria_gradebook_chat': {
                    $session_id = trim((string)($data['session_id'] ?? ''));
                    $body = [
                        'prompt' => (string)($data['prompt'] ?? ''),
                    ];
                    $resp = self::request_json('POST', $criabot_url . '/gradebook/sessions/' . rawurlencode($session_id) . '/chat', $headers, $body, 60);
                    return $resp['raw'];
                }

                case 'cria_gradebook_proposal': {
                    $session_id = trim((string)($data['session_id'] ?? ''));
                    $resp = self::request_json('GET', $criabot_url . '/gradebook/sessions/' . rawurlencode($session_id) . '/proposal', $headers, null, 30);
                    return $resp['raw'];
                }

                case 'cria_gradebook_status': {
                    $session_id = trim((string)($data['session_id'] ?? ''));
                    $resp = self::request_json('GET', $criabot_url . '/gradebook/sessions/' . rawurlencode($session_id) . '/status', $headers, null, 30);
                    return $resp['raw'];
                }

                case 'cria_gradebook_accept': {
                    $session_id = trim((string)($data['session_id'] ?? ''));
                    $resp = self::request_json('POST', $criabot_url . '/gradebook/sessions/' . rawurlencode($session_id) . '/accept', $headers, [], 30);
                    return $resp['raw'];
                }

                case 'cria_gradebook_reset': {
                    $session_id = trim((string)($data['session_id'] ?? ''));
                    $body = [
                        'keep_extraction' => isset($data['keep_extraction']) ? (bool)$data['keep_extraction'] : true,
                    ];
                    $resp = self::request_json('POST', $criabot_url . '/gradebook/sessions/' . rawurlencode($session_id) . '/reset', $headers, $body, 30);
                    return $resp['raw'];
                }

                case 'cria_gradebook_delete': {
                    $session_id = trim((string)($data['session_id'] ?? ''));
                    $resp = self::request_json('DELETE', $criabot_url . '/gradebook/sessions/' . rawurlencode($session_id), $headers, null, 30);
                    return $resp['raw'];
                }

                case 'cria_gradebook_finalize': {
                    $session_id = trim((string)($data['session_id'] ?? ''));
                    $body = [
                        'confirmed_mapping' => $data['confirmed_mapping'] ?? [],
                        'create_categories' => isset($data['create_categories']) ? (bool)$data['create_categories'] : true,
                        'reorganize_resources' => isset($data['reorganize_resources']) ? (bool)$data['reorganize_resources'] : false,
                    ];
                    $resp = self::request_json('POST', $criabot_url . '/gradebook/sessions/' . rawurlencode($session_id) . '/finalize', $headers, $body, 60);
                    return $resp['raw'];
                }

                case 'cria_gradebook_sync': {
                    $session_id = trim((string)($data['session_id'] ?? ''));
                    $body = [
                        'course_activities' => $data['course_activities'] ?? [],
                    ];
                    if (array_key_exists('confirmed_mapping', $data)) {
                        $body['confirmed_mapping'] = $data['confirmed_mapping'];
                    }
                    if (array_key_exists('refresh_proposal_candidates', $data)) {
                        $body['refresh_proposal_candidates'] = (bool)$data['refresh_proposal_candidates'];
                    }
                    if (array_key_exists('moodle_resources', $data)) {
                        $body['moodle_resources'] = $data['moodle_resources'];
                    }
                    if (array_key_exists('syllabus_documents', $data)) {
                        $body['syllabus_documents'] = $data['syllabus_documents'];
                    }
                    $resp = self::request_json('POST', $criabot_url . '/gradebook/sessions/' . rawurlencode($session_id) . '/sync', $headers, $body, 120);
                    return $resp['raw'];
                }

                case 'cria_gradebook_upload': {
                    $session_id = trim((string)($data['session_id'] ?? ''));
                    $body = [
                        'filename' => (string)($data['filename'] ?? ''),
                        'filetype' => (string)($data['filetype'] ?? ''),
                        'base64' => (string)($data['base64'] ?? ''),
                    ];
                    $resp = self::request_json('POST', $criabot_url . '/gradebook/sessions/' . rawurlencode($session_id) . '/upload', $headers, $body, 120);
                    return $resp['raw'];
                }

                case 'cria_sync_publish': {
                    $resp = self::request_json('POST', $criabot_url . '/bots/manage/publish/sync', $headers, [], 60);
                    return $resp['raw'];
                }
            }
        }

        if ($cfg['legacy_cria_url'] !== '' && $cfg['legacy_cria_token'] !== '') {
            $url = rtrim($cfg['legacy_cria_url'], '/') . '/webservice/restful/server.php/' . $method;
            $token = $cfg['legacy_cria_token'];

            $resp = self::request_json(
                'POST',
                $url,
                [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => $token,
                ],
                $data,
                60
            );
            return $resp['raw'];
        }

        return json_encode([[
            'exception' => 'error',
            'errorcode' => '404: Site unavailable',
            'message' => 'Cria is not configured. Please set Cria URLs/API keys in plugin settings.',
        ]]);

    }

    public static function exec_embed($bot_id, $api_key, $payload)
    {
        $last_response = '';
        $candidates = self::get_embed_loader_candidates($bot_id);
        foreach ($candidates as $url) {
            $post_result = self::execute_embed_request($url, (string)$api_key, $payload);
            if ($post_result['ok']) {
                $last_response = $post_result['response'];
                if (!self::is_invalid_embed_loader_response($last_response)) {
                    return $last_response;
                }
            }

            // Fallback path: if POST fails (e.g. missing bot API key or strict
            // session tracking rules), still fetch the launcher script via GET.
            $get_result = self::execute_embed_get_request($url);
            if (!$get_result['ok']) {
                continue;
            }

            $last_response = $get_result['response'];
            if (!self::is_invalid_embed_loader_response($last_response)) {
                return $last_response;
            }
        }

        return self::is_invalid_embed_loader_response($last_response) ? '' : $last_response;
    }
}