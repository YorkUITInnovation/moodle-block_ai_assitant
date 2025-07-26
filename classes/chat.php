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
}