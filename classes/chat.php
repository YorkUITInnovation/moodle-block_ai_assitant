<?php

namespace block_ai_assistant;

class chat
{

    public static function get_saved_chats(int $courseid, int $userid): array
    {
        global $DB;

        // Fetch saved chats for the user in the specified course.
        $sql = "SELECT * 
                FROM 
                    {block_aia_tutorial_chats} 
                WHERE courseid = :courseid 
                  AND userid = :userid
                ORDER BY timecreated DESC";
        $results = $DB->get_records_sql($sql, [
            'courseid' => $courseid,
            'userid' => $userid
        ]);
        return array_values($results);
    }

    /**
     * Retrieves messages from the chat history and formats them for display.
     *
     * @param int $tutorial_chat_id The ID of the tutorial chat.
     * @return array An array containing the messages and the tutorial name.
     * @throws \dml_exception
     * **/
    public static function get_messages(int $tutorial_chat_id, $continue_chat = false): array
    {
        global $DB;

        $tutorial_name = $DB->get_field(
            'block_aia_tutorial_chats',
            'name',
            ['id' => $tutorial_chat_id]
        );
        $history_records = $DB->get_records(
            'block_aia_chat_history',
            ['tutorialchatid' => $tutorial_chat_id],
            'timecreated ASC'
        );
        $i = 0;
        foreach ($history_records as $history) {
            if ($i > 0) {

                // Process markdown HTML and convert any base64 links to <img> tags with proper data URI prefix
                $is_human = $history->is_human;
                $message = $history->message;

                $messages[] = [
                    'is_human' => $is_human,
                    'message' => $message,
                ];
            }
            $i++;
        }
        // If continue_chat is true, add a message to indicate that the chat can be continued.
        if ($continue_chat) {
            // Get chat chat id and bot name.
            $chat_id = $DB->get_field(
                'block_aia_tutorial_chats',
                'chatid',
                ['id' => $tutorial_chat_id]
            );
            $course_id = $DB->get_field(
                'block_aia_tutorial_chats',
                'courseid',
                ['id' => $tutorial_chat_id]
            );
            $bot_name = $DB->get_field(
                'block_aia_settings',
                'bot_name',
                ['courseid' => $course_id]
            );
            $message = chat::continue_chat($tutorial_chat_id, $chat_id, $bot_name);
            $messages[] = [
                'is_human' => false,
                'message' => $message,
            ];
        }

        return [
            'messages' => array_values($messages) ?? [],
            'tutorial_name' => $tutorial_name ?? 'AI Assistant Chat',
        ];
    }

    /**
     * @param $messages
     * @param $chat_id
     * @return void
     */
    public static function continue_chat($tutorial_chat_id, $chat_id, $bot_name)
    {
        global $DB, $USER;
        // Get chat history for the given tutorial chat ID.
        $history_records = $DB->get_records(
            'block_aia_chat_history',
            ['tutorialchatid' => $tutorial_chat_id],
            'timecreated ASC'
        );

        // Get the first 3 messages from the chat history.
        $content = 'Here are the first three messages from the chat that started earlier: ';
        $i = 0;
        foreach ($history_records as $history) {
            if ($i < 3) {
                $is_human = $history->is_human;
                $message = $history->message;

                // Append the message to the content.
                $content .= "\n" . ($is_human ? 'Human: ' : 'AI: ') . $message;
            }
            // Get the last 2 messages from the chat history.

            if ($i >= count($history_records) - 2) {
                $content .= "\n\nHere is one the messages to end the previous conversation: ";
                $content .= "\n" . ($is_human ? 'Human: ' : 'AI: ') . $message;
            }

            $i++;
        }
        $content .= "\n\nUse the above context as a summary so that you can continue the chat now. ";
        $content .= "Tell the student $USER->firstanme that you can now continue where you left off.";

        $response = cria::chat_send($chat_id, $content, $bot_name);
        // Insert the response into the chat history.
        $params = [
            'tutorialchatid' => $tutorial_chat_id,
            'message' => $response,
            'is_human' => 0, // AI response
            'timecreated' => time(),
            'userid' => $USER->id,
        ];
        $DB->insert_record('block_aia_chat_history', $params);

        return $response;
    }

        /**
         * Returns the curretn chatid if it exists, otherwise false.
         * @param $courseid
         * @param $tutorialid
         * @param $userid
         * @param $cmid
         * @return string|false
         * @throws \dml_exception
         */
        public
        static function get_chat_id($courseid, $tutorialid, $userid, $cmid)
        {
            global $DB;

            // Check if a chat already exists for the given parameters.
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

            return $chatid;
        }

    }
