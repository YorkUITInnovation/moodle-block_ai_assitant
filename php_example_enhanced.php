<?php

class CriaEmbedClient {

    /**
     * Execute embed request with chat session persistence
     *
     * @param string $bot_id The bot identifier
     * @param string $api_key Your API key
     * @param array $payload Session data to send
     * @param string|null $chat_id Optional existing chat ID to reuse
     * @return array ['response' => string, 'chat_id' => string]
     */
    public static function exec_embed($bot_id, $api_key, $payload, $chat_id = null)
    {
        // Get the plugin configuration
        $config = get_config('block_ai_assistant');
        // Set the URL
        $url = $config->cria_embed_url . '/embed/' . $bot_id . '/load';

        // Add chat_id to payload if provided for session continuity
        if ($chat_id !== null) {
            $payload['chatId'] = $chat_id;
        }

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
            CURLOPT_POSTFIELDS => json_encode($payload), // Properly encode as JSON
            CURLOPT_HTTPHEADER => array(
                'accept: application/javascript',
                'X-Api-Key: ' . $api_key,
                'Content-Type: application/json'
            ),
            CURLOPT_HEADER => true, // Include headers in response to capture X-Chat-Id
        ));

        $response = curl_exec($curl);
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        curl_close($curl);

        // Split headers and body
        $headers = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        // Extract chat ID from headers
        $extracted_chat_id = self::extract_chat_id_from_headers($headers);

        return [
            'response' => $body,
            'chat_id' => $extracted_chat_id
        ];
    }

    /**
     * Extract X-Chat-Id from response headers
     *
     * @param string $headers Raw headers string
     * @return string|null The chat ID or null if not found
     */
    private static function extract_chat_id_from_headers($headers)
    {
        $lines = explode("\r\n", $headers);
        foreach ($lines as $line) {
            if (stripos($line, 'X-Chat-Id:') === 0) {
                return trim(substr($line, strlen('X-Chat-Id:')));
            }
        }
        return null;
    }

    /**
     * Store chat ID in session for later use
     *
     * @param string $bot_id Bot identifier
     * @param string $chat_id Chat ID to store
     */
    public static function store_chat_id($bot_id, $chat_id)
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['cria_chat_id_' . $bot_id] = $chat_id;
    }

    /**
     * Retrieve stored chat ID from session
     *
     * @param string $bot_id Bot identifier
     * @return string|null Stored chat ID or null if not found
     */
    public static function get_stored_chat_id($bot_id)
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['cria_chat_id_' . $bot_id]) ? $_SESSION['cria_chat_id_' . $bot_id] : null;
    }

    /**
     * Clear stored chat ID (useful for starting fresh conversations)
     *
     * @param string $bot_id Bot identifier
     */
    public static function clear_chat_id($bot_id)
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['cria_chat_id_' . $bot_id]);
    }

    /**
     * Execute embed with automatic session management
     * This is the recommended method to use
     *
     * @param string $bot_id The bot identifier
     * @param string $api_key Your API key
     * @param array $payload Session data to send
     * @param bool $start_new_session Whether to force a new chat session
     * @return array ['response' => string, 'chat_id' => string, 'is_new_session' => bool]
     */
    public static function exec_embed_with_session($bot_id, $api_key, $payload, $start_new_session = false)
    {
        // Get stored chat ID unless starting new session
        $stored_chat_id = $start_new_session ? null : self::get_stored_chat_id($bot_id);

        // Execute the embed request
        $result = self::exec_embed($bot_id, $api_key, $payload, $stored_chat_id);

        // Store the chat ID for future requests
        if ($result['chat_id']) {
            self::store_chat_id($bot_id, $result['chat_id']);
        }

        return [
            'response' => $result['response'],
            'chat_id' => $result['chat_id'],
            'is_new_session' => $stored_chat_id === null || $stored_chat_id !== $result['chat_id']
        ];
    }
}

// Example usage:

/**
 * Example 1: Simple usage with automatic session management
 */
function example_simple_usage() {
    $bot_id = '429';
    $api_key = 'your-api-key';
    $payload = [
        'user_id' => 12345,
        'user_name' => 'John Doe',
        'context' => 'mathematics_help'
    ];

    // This will automatically handle chat session persistence
    $result = CriaEmbedClient::exec_embed_with_session($bot_id, $api_key, $payload);

    echo "Chat ID: " . $result['chat_id'] . "\n";
    echo "Is new session: " . ($result['is_new_session'] ? 'Yes' : 'No') . "\n";
    echo "Response: " . $result['response'] . "\n";

    return $result['response'];
}

/**
 * Example 2: Manual session management for more control
 */
function example_manual_session_management() {
    $bot_id = '429';
    $api_key = 'your-api-key';

    // First request - no existing chat ID
    $payload1 = ['user_id' => 12345, 'question' => 'first question'];
    $result1 = CriaEmbedClient::exec_embed($bot_id, $api_key, $payload1);

    echo "First request - Chat ID: " . $result1['chat_id'] . "\n";

    // Second request - reuse the chat ID for conversation continuity
    $payload2 = ['user_id' => 12345, 'question' => 'follow up question'];
    $result2 = CriaEmbedClient::exec_embed($bot_id, $api_key, $payload2, $result1['chat_id']);

    echo "Second request - Chat ID: " . $result2['chat_id'] . "\n";
    echo "Same session: " . ($result1['chat_id'] === $result2['chat_id'] ? 'Yes' : 'No') . "\n";
}

/**
 * Example 3: Starting a fresh conversation
 */
function example_start_fresh() {
    $bot_id = '429';
    $api_key = 'your-api-key';
    $payload = ['user_id' => 12345, 'context' => 'new_topic'];

    // Force a new session (clears stored chat ID)
    $result = CriaEmbedClient::exec_embed_with_session($bot_id, $api_key, $payload, true);

    echo "Fresh session - Chat ID: " . $result['chat_id'] . "\n";

    return $result['response'];
}

/**
 * Replace your existing method with this enhanced version
 * This maintains backward compatibility while adding session persistence
 */
function enhanced_exec_embed($bot_id, $api_key, $payload) {
    // Use the new method with automatic session management
    $result = CriaEmbedClient::exec_embed_with_session($bot_id, $api_key, $payload);

    // Return just the response for backward compatibility
    return $result['response'];
}

?>
