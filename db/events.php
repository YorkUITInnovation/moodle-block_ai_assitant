<?php
$observers = [
    [
        'eventname' => '\core\event\course_module_updated',
        'callback' => 'ai_assistant_course_module_updated',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\mod_forum\event\discussion_created',
        'callback' => 'ai_assistant_forum_discussion_created',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\mod_forum\event\discussion_deleted',
        'callback' => 'ai_assistant_forum_discussion_deleted',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\mod_forum\event\discussion_updated',
        'callback' => 'ai_assistant_forum_discussion_updated',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\mod_forum\event\post_created',
        'callback' => 'ai_assistant_forum_post_created',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\mod_forum\event\post_deleted',
        'callback' => 'ai_assistant_forum_post_deleted',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\mod_forum\event\post_updated',
        'callback' => 'ai_assistant_forum_post_updated',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\mod_folder\event\folder_updated',
        'callback' => 'ai_assistant_folder_updated',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\mod_book\event\chapter_created',
        'callback' => 'ai_assistant_book_chapter_created',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\mod_book\event\chapter_deleted',
        'callback' => 'ai_assistant_book_chapter_deleted',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ],
    [
        'eventname' => '\mod_book\event\chapter_updated',
        'callback' => 'ai_assistant_book_chapter_updated',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ]
];