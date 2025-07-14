<?php

use block_ai_assistant\cria;
use block_ai_assistant\course_modules;

function block_ai_assistant_course_module_updated($event)
{
    global $DB;

    $data = (object)$event->get_data();
    // Get module from objectid
    $mod = course_modules::get_module_from_cmid($data->objectid);

    // Get module from aia_course_modules table
    $aia_module = $DB->get_record(
        'block_aia_course_modules',
        [
            'courseid' => $data->courseid,
            'cmid' => $data->objectid
        ]
    );

    // Only perform if the module is in the aia_course_modules table
    if ($aia_module) {
        switch ($mod[1]->modname) {
            case 'forum':
                // Only print if it's the news forum
                if ($mod[0]->type == 'news') {
                    $mod_url = new \moodle_url('/mod/forum/view.php', ['id' => $mod[0]->id]);
                    $content = course_modules::get_forum_content($mod[0]->id, $mod[0]->name, $mod[1]->id, $mod_url->out(false, true));
                    $module_content = course_modules::set_module_content(
                        $mod[0]->id,
                        $mod[0]->name,
                        $mod[0]->intro,
                        $content,
                        $mod[1]->modname,
                        $mod_url->out(false, true)
                    );
                }
                break;
            case 'page':
                $mod_url = new moodle_url('/mod/page/view.php', ['id' => $mod[0]->id]);
                $module_content = course_modules::set_module_content(
                    $mod[0]->id,
                    $mod[0]->name,
                    $mod[0]->intro,
                    $mod[0]->content,
                    $mod[1]->modname,
                    $mod_url->out(false)
                );
                break;
            case 'label':
                $module_content = course_modules::set_module_content(
                    $mod[0]->id,
                    $mod[0]->name,
                    $mod[0]->intro,
                    '',
                    $mod[1]->modname,
                    ''
                );
                break;
            case 'book':
                $mod_url = new \moodle_url('/mod/book/view.php', ['id' => $mod[0]->id]);
                // Must get book content
                $content = course_modules::get_book_content($mod[0]->id, $mod[0]->name, $mod_url->out(false, true));
                $module_content = course_modules::set_module_content(
                    $mod[0]->id,
                    $mod[0]->name,
                    $mod[0]->intro,
                    $content,
                    $mod[1]->modname,
                    $mod_url->out(false)
                );
                break;
            case 'resource': // File
                $mod_url = new \moodle_url('/mod/resource/view.php', ['id' => $mod[0]->id]);
                $module_content = course_modules::get_files_from_resource(
                    $mod[1]->id,
                    $mod[0]->id,
                    $mod_url->out(false),
                );
                break;
            case 'folder':
                $folder_files = course_modules::get_folder_files(
                    $mod[1]->id,
                    $mod[0]->id,
                    $mod[0]->name,
                    $mod[0]->intro
                );
                $module_content = $folder_files->content;
                break;
            case 'glossary':
                $mod_url = new \moodle_url('/mod/glossary/view.php', ['id' => $mod[0]->id]);
                $content = course_modules::get_glossary_entries($mod[1]->id, $mod[0]->id, $mod[0]->name);
                $module_content = course_modules::set_module_content(
                    $mod[0]->id,
                    $mod[0]->name,
                    $mod[0]->intro,
                    $content->content,
                    $mod[1]->modname,
                    $mod_url->out(false)
                );
                break;
        }
        // Update cria content
        return block_ai_assistant_update_cria_content($module_content, $aia_module, $data);
    }

}

/**
 * Callback function for course module deleted event
 * @param $event
 * @return void
 * @throws dml_exception
 */
function block_ai_assistant_course_module_deleted($event) {
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
        // Delete the content in CRIA
        cria::delete_content_from_bot($aia_module->cria_fileid);
        // Delete the record in aia_course_modules
        $DB->delete_records('block_aia_course_modules', ['id' => $aia_module->id]);
    }
}

/**
 * Callback function for forum post created event
 */
function block_ai_assistant_forum_retrain($event) {
    global $DB;
    $data = (object)$event->get_data();
    // Get module from aia_course_modules table
    $aia_module = $DB->get_record(
        'block_aia_course_modules',
        [
            'courseid' => $data->courseid,
            'cmid' => $data->contextinstanceid
        ]
    );

    if (!$aia_module) {
        // If the module is not in the aia_course_modules table, we do not proceed
        return;
    }

    $mod = course_modules::get_module_from_cmid($data->contextinstanceid);

    // Only print if it's the news forum
    if ($mod[0]->type == 'news') {
        $mod_url = new \moodle_url('/mod/forum/view.php', ['id' => $mod[0]->id]);
        $content = course_modules::get_forum_content($mod[0]->id, $mod[0]->name, $mod[1]->id, $mod_url->out(false));
        $module_content = course_modules::set_module_content(
            $mod[0]->id,
            $mod[0]->name,
            $mod[0]->intro,
            $content,
            $mod[1]->modname,
            $mod_url->out(false)
        );
    }
    // Update cria content
    return block_ai_assistant_update_cria_content($module_content, $aia_module, $data);
}

/**
 * Callback function for folder updated event
 */
function block_ai_assistant_folder_retrain($event) {
    global $DB;
    $data = (object)$event->get_data();
    // Get module from aia_course_modules table
    $aia_module = $DB->get_record(
        'block_aia_course_modules',
        [
            'courseid' => $data->courseid,
            'cmid' => $data->contextinstanceid
        ]
    );
    if (!$aia_module) {
        // If the module is not in the aia_course_modules table, we do not proceed
        return;
    }
    // Get module from objectid
    $mod = course_modules::get_module_from_cmid($data->contextinstanceid);
    // Get folder files
    $folder_files = course_modules::get_folder_files(
        $mod[1]->id,
        $mod[0]->id,
        $mod[0]->name,
        $mod[0]->intro
    );
    $module_content = course_modules::set_module_content(
        $mod[0]->id,
        $mod[0]->name,
        $mod[0]->intro,
        $folder_files->content,
        $mod[1]->modname,
        $folder_files->url
    );
    // Update cria content
    return block_ai_assistant_update_cria_content($module_content, $aia_module, $data);

}

/**
 * Callback function for book chapter created event
 */
function block_ai_assistant_book_retrain($event) {
    global $DB;
    $data = (object)$event->get_data();
    // Get book module from objectid
    $mod = course_modules::get_module_from_cmid($data->contextinstanceid);
    // Get module from aia_course_modules table
    $aia_module = $DB->get_record(
        'block_aia_course_modules',
        [
            'courseid' => $data->courseid,
            'cmid' => $data->contextinstanceid
        ]
    );
    if (!$aia_module) {
        // If the module is not in the aia_course_modules table, we do not proceed
        return;
    }
    // Get book content
    $mod_url = new \moodle_url('/mod/book/view.php', ['id' => $mod[0]->id]);
    $content = course_modules::get_book_content($mod[0]->id, $mod[0]->name, $mod_url->out(false));
    $module_content = course_modules::set_module_content(
        $mod[0]->id,
        $mod[0]->name,
        $mod[0]->intro,
        $content,
        $mod[1]->modname,
        $mod_url->out(false)
    );
    // Update cria content
    return block_ai_assistant_update_cria_content($module_content, $aia_module, $data);

}

function block_ai_assistan_glossary_retrain($event) {
    global $DB;
    $data = (object)$event->get_data();
    // Get module from aia_course_modules table
    $aia_module = $DB->get_record(
        'block_aia_course_modules',
        [
            'courseid' => $data->courseid,
            'cmid' => $data->contextinstanceid
        ]
    );
    if (!$aia_module) {
        // If the module is not in the aia_course_modules table, we do not proceed
        return;
    }
    // Get module from objectid
    $mod = course_modules::get_module_from_cmid($data->contextinstanceid);
    // Get glossary entries
    $mod_url = new \moodle_url('/mod/glossary/view.php', ['id' => $mod[0]->id]);
    $content = course_modules::get_glossary_entries($mod[1]->id, $mod[0]->id, $mod[0]->name);
    $module_content = course_modules::set_module_content(
        $mod[0]->id,
        $mod[0]->name,
        $mod[0]->intro,
        $content->content,
        $mod[1]->modname,
        $mod_url->out(false)
    );
    // Update cria content
    return block_ai_assistant_update_cria_content($module_content, $aia_module, $data);
}

/**
 * Update CRIA content for a course module.
 * @param $module_content
 * @param $aia_module
 * @param $data
 * @return true|void
 * @throws dml_exception
 */
function block_ai_assistant_update_cria_content($module_content, $aia_module, $data) {
    global $DB;
    // Delete the existing content in CRIA
    $delete_result = cria::delete_content_from_bot($aia_module->cria_fileid);
    if ($delete_result != '"200"') {
        // Log error if deletion failed
        \core\notification::error("Failed to delete content from CRIA for module ID: {$aia_module->id}");
        return;
    }

    // Recreate the content in CRIA
    $create_result = cria::upload_content_to_bot(
        $data->courseid,
        $module_content->file_name,
        $module_content->content,
        'GENERIC'
    );

    if ($create_result <= 0) {
        // Log error if creation failed
        \core\notification::error("Failed to create content in CRIA for module ID: {$aia_module->id}");
        return;
    }

    // Update cria_fileid
    $DB->set_field(
        'block_aia_course_modules',
        'cria_fileid',
        $create_result,
        ['id' => $aia_module->id]
    );// Update the record in aia_course_modules

    // Update modtimemodified in course_modules
    $DB->set_field(
        'block_aia_course_modules',
        'modtimemodified',
        $data->timecreated,
        ['id' => $data->objectid]
    );

    return true;
}
