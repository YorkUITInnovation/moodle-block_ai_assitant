<?php
namespace block_ai_assistant\task;

use block_ai_assistant\course_modules;

class upload_assessment_dates extends \core\task\scheduled_task
{
    public function get_name(): string
    {
        return get_string('upload_assessment_dates', 'block_ai_assistant');
    }

    public function execute()
    {
        global $CFG, $DB;
        raise_memory_limit(MEMORY_UNLIMITED);
        $block_instances = $DB->get_records('block_aia_settings');
        foreach ($block_instances as $block_instance) {
            mtrace('Uploading for course id: ' . $block_instance->courseid);
            course_modules::upload_course_dates($block_instance->courseid);
        }
        raise_memory_limit(MEMORY_STANDARD);
    }

    public function get_run_if_component_disabled()
    {
        return true;
    }
}