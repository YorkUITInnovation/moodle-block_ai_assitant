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
     * @param int $courseid
     * @param $chat_type
     * @param $prompt
     * @return string
     * @throws \core_external\restricted_context_exception
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws Exception
     */
    public static function chat(int $courseid, $bot_name, $prompt, $chatid): string
    {
        self::validate_parameters(
            self::chat_parameters(),
            [
                'courseid' => $courseid,
                'botname' => $bot_name,
                'prompt' => $prompt,
                'chatid' => $chatid
            ]
        );

        // Validate context
        $context = \context_course::instance($courseid);
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
                'tutorialid' => new external_value(PARAM_INT, 'The Tutorial ID', VALUE_REQUIRED),
                'cmid' => new external_value(PARAM_INT, 'Moodle Course Module ID', VALUE_REQUIRED),
                'name' => new external_value(PARAM_RAW, 'Name of selected course moduel file', VALUE_REQUIRED),
                'userid' => new external_value(PARAM_INT, 'User ID', VALUE_REQUIRED),
                'chatid' => new external_value(PARAM_TEXT, 'Chat ID', VALUE_OPTIONAL, ''),
            )
        );
    }

    /**
     * Start a new chat session
     * @param int $courseid
     * @param int $tutorialid
     * @param int $cmid
     * @param int $userid
     * @param string $chatid
     * @return string
     * @throws \core_external\restricted_context_exception
     * @throws coding_exception
     * @throws invalid_parameter_exception
     */
    public static function start(
        int    $courseid,
        int    $tutorialid,
        int    $cmid,
        string $name,
        int    $userid,
        string $chatid = ''
    ): array
    {
        global $CFG, $DB;
        self::validate_parameters(
            self::start_parameters(),
            [
                'courseid' => $courseid,
                'tutorialid' => $tutorialid,
                'cmid' => $cmid,
                'name' => $name,
                'userid' => $userid,
                'chatid' => $chatid
            ]
        );

        // Validate context
        $context = \context_course::instance($courseid);
        self::validate_context($context);

        // Get bot name.
        $bot_name = $DB->get_field('block_aia_settings', 'bot_name', ['courseid' => $courseid]);
        if (empty($chatid)) {
            // Lets find out if a chat session already exists.
            $chatid = $DB->get_field(
                'block_aia_tutorial_chats',
                'chatid',
                [
                    'courseid' => $courseid,
                    'tutorialid' => $tutorialid,
                    'userid' => $userid,
                    'cmid' => $cmid
                ]
            );
        }

        // Start chat session
        if (empty($chatid)) {
            // Start a new chat session
            $params = self::start_cria_session(
                $courseid,
                $cmid,
                $name,
                $tutorialid,
                $userid,
                $bot_name
            );

            $chatid = $params->chat_id;
            $tutorial_name = $params->tutorial_name;
            $messages = json_decode($params->messages)[0];
        } else {
            // Get chat history.
            $full_chat_history = cria::chat_history($chatid);
            $chat_history = json_decode($full_chat_history->history);
            $messages = [];
            if (isset($chat_history->history)) {
                $tutorial_name = $DB->get_field(
                    'block_aia_tutorial_chats',
                    'name',
                    ['chatid' => $chatid]
                );
                $history = $chat_history->history;
                for ($i = 0; $i < count($history); $i++) {
                    if ($i > 3) {
                        if ($history[$i]->role == 'user') {
                            $is_human = true;
                        } else {
                            $is_human = false;
                        }
                        $messages[] = [
                            'is_human' => $is_human,
                            'message' => $history[$i]->blocks[0]->text,
                        ];
                    }
                }
            } else {
                // There is a chat id but an error was thrown. So delete the chat id and start a new session.
                cria::chat_end($chatid);
                $DB->delete_records(
                    'block_aia_tutorial_chats',
                    [
                        'courseid' => $courseid,
                        'tutorialid' => $tutorialid,
                        'userid' => $userid,
                        'cmid' => $cmid
                    ]
                );
                // If no history, start a new session.
                $params = self::start_cria_session(
                    $courseid,
                    $cmid,
                    $name,
                    $tutorialid,
                    $userid,
                    $bot_name
                );
                $chatid = $params->chat_id;
                $tutorial_name = $params->tutorial_name;
                $messages = json_decode($params->messages)[0];
            }
        }

        // Prepare data to return.
        $data[] = [
            'chat_id' => $chatid,
            'tutorial_name' => $tutorial_name,
            'name' => $name,
            'bot_name' => $bot_name,
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
     * @return external_function_parameters
     */
    public static function delete_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'chatid' => new external_value(PARAM_TEXT, 'Chat id', VALUE_REQUIRED),
            )
        );
    }

    /**
     * @param string $chatid
     * @return bool
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws restricted_context_exception
     */
    public static function delete(string $chatid): bool
    {
        global $DB;

        self::validate_parameters(
            self::delete_parameters(),
            [
                'chatid' => $chatid,
            ]
        );

        // Validate context
        $context = \context_system::instance();
        self::validate_context($context);

        // Delete chat session.
        cria::chat_end($chatid);
        if ($DB->delete_records('block_aia_tutorial_chats', ['chatid' => $chatid])) {
            // If the chat session was deleted, return true.
            return true;
        }

        return false;
    }
    /**
     * Returns method result value
     * @return external_value
     */
    public static function delete_returns(): external_value
    {
        return new external_value(PARAM_BOOL, 'True if deleted');
    }

    /**
     * @param int $courseid
     * @param int $tutorialid
     * @param int $userid
     * @param string $chat_header
     * @param string $bot_name
     * @param string $initial_prompt
     * @return stdClass
     * @throws dml_exception
     */
    private static function start_cria_session(
        int    $courseid,
        int    $cmid,
        string $name,
        int    $tutorialid,
        int    $userid,
        string $bot_name
    ): \stdClass
    {
        global $DB, $USER;
        // Get tutorial type.
        $tutorial = $DB->get_record('block_aia_tutorials', ['id' => $tutorialid], '*', MUST_EXIST);

        $chat_id = cria::chat_start();

        $curent_lang = current_language();
        $topic_prompt = 'Give me oly a topic title for ' . $name . ' in ' . $curent_lang . ' language. Nothing else!';
        $topic_title = cria::chat_send($chat_id, $topic_prompt, $bot_name);
        $initial_prompt = str_replace(
            '[topic]',
            $topic_title,
            $tutorial->prompt
        );
        // Add the chat ID to the database.
        $DB->insert_record('block_aia_tutorial_chats', [
            'courseid' => $courseid,
            'tutorialid' => $tutorialid,
            'chatid' => $chat_id,
            'userid' => $userid,
            'cmid' => $cmid,
            'name' => $tutorial->name . ': ' . $name,
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
        $params->tutorial_name = $tutorial->name;
        $params->messages = json_encode($messages);

        return $params;

    }


}