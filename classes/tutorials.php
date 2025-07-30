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

    /**
     * Creates default tutorials for a course.
     * @param $courseid
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function create_default_tutorials($courseid):void
    {
        global $DB;

        $tutor_params = [
            'courseid' => $courseid,
            'name' => get_string('tutorial_tutor_name', 'block_ai_assistant'),
            'description' => 'This is a personal tutor that can help students with their studies of specific content.',
            'prompt' => get_string('tutorial_tutor_prompt', 'block_ai_assistant'),
            'enabled' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        $DB->insert_record('block_aia_tutorials', (object)$tutor_params);
        $quiz_params = [
            'courseid' => $courseid,
            'name' => get_string('tutorial_quiz_name', 'block_ai_assistant'),
            'description' => 'This is a quiz assistant that can helps student with mock quizzes based on the content they selected.',
            'prompt' => get_string('tutorial_quiz_prompt', 'block_ai_assistant'),
            'enabled' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $DB->insert_record('block_aia_tutorials', (object)$quiz_params);
    }
}