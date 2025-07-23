<?php

require_once($CFG->libdir . "/externallib.php");
require_once("$CFG->dirroot/config.php");

use block_ai_assistant\cria;

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
}