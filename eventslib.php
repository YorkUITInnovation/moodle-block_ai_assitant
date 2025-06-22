<?php

use block_ai_assistant\cria;
use block_ai_assistant\course_modules;

function ai_assistant_course_module_updated($event)
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
                    $content = course_modules::get_forum_content($mod[0]->id, $mod[0]->name);
                    $module_content = course_modules::set_module_content(
                        $mod[0]->id,
                        $mod[0]->name,
                        $mod[0]->intro,
                        $content,
                        $mod[1]->modname
                    );
                }
                break;
            case 'page':
                $module_content = course_modules::set_module_content(
                    $mod[0]->id,
                    $mod[0]->name,
                    $mod[0]->intro,
                    $mod[0]->content,
                    $mod[1]->modname
                );
                break;
            case 'label':
                $module_content = course_modules::set_module_content(
                    $mod[0]->id,
                    $mod[0]->name,
                    $mod[0]->intro,
                    '',
                    $mod[1]->modname
                );
                break;
            case 'book':
                // Must get book content
                $content = course_modules::get_book_content($mod[0]->id);
                $module_content = course_modules::set_module_content(
                    $mod[0]->id,
                    $mod[0]->name,
                    $mod[0]->intro,
                    $content,
                    $mod[1]->modname
                );
                break;
            case 'resource': // File
                $module_content = course_modules::get_files_from_resource(
                    $mod[1]->id,
                    $mod[0]->id
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
                $content = course_modules::get_glossary_entries($mod[1]->id, $mod[0]->id, $mod[0]->name);
                $module_content = course_modules::set_module_content(
                    $mod[0]->id,
                    $mod[0]->name,
                    $mod[0]->intro,
                    $content->content,
                    $mod[1]->modname
                );
                break;
        }


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
    }

}