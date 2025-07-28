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
    public static function get_messages(int $tutorial_chat_id): array
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
        foreach($history_records as $history) {
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

        return [
            'messages' => array_values($messages) ?? [],
            'tutorial_name' => $tutorial_name ?? 'AI Assistant Chat',
        ];
    }

    /**
     * @param $messages
     * @return void
     */
    public static function continue_chat($tutorial_chat_id)
    {
        global $DB, $USER;
      // Get chat history for the given tutorial chat ID.
        $history_records = $DB->get_records(
            'block_aia_chat_history',
            ['tutorialchatid' => $tutorial_chat_id],
            'timecreated ASC'
        );






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
    public static function get_chat_id($courseid, $tutorialid, $userid, $cmid): string|false
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