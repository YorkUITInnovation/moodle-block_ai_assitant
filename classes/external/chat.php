<?php

require_once($CFG->libdir . "/externallib.php");
require_once("$CFG->dirroot/config.php");

use block_ai_assistant\cria;
use block_ai_assistant\course_modules;

class block_ai_assistant_chat_ws extends external_api
{
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function chat_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
                'botname' => new external_value(PARAM_RAW, 'Cria bot name', VALUE_REQUIRED),
                'prompt' => new external_value(PARAM_TEXT, 'User prompt', VALUE_REQUIRED),
                'chatid' => new external_value(PARAM_RAW, 'Chat ID', VALUE_REQUIRED),
            )
        );
    }

    /**
     * Chat with AI
     * @param int $course_id
     * @param $chat_type
     * @param $prompt
     * @return string
     * @throws \core_external\restricted_context_exception
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws Exception
     */
    public static function chat(int $course_id, $bot_name, $prompt, $chatid): string
    {
        self::validate_parameters(
            self::chat_parameters(),
            [
                'courseid' => $course_id,
                'botname' => $bot_name,
                'prompt' => $prompt,
                'chatid' => $chatid
            ]
        );

        // Validate context
        $context = \context_course::instance($course_id);
        self::validate_context($context);

        // Get chat response
        $response = cria::chat_send($chatid, $prompt, $bot_name);

        return $response;
    }

    /**
     * Returns method result value
     * @return external_value|external_description
     */
    public static function chat_returns(): external_value|external_description
    {
        return new external_value(PARAM_RAW, 'Response from AI');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function start_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
                'tutorialid' => new external_value(PARAM_RAW, 'The Tutorial ID', VALUE_REQUIRED),
                'cmid' => new external_value(PARAM_INT, 'Course Module ID', VALUE_REQUIRED),
                'userid' => new external_value(PARAM_INT, 'User ID', VALUE_REQUIRED),
                'chatid' => new external_value(PARAM_RAW, 'Chat ID', VALUE_OPTIONAL, ''),
            )
        );
    }

    /**
     * Start a new chat session
     * @param int $course_id
     * @param string $tutorial_id
     * @param int $cmid
     * @param int $userid
     * @param string $chatid
     * @return string
     * @throws \core_external\restricted_context_exception
     * @throws coding_exception
     * @throws invalid_parameter_exception
     */
    public static function start(
        int    $course_id,
        string $tutorial_id,
        int    $cmid,
        int    $userid,
        string $chatid = ''
    ): array
    {
        global $CFG, $DB;
        self::validate_parameters(
            self::start_parameters(),
            [
                'courseid' => $course_id,
                'tutorialid' => $tutorial_id,
                'cmid' => $cmid,
                'userid' => $userid,
                'chatid' => $chatid
            ]
        );

        // Validate context
        $context = \context_course::instance($course_id);
        self::validate_context($context);

        // Get bot name.
        $bot_name = $DB->get_field('block_aia_settings', 'bot_name', ['courseid' => $course_id]);

        // Start chat session
        if (empty($chatid)) {
            // Start a new chat session
            $params = self::start_cria_session(
                $course_id,
                $cmid,
                $tutorial_id,
                $userid,
                $bot_name
            );

            $chatid = $params->chat_id;
            $messages = $params->messages;
        } else {
            // Get chat history.
            $chat_history = cria::chat_history($chatid);
            $messages = [];
            if (isset($chat_history->history)) {
                $history = $chat_history->history;
                for ($i = 0; $i < count($history); $i++) {
                    if ($i > 1) {
                        if ($history[$i]['role'] == 'user') {
                            $is_human = true;
                        } else {
                            $is_human = false;
                        }
                        $messages[] = [
                            'is_human' => $is_human,
                            'message' => $history[$i]['blocks'][0]['text'],
                        ];
                    }
                }
            } else {
                // If no history, start a new session.
                $params = self::start_cria_session(
                    $course_id,
                    $cmid,
                    $tutorial_id,
                    $userid,
                    $bot_name
                );
                $chatid = $params->chat_id;
                $messages = $params->messages;
            }
        }

        // Prepare data to return.
        $data[] = [
            'chat_id' =>$chatid,
            'messages' => json_encode($messages)
        ];

        return $data;
    }


    public static function start_details()
    {
        $fields = array(
            'chat_id' => new external_value(PARAM_RAW, 'Cria chat id', VALUE_REQUIRED),
            'messages' => new external_value(PARAM_RAW, 'JSON message', VALUE_REQUIRED),
        );
        return new external_single_structure($fields);
    }

    /**
     * Returns method result value
     * @return external_single_structure
     */
    public static function start_returns(): external_single_structure
    {
        return new external_value(PARAM_RAW, 'JSON Formated data');
    }


    /**
     * @param int $course_id
     * @param int $tutorial_id
     * @param int $user_id
     * @param string $chat_header
     * @param string $bot_name
     * @param string $initial_prompt
     * @return stdClass
     * @throws dml_exception
     */
    private static function start_cria_session(
        int    $course_id,
        int    $cmid,
        int    $tutorial_id,
        int    $user_id,
        string $bot_name
    ): \stdClass
    {
        global $DB, $USER;
        // Get tutorial type.
        $tutorial = $DB->get_record('block_aia_tutorials', ['id' => $tutorial_id], '*', MUST_EXIST);
        $mod = course_modules::get_module_from_cmid($cmid);
        // Set initial prompt
        $initial_prompt = str_replace('[topic]', $mod[0]->name, $tutorial->prompt);

        $chat_id = cria::chat_start();
        // Add the chat ID to the database.
        $DB->insert_record('block_aia_tutorial_chats', [
            'courseid' => $course_id,
            'tutorialid' => $tutorial_id,
            'chatid' => $chat_id,
            'userid' => $user_id,
            'name' => $tutorial->name . ': ' . $mod[0]->name,
            'timecreated' => time(),
        ]);

        $messages = [
            [
                'is_human' => false,
                'message' => cria::chat_send($chat_id, $initial_prompt, $bot_name),
            ]
        ];

        $params = new \stdClass();
        $params->chat_id = $chat_id;
        $params->messages = json_encode($messages);

        return $params;

    }
}