<?php
namespace block_ai_assistant\task;

use block_ai_assistant\course_modules;
use block_ai_assistant\cria;

class course_module_training_status extends \core\task\scheduled_task
{
    public function get_name(): string
    {
        return get_string('course_module_training_status', 'block_ai_assistant');
    }

    public function execute()
    {
        global $CFG, $DB;
        raise_memory_limit(MEMORY_UNLIMITED);
        // Get all courses modules for the given course ID
        $sql = "Select
                    bacmf.id,
                    bacmf.cria_fileid,
                    bacm.courseid,
                    bacm.cmid,
                    bacmf.name
                From
                    {block_aia_course_modules} bacm Inner Join
                    {block_aia_course_mod_files} bacmf On bacmf.bacmid = bacm.id
                Where
                    bacmf.trained = 0";
        $files = $DB->get_records_sql($sql, []);
        // Loop through each file and check if it is trained
        foreach ($files as $file) {
            // Update training status
            if ($file->cria_fileid > 0) {
                $status = cria::get_content_training_status($file->cria_fileid);
                if ($status->training_status_id != 0) {
                    $DB->set_field(
                        'block_aia_course_mod_files',
                        'trained',
                        $status->training_status_id,
                        ['id' => $file->id]
                    );
                }
            }
        }

        // Loop through each module and check if it is trained
        raise_memory_limit(MEMORY_STANDARD);
    }

    public function get_run_if_component_disabled()
    {
        return true;
    }
}