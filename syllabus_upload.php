<?php

/**
 * This file is part of Cria.
 * Cria is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * Cria is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with Cria. If not, see <https://www.gnu.org/licenses/>.
 *
 * @package    local_cria
 * @author     Patrick Thibaudeau
 * @copyright  2024 onwards York University (https://yorku.ca)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once("../../config.php");

require_once($CFG->dirroot . "/blocks/ai_assistant/classes/forms/syllabus_upload.php");


global $CFG, $OUTPUT, $USER, $PAGE, $DB;

// Get course id
$courseid = required_param('courseid', PARAM_INT);

$context = CONTEXT_COURSE::instance($courseid);

require_login(1, false);


// Set page title
if ($id != 0) {
    $page_title = get_string('edit_content', 'block_ai_assistant');
} else {
    $page_title = get_string('add_content', 'block_ai_assistant');
}

$formdata = new stdClass();
$formdata->courseid = $courseid;

// Create form
$mform = new \block_ai_assistant\syllabus_upload_form(null, array('formdata' => $formdata));

if ($mform->is_cancelled()) {
    //Handle form cancel operation, if cancel button is present on form
    redirect($CFG->wwwroot . '/course/view.php?id=' . $data->courseid);

} else if ($data = $mform->get_data()) {


     // Process uploaded file
     $fs = get_file_storage();
     $draftitemid = $data->syllabus_upload;


     file_save_draft_area_files(
         $draftitemid,
         $context->id,
         'block_ai_assistant',
         'syllabus',
         $courseid,
         array('subdirs' => 0, 'maxfiles' => 1)
     );
 
     // Generate URL for the uploaded file
     $file = $fs->get_file(
         $context->id,
         'block_ai_assistant',
         'syllabus',
         $courseid,
         '/',
         $mform->get_new_filename('syllabus_upload')
     );
 
     if ($file) {
        
        $url = moodle_url::make_pluginfile_url(
        $file->get_contextid(),
        $file->get_component(),
        $file->get_filearea(),
        $file->get_itemid(),
        $file->get_filepath(),
        $file->get_filename(),
        false // Whether to force download or not.
    );

    // Redirect or display the URL as needed.
    redirect($url);
         // Redirect with success message
         redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid, get_string('file_uploaded_successfully', 'block_ai_assistant'), null, \core\output\notification::NOTIFY_SUCCESS);
        
     } else {
         // Redirect with error message
         redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid, get_string('file_upload_failed', 'block_ai_assistant'), null, \core\output\notification::NOTIFY_ERROR);
     }
 } else {
     // Show form
     $mform->set_data($formdata);
 }
 
 $PAGE->set_context($context);
 $PAGE->set_url(new moodle_url('/blocks/ai_assistant/syllabus_upload.php', ['courseid' => $courseid]));
 $PAGE->set_title(get_string('syllabus', 'block_ai_assistant'));
 $PAGE->set_heading(get_string('syllabus', 'block_ai_assistant'));
 
 echo $OUTPUT->header();
 $mform->display();
 echo $OUTPUT->footer();
 
 ?>
    





















<!-- 

    // Get local_cria config

    // If id, then simple upload the file using file picker
    if ($data->id) {

//        $data->path = $path;
//        $data->file_content = $mform->get_file_content('importedFile');
        // Redirect to content page

        $content = $mform->get_file_content('userfile');
        $name = $mform->get_new_filename('userfile');
        $success = $mform->save_file('userfile', $fullpath, $override);


        $url = moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename(),
            true                     // Do not force download of the file.
        );


        



      
        // if ($success) {
        //     redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid, get_string('file_uploaded_successfully', 'block_ai_assistant'), null, \core\output\notification::NOTIFY_SUCCESS);
        // } else {
        //     redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid, get_string('file_upload_failed', 'block_ai_assistant'), null, \core\output\notification::NOTIFY_ERROR);
        // }








        redirect($CFG->wwwroot . '/course/view.php?id=' . $data->courseid);
    } else {

        print_object($data);


        // Get draft_area_files
       // $draft_area_files = file_get_all_files_in_draftarea($data->importedFile, '/');


        // Save all files to the server
        // Then add each individual file to the database
//        file_save_draft_area_files(
//        // The $data->attachments property contains the itemid of the draft file area.
//            $data->importedFile,
//
//            // The combination of contextid / component / filearea / itemid
//            // form the virtual bucket that file are stored in.
//            $context->id,
//            'local_cria',
//            'content',
//            $data->intent_id,
//
//            [
//                'subdirs' => 0,
//                'maxbytes' => $CFG->maxbytes,
//                'maxfiles' => -1,
//            ]
//        );


        }


    // Redirect to content page
//redirect($CFG->wwwroot . '/course/view.php?id=' . $data->courseid);
} else {
    // Show form
    $mform->set_data($mform);
}


$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/ai_assistant/syllabus_upload.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('syllabus', 'block_ai_assistant'));
$PAGE->set_heading(get_string('syllabus', 'block_ai_assistant'));


echo $OUTPUT->header();
//**********************
//*** DISPLAY HEADER ***
//
$mform->display();
//**********************
//*** DISPLAY FOOTER ***
//**********************
echo $OUTPUT->footer();
?> -->