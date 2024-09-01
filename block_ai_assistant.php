<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Block ai_assistant is defined here.
 *
 * @package     block_ai_assistant
 * @copyright   2022 UIT Innovation  <thibaud@yorku.ca>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_ai_assistant\cria;

class block_ai_assistant extends block_base
{

    /**
     * Initializes class member variables.
     */
    public function init()
    {
        // Needed by Moodle to differentiate between blocks.
        $this->title = get_string('pluginname', 'block_ai_assistant');
    }

    /**
     * Returns the block contents.
     *
     * @return stdClass The block contents.
     */
    public function get_content()
    {
        global $OUTPUT;
        global $PAGE, $DB, $USER, $CFG;
        $config = get_config('block_ai_assistant');

        $availability = cria::get_availability();
        if ($availability->exception == 'success') {
            if (!$course_record = $DB->get_record('block_aia_settings', array('courseid' => $this->page->course->id))) {
                $record = new stdClass();
                $record->courseid = $this->page->course->id;
                $record->blockid = $this->instance->id;
                $record->bot_name = cria::create_bot_instance($this->page->course->id);
                $record->no_context_message = $config->no_context_message;
                $record->subtitle = $config->subtitle;
                $record->welcome_message = $config->welcome_message;
                $record->lang = $config->default_language;
                $record->published = 0;
                $record->usermodified = $USER->id;
                $record->timecreated = time();
                $record->timemodified = time();
                $DB->insert_record('block_aia_settings', $record);
                $small_talk = cria::create_small_talk_questions($this->page->course->id);
                $course_record = $DB->get_record('block_aia_settings', array('courseid' => $this->page->course->id));
            }
        } else {
            $course_record = new stdClass();
            $course_record->courseid = $this->page->course->id;
            $course_record->cria_file_id = '';
            $course_record->block_id = '';
            $course_record->bot_name = '';
            $course_record->no_context_message = '';
            $course_record->subtitle = '';
            $course_record->welcome_message = '';
            $course_record->lang = '';
            $course_record->published = 0;
        }

        if ($this->content !== null) {
            return $this->content;
        }

        if (empty($this->instance)) {
            $this->content = '';
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';
        $PAGE->requires->js_call_amd('block_ai_assistant/delete_file', 'init');
        $PAGE->requires->js_call_amd('block_ai_assistant/publish_to_students', 'init');
        $PAGE->requires->js_call_amd('block_ai_assistant/course_modules', 'init');
        $PAGE->requires->js_call_amd('block_ai_assistant/training_status', 'init');
        $PAGE->requires->js_call_amd('block_ai_assistant/delete_question', 'init');
        $PAGE->requires->css(new moodle_url('/blocks/ai_assistant/css/styles.css'));

        $course_context = \context_course::instance($this->page->course->id);
        // get file from file area
        $fs = get_file_storage();
        //syllabus files
        $syllabus_files = $fs->get_area_files(
            $course_context->id,
            'block_ai_assistant',
            'syllabus',
            $this->page->course->id
        );
        $bot_id = '';
        if ($availability->exception == 'success') {
            $bot_id = cria::get_bot_id($this->page->course->id);
        }


        // Set syllabus_url
        $syllabus_url = '';
        foreach ($syllabus_files as $file) {
            if ($file->get_filename() != '.') {
                $syllabus_file = moodle_url::make_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    $file->get_itemid(),
                    $file->get_filepath(),
                    $file->get_filename()
                );
                $syllabus_url = $syllabus_file->out();
            }
        }
        //questions files
        $questions_files = $fs->get_area_files(
            $course_context->id,
            'block_ai_assistant',
            'questions',
            $this->page->course->id
        );
        // Set questions_url
        $questions_url = '';
        foreach ($questions_files as $q_file) {
            if (!$q_file->is_directory()) {
                $question_file = moodle_url::make_pluginfile_url(
                    $q_file->get_contextid(),
                    $q_file->get_component(),
                    $q_file->get_filearea(),
                    $q_file->get_itemid(),
                    $q_file->get_filepath(),
                    $q_file->get_filename()
                );
                $questions_url = $question_file->out();
            }
        }

        $embed_code = '';
        if ($availability->exception == 'success') {
            if ($course_record->published == 1) {
                $embed_code = cria::get_embed_bot_code($bot_id);
            } else {
                $embed_code = '';
            }
        }

        // Find out if there are any autotest questions uploaded
        $autotest_url = '';
        if ($availability->exception == 'success') {
            if (!$autotest = $DB->get_records('block_aia_autotest', ['courseid' => $this->page->course->id])) {
                $autotest_url = $CFG->wwwroot . '/blocks/ai_assistant/autotest_import.php?courseid=' . $this->page->course->id;
            } else {
                $autotest_url = $CFG->wwwroot . '/blocks/ai_assistant/autotest.php?courseid=' . $this->page->course->id;
            }
        }

        $teacher_embed_code = '';
        $training_status_id = '';
        $training_status = '';

        // Get training status
        if ($availability->exception == 'success') {
            if ($course_record->cria_file_id) {
                $results = cria::get_content_training_status($course_record->cria_file_id);
                $training_status_id = $results->training_status_id;
                $training_status = $results->training_status;
                $teacher_embed_code = cria::get_embed_bot_code($bot_id);
            } else {
                $training_status_id = 4;
                $training_status = '';
            }
        }

        // Get question files
        $question_file = $DB->get_record('block_aia_question_files', array('courseid' => $this->page->course->id));

        // Get training status
        $question_training_status_id = '';
        $question_training_status = '';
        if ($availability->exception == 'success') {
            if ($question_file->cria_fileid) {
                $results = cria::get_content_training_status($question_file->cria_fileid);
                $question_training_status_id = $results->training_status_id;
                $question_training_status = $results->training_status;
                $teacher_embed_code = cria::get_embed_bot_code($bot_id);
            } else {
                $question_training_status_id = 4;
                $question_training_status = '';
            }
        }

        $error_code = '';
        $error_message = '';
        if ($availability->exception != 'success') {
            $error_code = $availability->errorcode;
            $error_message = $availability->message;
        }

        $params = array(
            'blockid' => $this->instance->id,
            'courseid' => $this->page->course->id,
            'cria_file_id' => $course_record->cria_file_id,
            'published' => $course_record->published,
            'title' => get_string('title', 'block_ai_assistant'),
            'content' => 'This is the content',
            'configure_settings_url' => (new \moodle_url('/blocks/ai_assistant/configure_settings.php', [
                'courseid' => $this->page->course->id,
            ]))->out(false),
            'syllabus_url' => $syllabus_url,
            'questions_url' => $questions_url,
            'question_id' => $question_file->id,
            'embed_code' => $embed_code,
            'teacher_embed_code' => $teacher_embed_code,
            'autotest_url' => $autotest_url,
            'embed_offset' => $config->embed_position_teacher,
            'training_status' => $training_status,
            'training_status_id' => $training_status_id,
            'question_training_status' => $question_training_status,
            'question_training_status_id' => $question_training_status_id,
            'error_code' => $error_code,
            'error_message' => $error_message
        );

        if (!empty($this->config->text)) {
            $this->content->text = $this->config->text;
        } else {
            if (has_capability('block/ai_assistant:teacher', $course_context)) {
                $text = $OUTPUT->render_from_template('block_ai_assistant/default', $params);
            } else {
                $text = $OUTPUT->render_from_template('block_ai_assistant/student', $params);
            }
            $this->content->text = $text;
        }

        return $this->content;
    }

    // my moodle can only have SITEID and it's redundant here, so take it away
    public function applicable_formats()
    {
        return array(
            'course-view' => true,
            'site-index' => false,
            'my' => false,
            'mod' => false,
            'tag' => false
        );
    }

    /**
     * Enables global configuration of the block in settings.php.
     *
     * @return bool True if the global configuration is enabled.
     */
    public function has_config()
    {
        return true;
    }

    /**
     * Only one block can be installed per course.
     * @return false
     */
    public function instance_allow_multiple()
    {
        return false;
    }

    public function instance_delete()
    {
        global $COURSE, $DB;
        // Get settings record
        $settings = $DB->get_record('block_aia_settings', array('courseid' => $COURSE->id));
        // get bot id
        $bot_name = explode('-', $settings->bot_name);
        $bot_id = str_replace('"', '', $bot_name[0]);
        // Delete bot from Cria
        $results = cria::delete_bot_instance($bot_id);
        file_put_contents('/var/www/moodledata/temp/delete_cria_bot.json', json_encode($results, JSON_PRETTY_PRINT));
        // Delete all settings for this course
        $DB->delete_records('block_aia_settings', array('courseid' => $COURSE->id));
        // Delete Autotest questions
        $DB->delete_records('block_aia_autotest', array('courseid' => $COURSE->id));
        // Questions
        $DB->delete_records('block_aia_question_files', array('courseid' => $COURSE->id));
        // Delete the files in filearea syllabus
        $fs = get_file_storage();
        $context = \context_course::instance($COURSE->id);
        $files = $fs->get_area_files($context->id, 'block_ai_assistant', 'syllabus', $COURSE->id);
        foreach ($files as $file) {
            $file->delete();
        }
        $question_files = $fs->get_area_files($context->id, 'block_ai_assistant', 'questions', $COURSE->id);
        foreach ($question_files as $question_file) {
            $question_file->delete();
        }
        return true;
    }
}
