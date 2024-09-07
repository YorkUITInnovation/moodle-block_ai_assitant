<?php

namespace block_ai_assistant;

class webservice
{
    /**
     * @param string $method
     * @param array $data
     * @return mixed
     */
    public static function exec($method, $data)
    {
        // Get the plugin configuration
        $config = get_config('block_ai_assistant');
        // Set the URL
        $url = $config->cria_url . '/webservice/restful/server.php/' . $method;
        // Set the authorization token (replace with your actual token)
        $token = $config->cria_token;

// Initialize cURL session
        $ch = curl_init();

// Set cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            "Accept: application/json",
            "Authorization: " . $token
        ));

// Execute the cURL request
        $response = curl_exec($ch);

// Check for errors
        if (curl_errno($ch)) {
            return curl_error($ch);
        } else {
            // Print the response
            return $response;
        }

// Close cURL session
        curl_close($ch);

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