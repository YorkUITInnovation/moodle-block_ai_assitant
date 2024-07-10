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
        global $PAGE, $DB, $USER;
        $course_record = $DB->get_record('block_aia_settings', array('courseid' => $this->page->course->id));
        $config = get_config('block_ai_assistant');

        if (!$course_record) {
            $record = new stdClass();
            $record->courseid = $this->page->course->id;
            $record->blockid = $this->instance->id;
            $record->published = 0;
            $record->usermodified = $USER->id;
            $record->timecreated = time();
            $record->timemodified = time();
            $record->bot_name = cria::create_bot_instance($this->page->course->id);
            $record->no_context_message = $config->no_context_message;
            $record->subtitle = $config->subtitle;
            $record->welcome_message = $config->welcomemessage;
            $DB->insert_record('block_aia_settings', $record);
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
        foreach ($questions_files as $file) {
            if ($file->get_filename() != '.') {
                $questions_file = moodle_url::make_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    $file->get_itemid(),
                    $file->get_filepath(),
                    $file->get_filename()
                );
                $questions_url = $questions_file->out();
            }
        }

        $params = array(
            'blockid' => $this->instance->id,
            'courseid' => $this->page->course->id,
            'title' => get_string('title', 'block_ai_assistant'),
            'content' => 'This is the content',
            'syllabus_url' => $syllabus_url,
            'questions_url' => $questions_url,
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
            'site-index' => false,
            'my' => false,
            'course-view' => true,
            'mod' => false
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
}
