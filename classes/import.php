<?php

namespace block_ai_assistant;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class import
{
    /**
     * @var false|\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
     */
    private $worksheet;

    /**
     * @var string
     */
    private $file_type;

    /**
     * @param $file string  Path to file
     */
    public function __construct($file = '')
    {
        if ($file) {
            // Make sure we have an .xlsx file
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $file_info = finfo_file($finfo, $file);
            $this->file_type = null;

            if ($file_info == 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                $spread_sheet = $reader->load($file);
                $this->worksheet = $spread_sheet->getActiveSheet();
                $this->file_type = 'XLSX';
            } else if ($file_info == 'application/json') {
                $this->worksheet = false;
                $this->file_type = 'JSON';
                // now check for docx
            } else if ($file_info == 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
                $this->worksheet = false;
                $this->file_type = 'DOCX';
            } else {
                \core\notification::error('You must upload an xlsx file');
                $this->worksheet = false;
                $this->file_type = null;
            }
        }
    }

    /**
     * Return file type
     * @return string
     */
    public function get_file_type(): string
    {
        return $this->file_type;
    }

    /**
     * Returns an array of all columns in the first row of the work sheet
     * @return array
     */
    public function get_columns()
    {
        $worksheet = $this->worksheet;
        $worksheet_array = $worksheet->toArray();
//        print_object($worksheet_array);
        $columns = [];
        for ($i = 0; $i < count($worksheet_array[0]); $i++) {
            if ($worksheet_array[0][$i]) {
                $columns[$i] = $worksheet_array[0][$i];
            }
        }
        return $columns;
    }

    /**
     * Returns all rows as an array
     * @return array
     */
    public function get_rows()
    {
        raise_memory_limit(MEMORY_UNLIMITED);
        $worksheet = $this->worksheet;
        $worksheet_array = $worksheet->toArray();
        $number_of_rows = count($worksheet_array);
        $columns = $this->get_columns();
        $data = [];
        $rows = [];
        // Start at 1 because 0 is the first row
        for ($i = 1; $i <= $number_of_rows; $i++) {

            foreach ($columns as $key => $column) {
                if (isset($worksheet_array[$i][$key])) {
                    $rows[$i][$key] = $worksheet_array[$i][$key];
                } else {
                    $rows[$i][$key] = '';
                }
            }

        }
        raise_memory_limit(MEMORY_STANDARD);
        return $rows;
    }

    /**
     * Returns array for colum names
     * @return array
     */
    public function clean_column_names()
    {
        $columns = $this->get_columns();
        $column_names = [];
        foreach ($columns as $key => $column) {
            $column_names[$key] = new \stdClass();
            $column_names[$key]->fullname = $column;
            // Clean the column name
            $clean_column = preg_replace('/[^\w\s]+/', '', $column);;
            $clean_column = str_replace(" ", '_', $clean_column);
            $clean_column = strtolower($clean_column);
            $column_names[$key]->shortname = $clean_column;

        }

        return $column_names;
    }

    /**
     * @param $columns array
     * @param $rows array
     * @return void
     */
    public function autotest_excel($course_id, $columns, $rows)
    {
        global $CFG, $DB, $USER;
        // Make sure the columns exist
        if (!in_array('section', $columns)) {
            \core\notification::error(get_string('column_name_must_exist', 'local_cria', ['section']));
            redirect($CFG->wwwroot . '/course/view.php?id=' . $course_id);
        }
        if (!in_array('questions', $columns)) {
            \core\notification::error(get_string('column_name_must_exist', 'local_cria', ['questions']));
            redirect($CFG->wwwroot . '/course/view.php?id=' . $course_id);
        }
        if (!in_array('answer', $columns)) {
            \core\notification::error(get_string('column_name_must_exist', 'local_cria', ['answer']));
            redirect($CFG->wwwroot . '/course/view.php?id=' . $course_id);
        }


        // Set the proper key value for the columns
        foreach ($columns as $key => $column) {
            switch (trim($column)) {
                case 'section':
                    $section = $key;
                    break;
                case 'questions':
                    $questions = $key;
                    break;
                case 'answer':
                    $answer = $key;
                    break;
            }
        }

        $current_section = '';
        $current_section_row = 0;
        for ($i = 1; $i < count($rows); $i++) {
            if (!empty(trim($rows[$i][$section]))) {
                $current_section = trim($rows[$i][$section]);
                $current_section_row = $i;
                if (isset($rows[$i][$answer])) {
                    if (empty(trim($rows[$i][$answer]))) {
                        continue;
                    } else {
                        $answer = $rows[$i][$answer];
                    }
                }

                // Create question
                $params = [
                    'courseid' => $course_id,
                    'section' => str_replace('_', ' ', $current_section),
                    'questions' => trim($rows[$i][$questions]),
                    'human_answer' => trim($answer),
                    'timecreated' => time(),
                    'timemodified' => time(),
                    'usermodified' => $USER->id
                ];
            } else {
                $params = [
                    'courseid' => $course_id,
                    'section' => str_replace('_', ' ', $current_section),
                    'questions' => trim($rows[$i][$questions]),
                    'human_answer' => trim($answer),
                    'timecreated' => time(),
                    'timemodified' => time(),
                    'usermodified' => $USER->id
                ];
            }
            $DB->insert_record('block_aia_autotest', $params);
        }

        return true;
    }

    /**
     * @param $columns array
     * @param $rows array
     * @return void
     */
    public function questions_excel($course_id, $columns, $rows)
    {
        global $CFG, $DB, $USER;
        raise_memory_limit(MEMORY_UNLIMITED);
        // Make sure the columns exist
        if (!in_array('name', $columns)) {
            \core\notification::error(get_string('column_name_must_exist', 'block_ai_assistant', ['name']));
            redirect($CFG->wwwroot . '/course/view.php?id=' . $course_id);
        }
        if (!in_array('question', $columns)) {
            \core\notification::error(get_string('column_name_must_exist', 'block_ai_assistant', ['question']));
            redirect($CFG->wwwroot . '/course/view.php?id=' . $course_id);
        }
        if (!in_array('answer', $columns)) {
            \core\notification::error(get_string('column_name_must_exist', 'block_ai_assistant', ['answer']));
            redirect($CFG->wwwroot . '/course/view.php?id=' . $course_id);
        }


        // Set the proper key value for the columns
        foreach ($columns as $key => $column) {
            switch (trim($column)) {
                case 'name':
                    $name = $key;
                    break;
                case 'question':
                    $question = $key;
                    break;
                case 'answer':
                    $answer = $key;
                    break;
                case 'related_questions':
                case 'relatedquestions':
                    $related_questions = $key;
                    break;
                case 'lang':
                case 'language':
                    $lang = $key;
                    break;
                case 'generate_questions':
                case 'generatequestions':
                    $generate_questions = $key;
                    break;
            }
        }

        $current_name = '';
        $current_name_row = 0;

        for ($i = 1; $i < count($rows); $i++) {
            if (!empty(trim($rows[$i][$name])) && $current_name != trim($rows[$i][$name])) {

                $current_name = trim($rows[$i][$name]);
                $current_name_row = $i;
                $example_questions_array[$current_name_row] = [];
                // Create question
                $params[$current_name_row] = [
                    'courseid' => $course_id,
                    'name' => $current_name,
                    'value' => trim($rows[$i][$question]),
                    'answer' => $rows[$i][$answer],
                    'timecreated' => time(),
                    'timemodified' => time(),
                    'usermodified' => $USER->id
                ];
            } else {
                $example_questions_array[$current_name_row][]['value'] = trim($rows[$i][$question]);
            }
            $params[$current_name_row]['example_questions'] = json_encode($example_questions_array[$current_name_row]);
        }
        // Save the questions
        foreach ($params as $data) {
            $new_id = $DB->insert_record('block_aia_questions', $data);
            $question = new \stdClass();
            $question->intentid = cria::get_intent_id($course_id);
            $question->name = $data['name'];
            $question->value = $data['value'];
            $question->answer = $data['answer'];
            $question->generateanswer = 1;
            $question->examplequestions = $data['example_questions'];
            $question_id = cria::create_question($question);
            $status = cria::publish_question($question_id);
            // Update criaquestionid field
            $DB->set_field('block_aia_questions', 'criaquestionid', $question_id, ['id' => $new_id]);
        }

        raise_memory_limit(MEMORY_STANDARD);
        return true;
    }

}