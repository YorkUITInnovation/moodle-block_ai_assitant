<?php

namespace block_ai_assistant;


use block_ai_assistant\webservice;
use Exception;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet; 
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    echo "Autoload file exists at $autoloadPath\n";
    require_once($autoloadPath);
} else {
    echo "Autoload file does NOT exist at $autoloadPath\n";
}
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/../vendor');
require_once('autoload.php');


class cria
{

    /**
     * Create bot instance and returns bot_name
     * @param int $course_id
     * @return string bot_name
     */
    public static function create_bot_instance($course_id)
    {
        $method = get_string('create_cria_bot_endpoint', 'block_ai_assistant');
        $data = self::get_create_cria_bot_config($course_id);

        $bot_name = webservice::exec($method, $data);
        $bot_name = self::get_bot_name_intent_id($bot_name);
        print_object($bot_name);

        return $bot_name;
    }


    /**
     * Update bot instance and returns bot_name
     * @param int $course_id, $bot_id
     * @return string Message
     */
    public static function update_bot_instance($course_id, $botid)
    {
        $method = get_string('create_cria_bot_endpoint', 'block_ai_assistant');
   
        $data = self::get_create_cria_bot_config($course_id);
        $data['id']=$botid;
        print_object($data);
        

        $updated_bot_name = webservice::exec($method, $data);

        return $updated_bot_name;
    }





    /**
     * Create bot instance and returns bot_name
     * @param int $bot_name
     * @return string bot_name-intentId
     */

    public static function get_bot_name_intent_id($bot_name)
    {
        $method = get_string('cria_get_bot_name_endpoint', 'block_ai_assistant');
        $data = array('bot_id' => $bot_name);
        $bot_name_intent_id = webservice::exec($method, $data);
        print_object($bot_name_intent_id);
        return $bot_name_intent_id;
    }

    /**
     * Returns the config for creating bot instance
     * @param int $course_id
     * @return array
     */
    private static function get_create_cria_bot_config($course_id)
    {
        global $DB;
        // Set parameters
        $context = \context_course::instance($course_id);
        $config = get_config('block_ai_assistant');
        $course_data = $DB->get_record('course', array('id' => $course_id));
        $system_message = self::get_default_system_message($course_id);

        if ($course_data) {
            if ($course_data->idnumber != '') {
                $name = $course_data->idnumber;
            } else {
                $name = $course_data->shortname;
            }
        }
        // Get ai assistant logo
        $image = self::get_ai_assistant_logo();

        $data = array(
            'name' => $name,
            'description' => $config->description,
            'bot_type' => $config->bot_type,
            'bot_system_message' => $system_message,
            'model_id' => $config->criadex_model_id,
            'embedding_id' => $config->criadex_embed_id,
            'rerank_model_id' => $config->criadex_rerank_id,
            'requires_content_prompt' => $config->requires_content_prompt,
            'requires_user_prompt' => $config->requires_user_prompt,
            'user_prompt' => $config->user_prompt,
            'welcome_message' => $config->welcome_message,
            'theme_color' => $config->theme_color,
            'max_tokens' => $config->max_tokens,
            'temperature' => $config->temperature,
            'top_p' => $config->top_p,
            'top_k' => $config->top_k,
            'top_n' => $config->top_n,
            'min_k' => $config->min_k,
            'min_relevance' => $config->min_relevance,
            'max_context' => $config->max_context,
            'no_context_message' => self::get_default_no_context_message(),
            'no_context_use_message' => $config->no_context_use_message,
            'no_context_llm_guess' => $config->no_context_llm_guess,
            'email' => implode('; ', self::get_teacher_emails($course_id)),
            'available_child' => $config->available_child,
            'parse_strategy' => $config->parse_strategy,
            'botwatermark' => $config->botwatermark,
            'title' => $config->title,
            'subtitle' => $config->subtitle,
            'embed_position' => $config->embed_position,
            'icon_file_name' => $image->filename,
            'icon_file_content' => $image->filecontent,
            'bot_locale' => $config->bot_locale,
            'child_bots' => $config->child_bots,
            'publish' => 0
        );
        return $data;
    }

    public static function delete_content_from_bot($contentid)
    {
        $method = get_string('delete_content_from_bot_endpoint', 'block_ai_assistant');
        $data = array("id" => $contentid);
        $status = webservice::exec($method, $data);
        return $status;
    }

    /**
     * Uploads content to bot and returns file_id
     * @param string $file_path
     * @param int $course_id
     * @return int file_id
     */
    public static function upload_content_to_bot($file_path, $course_id)
    {
        $method = get_string('upload_content_to_bot_endpoint', 'block_ai_assistant');
        $data = self::get_upload_content_to_bot_config($file_path, $course_id);
        $file_id = webservice::exec($method, $data);
        return $file_id;
    }

    /**
     * Returns the config for uploading content to bot
     * @param string $file_path
     * @param int $course_id
     * @return array
     */
    private static function get_upload_content_to_bot_config($file_path, $course_id)
    {
        global $DB;
        $file_content = file_get_contents($file_path);
        $encoded_content = base64_encode($file_content);
        $filename = basename($file_path);
        $courserecord = $DB->get_record('block_aia_settings', array('courseid' => $course_id));
        if ($courserecord) {
            $bot_name = $courserecord->bot_name;
            $intentid = explode('-', $bot_name)[1];
            // $intentid =  $bot_name;

        }

        $data = array(
            "intentid" => (int)$intentid,
            "filename" => $filename,
            "filecontent" => $encoded_content,
        );
        return $data;
    }

    /**
     * Copies a file from the draft area to a temporary folder for LLM upload.
     *
     * @param int $contextid The context ID.
     * @param int $courseid The course ID.
     * @return string The path of the copied file.
     * @throws Exception If the directory creation or file copy fails.
     */
    public static function copy_file_to_temp_folder($contextid, $courseid)
    {
        global $CFG;
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid, 'block_ai_assistant', 'syllabus', $courseid);
        // print_object($files);

        if ($files) {
            $file = reset($files);
            $temppath = $CFG->dataroot . '/temp/' . $courseid . '/cria';

            // Check if the directory exists, create it if it doesn't
            if (!is_dir($temppath)) {
                if (!mkdir($temppath, 0777, true)) {
                    throw new Exception("Failed to create directory: $temppath");
                }
            }

            foreach ($files as $file) {
                if ($file->get_filesize() > 0) { // Ensures it's not a directory
                    $filepath = $temppath . '/' . $file->get_filename();
                    $file->copy_content_to($filepath);
                    return $filepath;
                }
            }
        }
    }
    /**
     * Returns default system message
     * @param int $course_id
     * @return array|string|string[]
     * @throws \dml_exception
     */
    public static function get_default_system_message($course_id)
    {
        global $DB;
        $course_data = $DB->get_record('course', array('id' => $course_id));
        $config = get_config('block_ai_assistant');
        $system_message = $config->system_message;
        // Replace the [course_number] with the shortname of the course
        $system_message = str_replace('[course_number]', $course_data->shortname, $system_message);
        // Replace the [course_title] with the fullname of the course
        $system_message = str_replace('[course_title]', $course_data->fullname, $system_message);
        // Add todays date
        $system_message .= ' Todays date is ' . date('Y-m-d');
        return $system_message;
    }

    /**
     * Returns the default no_context_message
     */
    public static function get_default_no_context_message()
    {
        $config = get_config('block_ai_assistant');
        return $config->no_context_message;
    }

    /**
     * Get teachers in the course
     * @param int $course_id
     */
    public static function get_teachers($course_id)
    {
        $context = \context_course::instance($course_id);
        $teachers = get_users_by_capability($context, 'block/ai_assistant:teacher', 'u.id, u.firstname, u.lastname, u.email', 'u.lastname, u.firstname');
        return array_values($teachers);
    }

    /**
     * Get teacher emails in the course
     * @param int $course_id
     */
    public static function get_teacher_emails($course_id)
    {
        $teachers = self::get_teachers($course_id);
        $emails = array();
        foreach ($teachers as $teacher) {
            $emails[] = $teacher->email;
        }
        return $emails;
    }

    /**
     * Get image ai_assistant.png and convert the content to base64
     * @return stdClass
     */
    public static function get_ai_assistant_logo()
    {
        global $CFG;
        $image_data = new \stdClass();
        $path = $CFG->dirroot . '/blocks/ai_assistant/pix/ai_assistant.png';
        $file_content = file_get_contents($path);
        $encoded_content = base64_encode($file_content);

        $image_data->filename = 'ai_assistant.png';
        $image_data->filecontent = $encoded_content;

        return $image_data;
    }

    /**
     * Get embed bot code
     * @param int $bot_id
     * @return string
     */
    public static function get_embed_bot_code($bot_id)
    {
        $config = get_config('block_ai_assistant');
        $embed_code = '';
        if (!empty($config->cria_embed_url)) {
            $embed_code = '<script type="text/javascript" src="' . $config->cria_embed_url . '/embed/' . $bot_id . '/load" async> </script>';
        }
        return $embed_code;
    }

    /**
     * Get questions in json format
     * @param int $course_id
     */
    public static function get_question_json_format($file_content)
    {
        $method = get_string('get_questions_json_format_endpoint', 'block_ai_assistant');

        ///replace all of the below dummy value with the original api call

        $jsonFilePath = 'AL_questions.json';
        $jsonContent = file_get_contents($jsonFilePath);
        $jsonQuestionObj = json_decode($jsonContent, true);
        return $jsonQuestionObj;
    }

    /**
     * Create a question
     * @param int $intentid
     * @param object $questionObj
     * @return int $question_id
     */
    public static function create_question($questionObj)
    {
        $method = get_string('create_question_endpoint', 'block_ai_assistant');
        $question_id = webservice::exec($method, $questionObj);
        return $question_id;
    }

    /**
     * Publish a question to the bot
     * @param int $question_id
     * @return boolean $status
     */
    public static function publish_question($question_id)
    {
        $method = get_string('publish_question_endpoint', 'block_ai_assistant');
        $data = array('id' => $question_id);
        $status = webservice::exec($method, $data);
        return $status;
    }

    /**
     * Parse json object retuned from get_question_json_format
     * @param object $jsonObj
     * @return array $questions
     */
    public static function create_questions_from_json($jsonQuestionObj, $intentid, $courseid)
    {
        foreach ($jsonQuestionObj as $key => $questionData) {

            $intentid = $intentid;
            $name = $key;
            $value = $questionData['question'];
            $answer = $questionData['answer'];
            $relatedquestions = array();
            $lang = 'en';
            $generateanswer = 0;
            $examplequestions = array_map(function ($example) {
                return array('value' => $example);
            }, $questionData['examples']);

            $questionObj = [
                'intentid' => $intentid,
                'name' => $name,
                'value' => $value,
                'answer' => $answer,
                'relatedquestions' => json_encode($relatedquestions),
                'lang' => $lang,
                'generateanswer' => $generateanswer,
                'examplequestions' => json_encode($examplequestions)
            ];
            $question_id = cria::create_question($questionObj);
            print_object($question_id);
            print_object("create question error above");
            $status = cria::publish_question($question_id);
            print_object($status);
            if ($status) {
                $questionData = [
                    'courseid' => $courseid,
                    'name' => $name,
                    'value' => $value,
                    'answer' => $answer,
                    'criaquestionid' => intval($question_id),
                    'related_questions' => json_encode($relatedquestions)
                ];
                self::update_questions_db($questionData);
            }
        }
    }

    private static function update_questions_db($questionData)
    {
        global $DB;
        $question_id = $questionData['criaquestionid'];
        $question_record = $DB->get_record('block_aia_questions', array('criaquestionid' => $question_id));

        if ($question_record) {
            $questionData['id'] = $question_record->id;
            $DB->update_record('block_aia_questions', $questionData);
        } else {
            $DB->insert_record('block_aia_questions', $questionData);
        }
    }

    public static function create_questions_from_xlsx($file, $intentid, $courseid)
{
    global $CFG;

    // Extract the file content
    $content = $file->get_content();

    // Define directory and file paths
    $directoryPath = $CFG->dataroot . '/temp/' . $courseid;
    $tempfile = $directoryPath . '/questions_upload.xlsx';

    // Check if the directory exists, create it if it doesn't
    if (!is_dir($directoryPath)) {
        if (!mkdir($directoryPath, 0777, true)) {
            throw new Exception("Failed to create directory: $directoryPath");
        }
    }

    // Save the content to the temporary file
    file_put_contents($tempfile, $content);

    // Load the spreadsheet from the temporary file
    try {
        $spreadsheet = IOFactory::load($tempfile);
    } catch (Exception $e) {
        throw new Exception("Failed to load spreadsheet: " . $e->getMessage());
    }

    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestRow();
    echo "Total Rows in Spreadsheet: " . $highestRow . "<br>";
    for ($row = 2; $row <= $highestRow; $row++) {
        echo "Processing Row: " . $row . "<br>";
    
        $name = $sheet->getCell('A' . $row)->getValue();
        $value = $sheet->getCell('B' . $row)->getValue();
        $answer = $sheet->getCell('C' . $row)->getValue();
        $relatedquestions = $sheet->getCell('D' . $row)->getValue();
        $lang = $sheet->getCell('E' . $row)->getValue();
        $generateanswer = $sheet->getCell('F' . $row)->getValue();
        $examplequestions = $sheet->getCell('G' . $row)->getValue();
    
        // Debug output
        echo "Name: " . $name . "<br>";
        echo "Value: " . $value . "<br>";
        echo "Answer: " . $answer . "<br>";
        echo "Related Questions: " . $relatedquestions . "<br>";
        echo "Language: " . $lang . "<br>";
        echo "Generate Answer: " . $generateanswer . "<br>";
        echo "Example Questions: " . $examplequestions . "<br>";
    
        // Prepare question data
        $questionObj = [
            'intentid' => $intentid,
            'name' => $name,
            'value' => $value,
            'answer' => $answer,
            'relatedquestions' => $relatedquestions,
            'lang' => $lang,
            'generateanswer' => $generateanswer,
            'examplequestions' => $examplequestions
        ];
    
        try {
            // Create and publish question
            $question_id = cria::create_question($questionObj);
            print_object("Question ID: " . $question_id);
            $status = cria::publish_question($question_id);
            print_object("Publish Status: " . $status);
    
            if ($status) {
                $questionData = [
                    'courseid' => $courseid,
                    'name' => $name,
                    'value' => $value,
                    'answer' => $answer,
                    'criaquestionid' => intval($question_id),
                    'related_questions' => $relatedquestions
                ];
                self::update_questions_db($questionData);
                echo "Question $name processed and saved.<br>";
            } else {
                echo "Failed to publish question $name.<br>";
            }
        } catch (Exception $e) {
            echo "Error processing question $name: " . $e->getMessage() . "<br>";
        }
    }
}

//autotest the bot:
public static function autotest_qa_from_xlsx($file, $courseid)
{ 
    global $CFG;

    // Extract the file content
    $content = $file->get_content();

    // Define directory and file paths
    $directoryPath = $CFG->dataroot . '/temp/' . $courseid;
    $tempfile = $directoryPath . '/autotest_qa.xlsx';

    // Check if the directory exists, create it if it doesn't
    if (!is_dir($directoryPath)) {
        if (!mkdir($directoryPath, 0777, true)) {
            throw new Exception("Failed to create directory: $directoryPath");
        }
    }

    // Save the content to the temporary file
    file_put_contents($tempfile, $content);

    // Load the spreadsheet from the temporary file
    try {
        $spreadsheet = IOFactory::load($tempfile);
    } catch (Exception $e) {
        throw new Exception("Failed to load spreadsheet: " . $e->getMessage());
    }

    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestRow();

    // Prepare a new spreadsheet to store results
    $resultSpreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $resultSheet = $resultSpreadsheet->getActiveSheet();
    $resultSheet->setCellValue('A1', 'Question');
    $resultSheet->setCellValue('B1', 'Expected Answer');
    $resultSheet->setCellValue('C1', 'Bot Answer');

    // Iterate through each question
    for ($row = 2; $row <= $highestRow; $row++) {
        $question = $sheet->getCell('A' . $row)->getValue();
        $expectedAnswer = $sheet->getCell('B' . $row)->getValue();

        // Make an API call to the bot to get the answer for the question
        $botAnswer = self::get_bot_answer($question);

        // Store the result in the new spreadsheet
        $resultSheet->setCellValue('A' . $row, $question);
        $resultSheet->setCellValue('B' . $row, $expectedAnswer);
        $resultSheet->setCellValue('C' . $row, $botAnswer);

        // Debug output
        echo "Question: " . $question . "<br>";
        echo "Expected Answer: " . $expectedAnswer . "<br>";
        echo "Bot Answer: " . $botAnswer . "<br>";
    }
        // Save the results to a CSV file
    $resultFile = $directoryPath . '/questions_results.csv';

    try {
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($resultSpreadsheet, 'Csv');
        $writer->save($resultFile);
        echo "Results saved to: " . $resultFile . "<br>";
    } catch (Exception $e) {
        echo "Failed to save results: " . $e->getMessage() . "<br>";
    }
   
}

private static function get_bot_answer($question)
{
    $responseData="bot_response";
    return $responseData ?? 'No answer';
}

}