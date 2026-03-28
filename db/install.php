<?php

function xmldb_block_ai_assistant_install()
{
    global $DB;

    $tutor_params = [
        'courseid' => 1,
        'shortname' => 'tutor',
        'name' => get_string('tutorial_tutor_name', 'block_ai_assistant'),
        'description' => get_string('tutorial_tutor_description', 'block_ai_assistant'),
        'prompt' => get_string('tutorial_tutor_prompt', 'block_ai_assistant'),
        'enabled' => 1,
        'timecreated' => time(),
        'timemodified' => time(),
    ];

    $DB->insert_record('block_aia_tutorials', (object)$tutor_params);
    $quiz_params = [
        'courseid' => 1,
        'shortname' => 'quiz',
        'name' => get_string('tutorial_quiz_name', 'block_ai_assistant'),
        'description' => get_string('tutorial_quiz_description', 'block_ai_assistant'),
        'prompt' => get_string('tutorial_quiz_prompt', 'block_ai_assistant'),
        'enabled' => 1,
        'timecreated' => time(),
        'timemodified' => time(),
    ];
    $DB->insert_record('block_aia_tutorials', (object)$quiz_params);
}