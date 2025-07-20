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
     *
     * @return string[]
     */
    public static function supported_mime_types() {
        return [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/pdf',
            'text/plain',
            'text/html',
            'text/rtf',
            'text/markdown',
            'application/vnd.oasis.opendocument.text',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'text/csv',
            'audio/mpeg',
            'audio/mp3',
            'audio/x-mpeg-3',
            'audio/x-mp3',
            'audio/x-wav',
            'audio/wav',
            'audio/x-m4a',
            'audio/m4a',
            'video/mp4',
        ];
    }
}