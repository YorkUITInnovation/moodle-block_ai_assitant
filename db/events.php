<?php
$observers = [
    [
        'eventname' => '\core\event\course_module_updated',
        'callback' => 'ai_assistant_course_module_updated',
        'includefile' => '/blocks/ai_assistant/eventslib.php'
    ]
];