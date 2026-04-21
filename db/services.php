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
    'block_ai_assistant_gradebook_start' => array(
        'classname' => 'block_ai_assistant_gradebook_ws',
        'methodname' => 'start',
        'classpath' => 'blocks/ai_assistant/classes/external/gradebook_ws.php',
        'description' => 'Start a gradebook workflow session with Criabot.',
        'type' => 'write',
        'capabilities' => '',
        'ajax' => true
    ),
    'block_ai_assistant_gradebook_chat' => array(
        'classname' => 'block_ai_assistant_gradebook_ws',
        'methodname' => 'chat',
        'classpath' => 'blocks/ai_assistant/classes/external/gradebook_ws.php',
        'description' => 'Send a gradebook chat prompt to advance the workflow session.',
        'type' => 'write',
        'capabilities' => '',
        'ajax' => true
    ),
    'block_ai_assistant_gradebook_proposal' => array(
        'classname' => 'block_ai_assistant_gradebook_ws',
        'methodname' => 'proposal',
        'classpath' => 'blocks/ai_assistant/classes/external/gradebook_ws.php',
        'description' => 'Read the latest gradebook proposal for the active session.',
        'type' => 'read',
        'capabilities' => '',
        'ajax' => true
    ),
    'block_ai_assistant_gradebook_status' => array(
        'classname' => 'block_ai_assistant_gradebook_ws',
        'methodname' => 'status',
        'classpath' => 'blocks/ai_assistant/classes/external/gradebook_ws.php',
        'description' => 'Read gradebook session status and payload for resume.',
        'type' => 'read',
        'capabilities' => '',
        'ajax' => true
    ),
    'block_ai_assistant_gradebook_accept' => array(
        'classname' => 'block_ai_assistant_gradebook_ws',
        'methodname' => 'accept',
        'classpath' => 'blocks/ai_assistant/classes/external/gradebook_ws.php',
        'description' => 'Accept the gradebook proposal and generate content mapping.',
        'type' => 'write',
        'capabilities' => '',
        'ajax' => true
    ),
    'block_ai_assistant_gradebook_finalize' => array(
        'classname' => 'block_ai_assistant_gradebook_ws',
        'methodname' => 'finalize',
        'classpath' => 'blocks/ai_assistant/classes/external/gradebook_ws.php',
        'description' => 'Finalize gradebook mapping and mark ready for Moodle sync.',
        'type' => 'write',
        'capabilities' => '',
        'ajax' => true
    ),
    'block_ai_assistant_gradebook_get_state' => array(
        'classname' => 'block_ai_assistant_gradebook_ws',
        'methodname' => 'get_state',
        'classpath' => 'blocks/ai_assistant/classes/external/gradebook_ws.php',
        'description' => 'Load persisted gradebook UI state (session id, chat history, finalize result) for current user and course.',
        'type' => 'read',
        'capabilities' => '',
        'ajax' => true
    ),
    'block_ai_assistant_gradebook_save_state' => array(
        'classname' => 'block_ai_assistant_gradebook_ws',
        'methodname' => 'save_state',
        'classpath' => 'blocks/ai_assistant/classes/external/gradebook_ws.php',
        'description' => 'Upsert or clear persisted gradebook UI state for the current user and course.',
        'type' => 'write',
        'capabilities' => '',
        'ajax' => true
    ),
);
