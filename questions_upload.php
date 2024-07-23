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

require_once($CFG->dirroot . "/blocks/ai_assistant/classes/forms/questions_upload.php");

use block_ai_assistant\cria;


global $CFG, $OUTPUT, $USER, $PAGE, $DB;

// Get course id
$courseid = required_param('courseid', PARAM_INT);
$intentid = 0; // Default value


$courserecord = $DB->get_record('block_aia_settings', array('courseid' => $courseid));
if ($courserecord) {
    $bot_name = $courserecord->bot_name;
    $intentid = (int)explode('-', $bot_name)[1];
   
}

$context = CONTEXT_COURSE::instance($courseid);

require_login(1, false);

$formdata = new stdClass();
$formdata->courseid = $courseid;

// Create form
$mform = new \block_ai_assistant\questions_upload_form(null, array('formdata' => $formdata));
if ($mform->is_cancelled()) {
    //Handle form cancel operation, if cancel button is present on form
    redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid);
} else if ($data = $mform->get_data()) {

    file_save_draft_area_files(
        $data->questions_upload,
        $context->id,
        'block_ai_assistant',
        'questions',
        $data->courseid,
        array('subdirs' => 0, 'maxfiles' => 1)
    );

    //assuming the file uploaded is parsed and converted to json as required
    //get hold of file contents
    // Path to the JSON file
    $jsonFilePath = 'AL_questions.json';

    // Read the JSON file contents
    $jsonContent = file_get_contents($jsonFilePath);
    // print_object($jsonContent);

    if ($jsonContent === false) {
        die('Error reading JSON file');
    }
    // Parse the JSON data
    $questionsArray = json_decode($jsonContent, true);

    if ($questionsArray === null) {
        die('Error decoding JSON data');
    }

    // Print the parsed JSON data
    // print_r($questionsArray);
   
    print_object($intentid);

    // Loop through the parsed JSON data and call the API for each question
    foreach ($questionsArray as $key => $questionData) {
        $name = $key;
        $value = $questionData['question'];
        $answer = $questionData['answer'];
        $relatedquestions = array(); // Adjust this if you have related questions in your JSON structure
        $lang = 'en'; // Default language
        $generateanswer = 0; // Default generate answer setting
        $examplequestions = array_map(function($example) {
            return array('value' => $example);
        }, $questionData['examples']);

        // Call the Cria API to create the question
        $response = cria::cria_question_create($intentid, $name, $value, $answer, $relatedquestions, $lang, $generateanswer, $examplequestions);
        print_object($response);
        $publishresponse=cria::cria_question_publish($response);
        print_object($publishresponse);




       
    }
    //make api call to store each question in cria

    //add questions to db
    // Redirect with success message
    // redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid, get_string('file_uploaded_successfully', 'block_ai_assistant'), null, \core\output\notification::NOTIFY_SUCCESS);
} else {
    // Show form
    $mform->set_data($formdata);
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/ai_assistant/questions_upload.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('questions', 'block_ai_assistant'));
$PAGE->set_heading(get_string('questions', 'block_ai_assistant'));

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
