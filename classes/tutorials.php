<?php

namespace block_ai_assistant;

class tutorials
{
    public static function get_tutorials($courseid): array
    {
        global $DB;

        // Fetch tutorials from the database for the given course ID.
        $sql = "SELECT * FROM {block_aia_tutorials} WHERE "
            . "(courseid = ? OR courseid = 1) and enabled = 1 ORDER BY name ASC";
        $params = [$courseid];
        $tutorials = $DB->get_records_sql($sql, $params);
        // Reset the keys to be sequential.
        $tutorials = array_values($tutorials);
        // Return the tutorials as an array.
        return $tutorials ?: [];

    }
}