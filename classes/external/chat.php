<?php

require_once($CFG->libdir . "/externallib.php");
require_once("$CFG->dirroot/config.php");

use block_ai_assistant\cria;
use block_ai_assistant\chat;
use block_ai_assistant\course_modules;

class block_ai_assistant_chat_ws extends external_api
{
    /**
     * Clean and normalize module/title text for user-facing chat headers.
     * Removes noisy source tags and duplicate repeated labels.
     */
    private static function clean_chat_display_name(string $name): string
    {
        $value = trim($name);
        if ($value === '') {
            return '';
        }

        // Remove source suffixes like [doc:page_66_...html].
        $value = preg_replace('/\s*\[doc:[^\]]+\]\s*/i', ' ', $value);
        $value = preg_replace('/\s+/', ' ', trim($value));

        // Collapse duplicated label patterns around ":" (A: A).
        $parts = array_values(array_filter(array_map('trim', explode(':', $value)), function($part) {
            return $part !== '';
        }));
        if (count($parts) >= 2) {
            $first = core_text::strtolower($parts[0]);
            $second = core_text::strtolower($parts[1]);
            if ($first === $second) {
                $value = $parts[0];
            }
        }

        return $value;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function chat_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
                'tutorialid' => new external_value(PARAM_INT, 'Tutorial id', VALUE_REQUIRED),
                'tutorialchatid' => new external_value(PARAM_INT, 'Tutorial chat id', VALUE_REQUIRED),
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
    public static function chat(int $courseid, int $tutorialid, $tutorialchatid, $bot_name, $prompt, $chatid): string
    {
        global $DB, $USER;
        self::validate_parameters(
            self::chat_parameters(),
            [
                'courseid' => $courseid,
                'tutorialid' => $tutorialid,
                'tutorialchatid' => $tutorialchatid,
                'botname' => $bot_name,
                'prompt' => $prompt,
                'chatid' => $chatid
            ]
        );

        // Validate context
        $context = \context_course::instance($courseid);
        self::validate_context($context);

        // Enforce ownership: a user may only post to their own tutorial chat.
        $owner = $DB->get_field('block_aia_tutorial_chats', 'userid', ['id' => $tutorialchatid]);
        if (!$owner || (int)$owner !== (int)$USER->id) {
            throw new \moodle_exception('nopermission', 'error');
        }

        // Insert the new prompt into the chat history.
        $params = [
            'tutorialchatid' => $tutorialchatid,
            'userid' => $USER->id,
            'is_human' => true,
            'message' => $prompt,
            'timecreated' => time(),
        ];
        $DB->insert_record('block_aia_chat_history', $params);

        // Tutorial/quiz personas are free-form conversations, not Moodle-support
        // Q&A - FAQ fallback on a low-confidence short reply (e.g. a quiz answer
        // like "c") would otherwise inject unrelated FAQ content and derail them.
        $is_tutorial_chat = !empty($tutorialid);

        // Get chat response
        $response = cria::chat_send($chatid, $prompt, $bot_name, $is_tutorial_chat);

        // now insert response into the chat history.
        $params = [
            'tutorialchatid' => $tutorialchatid,
            'userid' => $USER->id,
            'is_human' => false,
            'message' => $response,
            'timecreated' => time(),
        ];
        $DB->insert_record('block_aia_chat_history', $params);
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
                'name' => new external_value(PARAM_RAW, 'Name of selected course module file', VALUE_REQUIRED),
                'userid' => new external_value(PARAM_INT, 'User ID', VALUE_REQUIRED),
                'chatid' => new external_value(PARAM_TEXT, 'Chat ID', VALUE_DEFAULT, ''),
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
        $name = self::clean_chat_display_name($name);
        // Curretnly not using as a webservice or ajax call, so we can skip the webservice validation.
//        self::validate_parameters(
//            self::start_parameters(),
//            [
//                'courseid' => $courseid,
//                'tutorialid' => $tutorialid,
//                'cmid' => $cmid,
//                'name' => $name,
//                'userid' => $userid,
//                'chatid' => $chatid
//            ]
//        );

        // Validate context
        $context = \context_course::instance($courseid);
        self::validate_context($context);

        // Get bot name.
        $bot_name = $DB->get_field('block_aia_settings', 'bot_name', ['courseid' => $courseid]);
        if (empty($chatid)) {
            // Lets find out if a chat session already exists.
            $chatid = chat::get_chat_id(
                $courseid,
                $tutorialid,
                $userid,
                $cmid
            );
        }

        // Start chat session
        if (!$chatid) {
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
            $decoded_messages = json_decode((string)$params->messages, true);
            $messages = is_array($decoded_messages) ? $decoded_messages : [];
        } else {
            // Get chat history from table block_aia_tutorial_chats.
            $chat_exists = $DB->get_record(
                'block_aia_tutorial_chats',
                [
                    'chatid' => $chatid,
                    'courseid' => $courseid,
                    'userid' => $userid,
                ]
            );
            if (empty($name) && !empty($chat_exists->name)) {
                $name = self::clean_chat_display_name((string)$chat_exists->name);
            }


            // Store the original chatid to update the assets table later.
            $original_chatid = $chatid;
            // Only regenerate the "continue" summary when the backend session was
            // actually recreated; reopening a live session should not call the LLM again.
            $session_recreated = false;
            // Now let's check if the chat_id still exists on cria
            $cria_chat_exists = cria::chat_exists($original_chatid);
            if (!$cria_chat_exists->exists) {
                // Start a new chat session.
                $chatid = cria::chat_start();
                // Now update the chatid in the database.
                $DB->update_record(
                    'block_aia_tutorial_chats',
                    [
                        'id' => $chat_exists->id,
                        'chatid' => $chatid,
                        'timemodified' => time()
                    ]
                );
                // Train the bot to continue the chat session.
                chat::continue_chat($tutorialid, $chatid, $bot_name);
                $session_recreated = true;
            }

            // Get messages and tutorial name. Only surface the continue summary
            // when the session was just recreated, to avoid repeating it on reopen.
            $data = chat::get_messages(
                $chat_exists->id, $session_recreated
            );

            $tutorial_name = $data['tutorial_name'];
            $messages = $data['messages'];

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


    public static function start_details(): external_single_structure
    {
        $fields = array(
            'chat_id' => new external_value(PARAM_RAW, 'Cria chat id', VALUE_REQUIRED),
            'tutorial_name' => new external_value(PARAM_RAW, 'Tutorial title', VALUE_REQUIRED),
            'name' => new external_value(PARAM_RAW, 'Selected module/file name', VALUE_REQUIRED),
            'bot_name' => new external_value(PARAM_RAW, 'Cria bot name', VALUE_REQUIRED),
            'messages' => new external_value(PARAM_RAW, 'JSON message', VALUE_REQUIRED),
        );
        return new external_single_structure($fields);
    }


    /**
     * Returns method result value
     * @return external_single_structure
     */
    public static function start_returns(): external_multiple_structure
    {
        return new external_multiple_structure(self::start_details());
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
        global $DB, $USER;

        self::validate_parameters(
            self::delete_parameters(),
            [
                'chatid' => $chatid,
            ]
        );

        // Validate context
        $context = \context_system::instance();
        self::validate_context($context);

        // Verify ownership: only the owner can delete their own chat
        $chat_record = $DB->get_record(
            'block_aia_tutorial_chats',
            ['chatid' => $chatid]
        );
        if (!$chat_record || $chat_record->userid != $USER->id) {
            throw new \moodle_exception('nopermission', 'error');
        }

        // Delete chat session.
        cria::chat_end($chatid);

        if ($DB->delete_records('block_aia_tutorial_chats', ['id' => $chat_record->id])) {
            // Delete assets related to this chat session.
            $DB->delete_records('block_aia_chat_history', ['tutorialchatid' => $chat_record->id]);
            return true;
        }

        return false;
    }

    /**
     * Bulk delete multiple chats
     */
    public static function bulk_delete_chats(array $chatids): array
    {
        global $DB, $USER;

        self::validate_parameters(
            self::bulk_delete_chats_parameters(),
            ['chatids' => $chatids]
        );

        // Validate context
        $context = \context_system::instance();
        self::validate_context($context);

        $deleted = 0;
        foreach ($chatids as $chatid) {
            // Verify ownership: only the owner can delete their own chat
            $chat_record = $DB->get_record('block_aia_tutorial_chats', ['chatid' => $chatid]);
            if (!$chat_record || $chat_record->userid != $USER->id) {
                continue; // Skip if user doesn't own this chat
            }

            cria::chat_end($chatid);
            if ($DB->delete_records('block_aia_tutorial_chats', ['id' => $chat_record->id])) {
                $DB->delete_records('block_aia_chat_history', ['tutorialchatid' => $chat_record->id]);
                $deleted++;
            }
        }

        return ['deleted' => $deleted, 'total' => count($chatids)];
    }

    /**
     * Parameters for bulk delete
     */
    public static function bulk_delete_chats_parameters(): external_function_parameters
    {
        return new external_function_parameters([
            'chatids' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Chat ID to delete')
            )
        ]);
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
     * Returns bulk delete result
     */
    public static function bulk_delete_chats_returns(): external_single_structure
    {
        return new external_single_structure([
            'deleted' => new external_value(PARAM_INT, 'Number of chats deleted'),
            'total' => new external_value(PARAM_INT, 'Total chats requested to delete')
        ]);
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
    /**
     * Generate a descriptive chat title combining tutorial type and resource name
     * Examples: "Quiz Coach · Chapter 3" or "Study Tutor · Photosynthesis"
     */
    private static function generate_chat_title(string $tutorial_name, string $resource_name): string
    {
        $tutorial_name = trim($tutorial_name);
        $resource_name = trim($resource_name);

        if (empty($resource_name)) {
            return $tutorial_name;
        }
        if (empty($tutorial_name)) {
            return $resource_name;
        }

        return $tutorial_name . ' · ' . $resource_name;
    }

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
        $name = self::clean_chat_display_name($name);
        // Get tutorial type.
        $tutorial = $DB->get_record('block_aia_tutorials', ['id' => $tutorialid], '*', MUST_EXIST);

        $chat_id = cria::chat_start();

        // Use the resource/module name directly as the topic instead of making an extra LLM call
        $topic_title = $name;
        $initial_prompt = str_replace(
            '[topic]',
            $topic_title,
            $tutorial->prompt
        );

        // Generate a better chat title: "Tutorial Name · Resource Name" instead of just "Tutorial Name"
        $chat_title = self::generate_chat_title($tutorial->name, $name);
        // Add the chat ID to the database.
        $tutorialchatid = $DB->insert_record('block_aia_tutorial_chats', [
            'courseid' => $courseid,
            'tutorialid' => $tutorialid,
            'chatid' => $chat_id,
            'userid' => $userid,
            'cmid' => $cmid,
            'name' => $chat_title,
            'timecreated' => time(),
        ]);

        // Add the message (prompt) to the chat_history table.
        $params = [
            'tutorialchatid' => $tutorialchatid,
            'userid' => $userid,
            'is_human' => false,
            'message' => $initial_prompt,
            'timecreated' => time(),
        ];

        $DB->insert_record('block_aia_chat_history', $params);
        // Get message from Cria
        $message = cria::chat_send($chat_id, $initial_prompt, $bot_name, true);
        // INsert new message to chat history.
        $new_message_params = [
            'tutorialchatid' => $tutorialchatid,
            'userid' => $USER->id,
            'is_human' => false,
            'message' => $message,
            'timecreated' => time(),
        ];
        $DB->insert_record('block_aia_chat_history', $new_message_params);
        $messages = [
            [
                'is_human' => false,
                'message' => $message,
            ]
        ];

        $params = new \stdClass();
        $params->chat_id = $chat_id;
        $params->tutorial_name = $tutorial->name;
        $params->messages = json_encode($messages);

        return $params;

    }


}
