<?php

namespace block_ai_assistant;

class markitdown
{
    /**
     * Convert a file to markdown using the markitdown API
     *
     * @param string $filepath Path to the file to convert
     * @param bool $create_pages Whether to create pages (default: false)
     * @return array|false Returns the API response as array or false on failure
     */
    public static function execute($filepath, $create_pages = false) {
        // Get plugin config
        $config = get_config('block_ai_assistant');
        // Check if file exists
        if (!file_exists($filepath)) {
            return false;
        }

        // Get file info
        $filename = basename($filepath);
        $mime_type = mime_content_type($filepath);

        // Initialize cURL
        $curl = curl_init();

        // Prepare the file for upload
        $file = new \CURLFile($filepath, $mime_type, $filename);

        // Set cURL options
        curl_setopt_array($curl, [
            CURLOPT_URL => $config->markitdown_api_url . '/upload',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => [
                'file' => $file,
                'create_pages' => $create_pages ? 'true' : 'false'
            ],
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'Authorization: Bearer ' . $config->markitdown_api_key,
                'Content-Type: multipart/form-data'
            ],
        ]);

        // Execute the request
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // Check for cURL errors
        if (curl_errno($curl)) {
            curl_close($curl);
            return false;
        }

        curl_close($curl);

        // Check HTTP response code
        if ($http_code !== 200) {
            return false;
        }

        // Decode and return the JSON response
        $decoded_response = json_decode($response, true);
        return $decoded_response !== null ? $decoded_response : false;
    }

    /**
     * Get supported MIME types based on plugin settings.
     * Reads from the 'allowed_file_types' config stored as a comma-separated string
     * (set via admin_setting_configtextarea in settings.php).
     *
     * @return string[]
     */
    public static function supported_mime_types(): array {
        $allowed_types = get_config('block_ai_assistant', 'allowed_file_types');

        // If no settings are configured, return empty array - no files will be processed
        if (empty($allowed_types)) {
            return [];
        }

        // Split on comma or newline (configtextarea may use either), trim whitespace, remove empty entries
        $types_array = preg_split('/[\s,]+/', $allowed_types, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_map('trim', $types_array));
    }
}
