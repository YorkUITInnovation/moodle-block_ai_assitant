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

use block_ai_assistant\cria;

require_once($CFG->dirroot . "/blocks/ai_assistant/classes/forms/configure_settings.php");


global $CFG, $OUTPUT, $USER, $PAGE, $DB;

// Get course id
$courseid = required_param('courseid', PARAM_INT);

$context = CONTEXT_COURSE::instance($courseid);
$config = get_config('block_ai_assistant');


require_login(1, false);

// Get record form block_aia_settings table
if (!$formdata = $DB->get_record('block_aia_settings', array('courseid' => $courseid))) {
    $course = $DB->get_record('course', array('id' => $courseid));
    $formdata = new stdClass();
    $formdata->courseid = $courseid;
    $formdata->welcome_message = $config->welcome_message;
    $formdata->no_context_message = $config->no_context_message;
    $formdata->subtitle = $course->fullname;
}


// Create form
$mform = new \block_ai_assistant\configure_settings(null, array('formdata' => $formdata));
if ($mform->is_cancelled()) {
    //Handle form cancel operation, if cancel button is present on form
    redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid);
} else if ($data = $mform->get_data()) {

    //need to update db
    $record = $DB->get_record('block_aia_settings', array('courseid' => $courseid));

    if ($record && !empty($record->bot_name)) {
        $bot_name = explode('-', $record->bot_name);
        $botid = str_replace('"', '', $bot_name[0]);

   
    } else {
        // Handle the error or set a default value for $bot_id
        $botid = null; // or some default value
  
    }

    $botid=intval($botid);

    if($data->id){
        $record->welcome_message = $data->welcome_message; 
        $record->no_context_message = $data->no_context_message;
        $record->subtitle = $data->subtitle;
        $record->embed_position = $data->embed_position;
        $record->timemodified = time(); // Set the modified time

        $DB->update_record('block_aia_settings', $record); //Update record
        $message = get_string('update_successful', 'block_ai_assistant');

    } else {
        // If no record exists, insert a new record instead
        $record = new stdClass();
        $record->courseid = $courseid;
        $record->welcome_message = $data->welcome_message;
        $record->no_context_message = $data->no_context_message;
        $record->subtitle = $data->subtitle;
        $record->embed_position = $data ->embed_position;
        $record->timecreated = time();  
        $record->timemodified = time();  
        
        // Insert the new record
        $DB->insert_record('block_aia_settings', $record);
        $message = get_string('insert_successful', 'block_ai_assistant');
       
    }


    //need to call api update bot to update bot settings in cria backend
    $update=cria::update_bot_instance($courseid, $botid);
    // Redirect with success message
    redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid, $message, null, \core\output\notification::NOTIFY_SUCCESS);
} else {
    // Show form
    $mform->set_data($formdata);
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/ai_assistant/configure_settings.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('configure_settings', 'block_ai_assistant'));
$PAGE->set_heading(get_string('configure_settings', 'block_ai_assistant'));

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
