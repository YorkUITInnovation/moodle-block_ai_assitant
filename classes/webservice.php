<?php

namespace block_ai_assistant;

class webservice
{
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
            'criaparse_url' => $get($local, 'criaparse_url') ?: $get($block, 'criaparse_url'),
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
        } else {
            $raw = (string)$curl->request($method, $url, $body ?? '', $options);
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
                    ];
                    $resp = self::request_json('POST', $criabot_url . '/bots/chats/' . rawurlencode($chat_id) . '/query', $headers, $body, 60);
                    return $resp['raw'];
                }

                case 'cria_chat_end': {
                    $chat_id = trim((string)($data['chat_id'] ?? ''));
                    $resp = self::request_json('DELETE', $criabot_url . '/bots/chats/' . rawurlencode($chat_id) . '/end', $headers, null, 30);
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
        // Get the plugin configuration
        $config = get_config('block_ai_assistant');
        // Set the URL
        $url = $config->cria_embed_url . '/embed/' . $bot_id . '/load';

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => array(
                'accept: application/javascript',
                'X-Api-Key: ' . $api_key,
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return $response;

    }
}