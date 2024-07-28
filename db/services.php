<?php
$functions = array(
    'ai_assistant_delete_syllabus' => array(
        'classname' => 'block_ai_assistant_syllabus_ws',
        'methodname' => 'delete',
        'classpath' => 'blocks/ai_assistant/classes/external/syllabus_ws.php',
        'description' => 'This web service deletes the syllabus file from the course.',
        'type' => 'write',
        'capabilities' => '',
        'ajax' => true
    ),
    'ai_assistant_delete_question' => array(
        'classname' => 'block_ai_assistant_question_ws',
        'methodname' => 'delete',
        'classpath' => 'blocks/ai_assistant/classes/external/question_ws.php',
        'description' => 'This web service deletes the question record from the course question table.',
        'type' => 'write',
        'capabilities' => '',
        'ajax' => true
    ),
    'ai_assistant_publish' => array(
        'classname' => 'block_ai_assistant_publish_ws',
        'methodname' => 'publish',
        'classpath' => 'blocks/ai_assistant/classes/external/publish_ws.php',
        'description' => 'Publish or unpublish the bot',
        'type' => 'write',
        'capabilities' => '',
        'ajax' => true
    ),
    'ai_assistant_delete_autotest_question' => array(
        'classname' => 'block_ai_assistant_autotest_ws',
        'methodname' => 'delete',
        'classpath' => 'blocks/ai_assistant/classes/external/autotest_ws.php',
        'description' => 'Deletes a question from the autotest table',
        'type' => 'write',
        'capabilities' => '',
        'ajax' => true
    ),
);
