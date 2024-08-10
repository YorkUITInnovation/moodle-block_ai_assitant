<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use block_ai_assistant\cria;

global $DB, $USER;
// Define the input options.
$longparams = array(
    'help' => false,
    'courseid' => '',
);

$shortparams = array(
    'h' => 'help',
    'cid' => 'courseid',

);

// now get cli options
list($options, $unrecognized) = cli_get_params($longparams, $shortparams);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help =
        "Run AuoTest.

There are no security checks here because anybody who is able to
execute this file may execute any PHP too.

Options:
-h, --help                    Print out this help
-cid, --courseid=courseid       The intent id to index files for

Example:
\$sudo -u www-data /usr/bin/php local/cria/cli/index_files.php -cid=45
\$sudo -u www-data /usr/bin/php local/cria/cli/index_files.php --courseid=45
";

    echo $help;
    die;
}

if ($options['courseid'] == '') {
    cli_heading('AutoTest');
    $prompt = "Enter course id";
    $courseid = cli_input($prompt);
} else {
    $courseid = $options['courseid'];
}
// Get block settings
$settings = $DB->get_record('block_aia_settings', ['courseid' => $courseid]);
// Get all autotest questions
$autotest_questions = $DB->get_records('block_aia_autotest', ['courseid' => $courseid]);
// Set the chat id
$chat_id = cria::get_chat_id();
// Get the bot id
$bot_name = explode('-', $settings->bot_name);
$bot_id = str_replace('"', '', $bot_name[0]);
// number of questions
$number_of_questions = count($autotest_questions);
// Set a counter
$counter = 1;
// Loop through all autotest questions
foreach ($autotest_questions as $question) {
    $response = cria::get_gpt_response($chat_id, $bot_id, $question->questions);
    $response = json_decode($response);
    // Get stacktrace data to determine the status of the response
    $stack_trace = json_decode($response->stacktrace);
    if ($stack_trace->status == 200) {
        $params = [
            'id' => $question->id,
            'bot_answer' => $response->message,
            'timemodified' => time(),
            'usermodified' => $USER->id
        ];
        // Update record
        $DB->update_record('block_aia_autotest', (object)$params);
        // In case I find a way to retrieve the info for a progress bar
        $counter++;
        sleep(1);
    }
}