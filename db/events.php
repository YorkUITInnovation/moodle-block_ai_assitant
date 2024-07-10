<?php

$observers = array(
    array(
        'eventname' => '\core\event\block_instance_created',
        'callback' => 'block_ai_assistant_observer::create_bot_instance_and_update_db',
    ),
);
