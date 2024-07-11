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
require_once($CFG->dirroot . "/blocks/ai_assistant/classes/forms/questions_edit.php");

global $CFG, $OUTPUT, $USER, $PAGE, $DB;

// Get course id
$courseid = required_param('courseid', PARAM_INT);

$context = context_course::instance($courseid);

require_login($courseid);

$formdata = new stdClass();
$formdata->courseid = $courseid;

// Create form
$mform = new \block_ai_assistant\questions_edit(null, array('formdata' => $formdata));
if ($mform->is_cancelled()) {
    // Handle form cancel operation, if cancel button is present on form
    redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid);
} else if ($data = $mform->get_data()) {
    // Sample JSON response
    $sample_json_response = [
        "Goodbye " => [
            "question" => "Goodbye ",
            "examples" => [
                "Bye. ",
                "Ciao. ",
                "Gotta go. ",
                "Talk to you later. ",
                "See you later. ",
                "Have a nice day. ",
                "The response to any of these prompts is (be playful and funny): "
            ],
            "answer" => "Cheers! Come back and see me\u2026 It's lonely being a bot\u2026",
            "criaquestionid" => 1
        ],
        "Does the video have to be on during a Zoom meeting? " => [
            "question" => "Does the video have to be on during a Zoom meeting? ",
            "examples" => [
                "Switch on video for my class. ",
                "is video compulsory on zoom? ",
                "Do I have to turn on my video during a Zoom session? ",
                "The response to any of these questions or prompts is:"
            ],
            "answer" => "You don't have to turn on your video during a Zoom session. It’s up to you. However, interactions would be humanized and improved if your peers could put a face to your name and voice.",
            "criaquestionid" => 3
        ]
    ];

    // Parse the JSON response and process each question-answer pair
    foreach ($sample_json_response as $key => $data) {
        $question = $data['question'];
        $answer = $data['answer'];
        $examples = $data['examples'];
        $criaquestionid = $data['criaquestionid']; // Assuming this is included in your API response

        // Check if the record already exists
        $record = $DB->get_record('block_aia_questions', array('criaquestionid' => $criaquestionid, 'courseid' => $courseid));

        if ($record) {
            // Update the existing record
            $record->name = $key;
            $record->value = $question;
            $record->answer = $answer;
            $DB->update_record('block_aia_questions', $record);
        } else {
            // Insert a new record
            $new_record = new stdClass();
            $new_record->courseid = $courseid;
            $new_record->name = $key;
            $new_record->value = $question;
            $new_record->answer = $answer;
            $new_record->criaquestionid = $criaquestionid;
            $DB->insert_record('block_aia_questions', $new_record);
        }
    }

    // Redirect with success message
    redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid, get_string('file_uploaded_successfully', 'block_ai_assistant'), null, \core\output\notification::NOTIFY_SUCCESS);
} else {
    // Show form
    $mform->set_data($formdata);
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/ai_assistant/questions_edit.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('questions', 'block_ai_assistant'));
$PAGE->set_heading(get_string('questions', 'block_ai_assistant'));

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();