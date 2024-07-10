<?php

require_once(__DIR__ . '/classes/cria.php');

class block_ai_assistant_observer
{
    public static function create_bot_instance_and_update_db(\core\event\base $event)
    {
        global $DB;

        $context = $event->get_context();
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        } else {
            $courseid = $context->instanceid;
            $course_record = $DB->get_record('block_aia_settings', array('courseid' => $courseid));

            if (!$course_record) {
                $record = new stdClass();
                $record->courseid = $courseid;
                $record->blockid = $event->contextinstanceid;
                $record->published = 0;
                $record->usermodified = $event->userid;
                $record->timecreated = $event->timecreated;
                $record->timemodified = $event->timecreated;
                $record->bot_name = cria::create_bot_instance();

                $DB->insert_record('block_aia_settings', $record);
            }
        }
    }
}

function block_ai_assistant_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array())
{
    global $DB;

    if ($context->contextlevel != CONTEXT_COURSE) {
        return false;
    }

    $fileAreas = array(
        'syllabus',
        'questions',
    );

    if (!in_array($filearea, $fileAreas)) {
        return false;
    }

    $itemid = array_shift($args);
    $filename = array_pop($args);
    $path = !count($args) ? '/' : '/' . implode('/', $args) . '/';

    $fs = get_file_storage();

    $file = $fs->get_file($context->id, 'block_ai_assistant', $filearea, $itemid, $path, $filename);

    // If the file does not exist.
    if (!$file) {
        send_file_not_found();
    }

    send_stored_file($file, 86400, 0, $forcedownload); // Options.
}
