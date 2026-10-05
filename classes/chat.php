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

        // Fetch all needed tutorial_chats data in one query instead of multiple get_field() calls
        $tutorial_chat = $DB->get_record('block_aia_tutorial_chats', ['id' => $tutorial_chat_id], 'name, chatid, courseid');
        $tutorial_name = $tutorial_chat ? $tutorial_chat->name : 'AI Assistant Chat';

        $history_records = $DB->get_records(
            'block_aia_chat_history',
            ['tutorialchatid' => $tutorial_chat_id],
            'timecreated ASC'
        );
        $messages = [];
        $i = 0;
        foreach ($history_records as $history) {
            if ($i > 0) {
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
        if ($continue_chat && $tutorial_chat) {
            $bot_name = $DB->get_field(
                'block_aia_settings',
                'bot_name',
                ['courseid' => $tutorial_chat->courseid]
            );
            $message = chat::continue_chat($tutorial_chat_id, $tutorial_chat->chatid, $bot_name);
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
        $content .= "Tell the student that you can now continue where you left off.";

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
        static function get_chat_id($courseid, $tutorialid, $userid, $cmid): string|false
        {
            global $DB;

            // Select the newest matching chat row deterministically.
            // We intentionally avoid get_field/get_record here because historical
            // duplicates can exist and would otherwise trigger a debugging exception.
            $records = $DB->get_records(
                'block_aia_tutorial_chats',
                [
                    'courseid' => $courseid,
                    'tutorialid' => $tutorialid,
                    'userid' => $userid,
                    'cmid' => $cmid
                ],
                'id DESC',
                'id, chatid',
                0,
                1
            );

            if (empty($records)) {
                return false;
            }

            $record = reset($records);
            return (string)$record->chatid;
        }

    }