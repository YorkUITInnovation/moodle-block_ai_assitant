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
);

