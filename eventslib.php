<?php

use block_ai_assistant\cria;
use block_ai_assistant\course_modules;
use block_ai_assistant\course_module_training;

function block_ai_assistant_course_module_updated($event)
{
    global $DB;

    $data = (object)$event->get_data();
    $TRAINING = new course_module_training($data->objectid, true);

    // Only perform if the module is in the aia_course_modules table
    switch ($TRAINING->get_module_type()) {
        case 'forum':
            $TRAINING->forum();
            break;
        case 'page':
            $TRAINING->page();
            break;
        case 'label':
            $TRAINING->label();
            break;
        case 'book':
            $TRAINING->book();
            break;
        case 'resource': // File
            $TRAINING->resource();
            break;
        case 'folder':
            $TRAINING->folder();
            break;
        case 'glossary':
            $TRAINING->glossary();
            break;
        case 'tab':
            $TRAINING->tab();
            break;
    }
}

/**
 * Callback function for course module deleted event
 * @param $event
 * @return void
 * @throws dml_exception
 */
function block_ai_assistant_course_module_deleted($event)
{
    global $DB;

    $data = (object)$event->get_data();
    // Get module from aia_course_modules table
    $aia_module = $DB->get_record(
        'block_aia_course_modules',
        [
            'courseid' => $data->courseid,
            'cmid' => $data->objectid
        ]
    );

    if ($aia_module) {
        course_modules::delete_course_module_files($aia_module->id);
    }
}

/**
 * Callback function for forum post created event
 */
function block_ai_assistant_forum_retrain($event)
{

    $data = (object)$event->get_data();
    $TRAINING = new course_module_training($data->objectid, true);
    $TRAINING->forum();
}

/**
 * Callback function for folder updated event
 */
function block_ai_assistant_folder_retrain($event)
{
    $data = (object)$event->get_data();
    $TRAINING = new course_module_training($data->objectid, true);
    $TRAINING->folder();
}

/**
 * Callback function for book chapter created event
 */
function block_ai_assistant_book_retrain($event)
{
    $data = (object)$event->get_data();
    $TRAINING = new course_module_training($data->objectid, true);
    $TRAINING->book();
}

function block_ai_assistant_glossary_retrain($event)
{
    $data = (object)$event->get_data();
    $TRAINING = new course_module_training($data->objectid, true);
    $TRAINING->glossary();
}
