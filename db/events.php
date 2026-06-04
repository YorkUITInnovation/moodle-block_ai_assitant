<?php
$observers = [
    [
        'eventname' => '\core\event\course_viewed',
        'callback' => 'block_ai_assistant_course_viewed',
        'includefile' => '/blocks/ai_assistant/eventslib.php',
    ],
    [
        'eventname' => '\core\event\course_module_updated',
        'callback' => 'block_ai_assistant_course_module_updated',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\core\event\course_module_deleted',
        'callback' => 'block_ai_assistant_course_module_deleted',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\core\event\grade_item_deleted',
        'callback' => 'block_ai_assistant_gradebook_item_deleted',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\core\event\grade_category_deleted',
        'callback' => 'block_ai_assistant_gradebook_category_deleted',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\mod_forum\event\discussion_created',
        'callback' => 'block_ai_assistant_forum_retrain',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\mod_forum\event\discussion_deleted',
        'callback' => 'block_ai_assistant_forum_retrain',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\mod_forum\event\discussion_updated',
        'callback' => 'block_ai_assistant_forum_retrain',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\mod_forum\event\post_created',
        'callback' => 'block_ai_assistant_forum_retrain',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\mod_forum\event\post_deleted',
        'callback' => 'block_ai_assistant_forum_retrain',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\mod_forum\event\post_updated',
        'callback' => 'block_ai_assistant_forum_retrain',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\mod_folder\event\folder_updated',
        'callback' => 'block_ai_assistant_folder_retrain',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\mod_book\event\chapter_created',
        'callback' => 'block_ai_assistant_book_retrain',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\mod_book\event\chapter_deleted',
        'callback' => 'block_ai_assistant_book_retrain',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\mod_book\event\chapter_updated',
        'callback' => 'block_ai_assistant_book_retrain',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\mod_glossary\event\category_created',
        'callback' => 'block_ai_assistant_glossary_retrain',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\mod_glossary\event\category_deleted',
        'callback' => 'block_ai_assistant_glossary_retrain',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\mod_glossary\event\category_updated',
        'callback' => 'block_ai_assistant_glossary_retrain',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\mod_glossary\event\entry_created',
        'callback' => 'block_ai_assistant_glossary_retrain',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\mod_glossary\event\entry_approved',
        'callback' => 'block_ai_assistant_glossary_retrain',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\mod_glossary\event\entry_deleted',
        'callback' => 'block_ai_assistant_glossary_retrain',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\mod_glossary\event\entry_updated',
        'callback' => 'block_ai_assistant_glossary_retrain',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
];