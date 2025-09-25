<?php
$functions = array(
    'block_ai_assistant_delete_syllabus' => array(
        'classname' => 'block_ai_assistant_syllabus_ws',
        'methodname' => 'delete',
        'classpath' => 'blocks/ai_assistant/classes/external/syllabus_ws.php',
        'description' => 'This web service deletes the syllabus file from the course.',
        'type' => 'write',
        'capabilities' => '',
        'ajax' => true
    ),
    'block_ai_assistant_get_training_status' => array(
        'classname' => 'block_ai_assistant_syllabus_ws',
        'methodname' => 'training_status',
        'classpath' => 'blocks/ai_assistant/classes/external/syllabus_ws.php',
        'description' => 'Returns two values: training_status_id and training_status (HTML)',
        'type' => 'write',
        'capabilities' => '',
        'ajax' => true
    ),
    'block_ai_assistant_get_question_training_status' => array(
        'classname' => 'block_ai_assistant_question_ws',
        'methodname' => 'training_status',
        'classpath' => 'blocks/ai_assistant/classes/external/question_ws.php',
        'description' => 'Returns two values: training_status_id and training_status (HTML)',
        'type' => 'write',
        'capabilities' => '',
        'ajax' => true
    ),
    'block_ai_assistant_delete_question' => array(
        'classname' => 'block_ai_assistant_question_ws',
        'methodname' => 'delete',
        'classpath' => 'blocks/ai_assistant/classes/external/question_ws.php',
        'description' => 'This web service deletes the question record from the course question table.',
        'type' => 'write',
        'capabilities' => '',
        'ajax' => true
    ),
    'block_ai_assistant_delete_question_file' => array(
        'classname' => 'block_ai_assistant_question_ws',
        'methodname' => 'delete_file',
        'classpath' => 'blocks/ai_assistant/classes/external/question_ws.php',
        'description' => 'Delete file from question files',
        'type' => 'write',
        'capabilities' => '',
        'ajax' => true
    ),
    'block_ai_assistant_publish' => array(
        'classname' => 'block_ai_assistant_publish_ws',
        'methodname' => 'publish',
        'classpath' => 'blocks/ai_assistant/classes/external/publish_ws.php',
        'description' => 'Publish or unpublish the bot',
        'type' => 'write',
        'capabilities' => '',
        'ajax' => true
    ),
    'block_ai_assistant_publish_tutorials' => array(
        'classname' => 'block_ai_assistant_publish_ws',
        'methodname' => 'publish_tutorials',
        'classpath' => 'blocks/ai_assistant/classes/external/publish_ws.php',
        'description' => 'Make tutorials available, or not, to students',
        'type' => 'write',
        'capabilities' => '',
        'ajax' => true
    ),
    'blcok_block_ai_assistant_delete_autotest_question' => array(
        'classname' => 'block_ai_assistant_autotest_ws',
        'methodname' => 'delete',
        'classpath' => 'blocks/ai_assistant/classes/external/autotest_ws.php',
        'description' => 'Deletes a question from the autotest table',
        'type' => 'write',
        'capabilities' => '',
        'ajax' => true
    ),
    'block_ai_assistant_display_course_modules' => array(
        'classname' => 'block_ai_assistant_course_modules_ws',
        'methodname' => 'display_modules',
        'classpath' => 'blocks/ai_assistant/classes/external/course_modules_ws.php',
        'description' => 'Display all course modules',
        'type' => 'read',
        'capabilities' => '',
        'ajax' => true
    ),
    'block_ai_assistant_display_student_course_modules' => array(
        'classname' => 'block_ai_assistant_course_modules_ws',
        'methodname' => 'display_student_modules',
        'classpath' => 'blocks/ai_assistant/classes/external/course_modules_ws.php',
        'description' => 'Display all trained course modules for students',
        'type' => 'read',
        'capabilities' => '',
        'ajax' => true
    ),
    'block_ai_assistant_insert_course_modules' => array(
        'classname' => 'block_ai_assistant_course_modules_ws',
        'methodname' => 'insert',
        'classpath' => 'blocks/ai_assistant/classes/external/course_modules_ws.php',
        'description' => 'Inserts all course modules',
        'type' => 'write',
        'capabilities' => '',
        'ajax' => true
    ),
    'block_ai_assistant_delete_course_modules' => array(
        'classname' => 'block_ai_assistant_course_modules_ws',
        'methodname' => 'delete',
        'classpath' => 'blocks/ai_assistant/classes/external/course_modules_ws.php',
        'description' => 'Deletes a course modules',
        'type' => 'write',
        'capabilities' => '',
        'ajax' => true
    ),
    'block_ai_assistant_delete_tutorial' => array(
        'classname' => 'block_ai_assistant_tutorial_ws',
        'methodname' => 'delete',
        'classpath' => 'blocks/ai_assistant/classes/external/tutorials.php',
        'description' => 'Deletes a tutorial record from the tutorials table.',
        'type' => 'write',
        'capabilities' => '',
        'ajax' => true
    ),
    'block_ai_assistant_chat' => array(
        'classname' => 'block_ai_assistant_chat_ws',
        'methodname' => 'chat',
        'classpath' => 'blocks/ai_assistant/classes/external/chat.php',
        'description' => 'Send chat request to Cria. Returns AI reply.',
        'type' => 'read',
        'capabilities' => '',
        'ajax' => true
    ),
    'block_ai_assistant_chat_start' => array(
        'classname' => 'block_ai_assistant_chat_ws',
        'methodname' => 'start',
        'classpath' => 'blocks/ai_assistant/classes/external/chat.php',
        'description' => 'Start a new chat session with Cria. Returns chat ID.',
        'type' => 'read',
        'capabilities' => '',
        'ajax' => true
    ),
    'block_ai_assistant_chat_delete' => array(
        'classname' => 'block_ai_assistant_chat_ws',
        'methodname' => 'delete',
        'classpath' => 'blocks/ai_assistant/classes/external/chat.php',
        'description' => 'Delete a chat session with Cria.',
        'type' => 'read',
        'capabilities' => '',
        'ajax' => true
    ),
    'block_ai_assistant_set_policy_status' => array(
        'classname' => 'block_ai_assistant_ai_policy_ws',
        'methodname' => 'execute',
        'classpath' => 'blocks/ai_assistant/classes/external/ai_policy.php',
        'description' => 'Register user policy acceptance for AI assistant.',
        'type' => 'read',
        'capabilities' => '',
        'ajax' => true
    ),
);
