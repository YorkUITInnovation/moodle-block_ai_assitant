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
use block_ai_assistant\tutorials;

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
        require_once($CFG->libdir . '/gradelib.php');
        $config = get_config('block_ai_assistant');

        $availability = cria::get_availability();
        if ($availability->exception == 'success') {
            if (!$course_record = $DB->get_record('block_aia_settings', array('courseid' => $this->page->course->id))) {
                $record = new stdClass();
                $record->courseid = $this->page->course->id;
                $record->blockid = $this->instance->id;
                $created = cria::create_bot_instance($this->page->course->id);
                $bot_name = $created;
                $bot_id = 0;
                $bot_api_key = '';
                $create_status = 0;
                $create_code = '';
                $create_message = '';
                $decoded = json_decode((string)$created, true);
                if (is_array($decoded) && isset($decoded['name'])) {
                    $bot_name = (string)$decoded['name'];
                    $bot_id = (int)($decoded['bot_id'] ?? 0);
                    $bot_api_key = (string)($decoded['bot_api_key'] ?? '');
                    $create_status = (int)($decoded['status'] ?? 0);
                    $create_code = (string)($decoded['code'] ?? '');
                    $create_message = (string)($decoded['message'] ?? '');
                }
                $record->bot_name = $bot_name;
                if ($bot_id > 0) {
                    $record->bot_id = $bot_id;
                }
                if ($bot_api_key !== '') {
                    $record->bot_api_key = $bot_api_key;
                }
                if ($bot_api_key === '' && $create_status && ($create_code || $create_message)) {
                    $record->published = 0;
                }
                $record->no_context_message = $config->no_context_message;
                $record->subtitle = $config->subtitle;
                $record->welcome_message = $config->welcome_message;
                $record->lang = $config->default_language;
                $record->published = 0;
                $record->publish_tutorials = 0;
                $record->usermodified = $USER->id;
                $record->timecreated = time();
                $record->timemodified = time();
                $DB->insert_record('block_aia_settings', $record);
                $small_talk = cria::create_small_talk_questions($this->page->course->id);
                $course_record = $DB->get_record('block_aia_settings', array('courseid' => $this->page->course->id));

                // Now add the default tutorials.
                tutorials::create_default_tutorials($this->page->course->id);
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

        $bot_api_key_exists = false;
        if ($availability->exception == 'success') {
            $blockcfg = get_config('block_ai_assistant');
            $localcfg = get_config('local_cria');
            $has_criabot = (!empty($blockcfg->criabot_url) || !empty($localcfg->criabot_url));

            if (!empty($course_record->bot_api_key)) {
                $bot_api_key_exists = true;
            } else if (!$has_criabot) {
                $course_record->bot_api_key = cria::get_api_key(cria::get_bot_id($this->page->course->id));
                if (!empty($course_record->bot_api_key)) {
                    $DB->set_field('block_aia_settings', 'bot_api_key', $course_record->bot_api_key, ['courseid' => $this->page->course->id]);
                    $bot_api_key_exists = true;
                }
            }
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
        $PAGE->requires->js_call_amd('block_ai_assistant/learning_assistant', 'init');
        $PAGE->requires->js_call_amd('block_ai_assistant/disabled_assistant', 'init', [$course_record->published == 1]);
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
        $has_syllabus_file = false;
        $bot_id = '';
        if ($availability->exception == 'success') {
            $bot_id = cria::get_bot_id($this->page->course->id);
        }


        // Set syllabus_url
        $syllabus_url = '';
        foreach ($syllabus_files as $file) {
            if ($file->get_filename() != '.') {
                $has_syllabus_file = true;
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
        // Get the users grade
        // Get the grade item for the course
        $user_grade = 0;
        cria::ensure_course_gradebook_integrity((int)$this->page->course->id);
        $grade_item = grade_item::fetch(array('itemtype' => 'course', 'courseid' => $this->page->course->id));
        if ($grade_item) {
            // Get the user's grade
            $grades = grade_grade::fetch_users_grades($grade_item, array($USER->id), true);
            $user_grade = $grades[$USER->id]->finalgrade;
        }

        // Get the user's groups
        $user_groups = groups_get_all_groups($this->page->course->id, $USER->id, 0, 'g.*', false);
        $groups = '';
        foreach ($user_groups as $group) {
            $groups .= $group->name . ',';
        }
        // Remove trialing comma
        $groups = rtrim($groups, ',');

        $is_student = false;
        $is_teacher = false;
        // Check to see if user is a student
        if (has_capability('block/ai_assistant:teacher', $course_context)) {
            $is_teacher = true;
            $name = get_string('teacher_and_name', 'block_ai_assistant', fullname($USER));
        } else {
            $is_student = true;
            $name = get_string('student_and_name', 'block_ai_assistant', fullname($USER));
        }

        $launcherproxystate = ((int)($course_record->published ?? 0) === 1)
            ? get_string('enabled', 'block_ai_assistant')
            : get_string('disabled', 'block_ai_assistant');
        $launcherproxytitle = get_string('ai_assistant', 'block_ai_assistant')
            . ' - ' . get_string('access', 'block_ai_assistant') . ': ' . $launcherproxystate;

        // Set payload. Embed chat uses this to scope answers to the current course and personalize prompts.
        $course_title = trim((string)($this->page->course->fullname ?? ''));
        $course_shortname = trim((string)($this->page->course->shortname ?? ''));
        $course_number = trim((string)($this->page->course->idnumber ?? ''));
        if ($course_number === '') {
            $course_number = $course_shortname;
        }

        $payload = array(
            'idNumber' => $USER->idnumber,
            'name' => $name,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'grade' => $user_grade,
            'groups' => $groups,
            'courseId' => (int)$this->page->course->id,
            'courseName' => $course_title,
            'courseTitle' => $course_title,
            'courseShortName' => $course_shortname,
            'courseNumber' => $course_number,
            'currentDate' => userdate(time(), get_string('strftimedatefullshort', 'langconfig')),
        );
        // get embed code data
        $embed_session_data = cria::start_session(
                $this->page->course->id,
                $course_record->bot_api_key,
                $payload) ;

        $embed_code_data = '<script>' . $embed_session_data . '</script>';
        $embed_code = '';
        $embed_warning_message = '';
        $embed_warning_details = '';
        $has_embed_loader = trim((string)$embed_session_data) !== '';

        if ($availability->exception == 'success' && !$has_embed_loader) {
            $embed_warning_message = get_string('embed_loader_unavailable', 'block_ai_assistant');
            $embed_warning_details = get_string('embed_loader_unavailable_help', 'block_ai_assistant');
        }

        if ($availability->exception == 'success') {
            if ($course_record->published == 1 && $has_embed_loader) {
                $embed_code .= $embed_code_data;
            } else {
                $embed_code = '';
            }
        }

        // Find out if there are any autotest questions uploaded
        $autotest_url = '';
        if (has_capability('block/ai_assistant:view_autotest', $course_context)) {
            if ($availability->exception == 'success') {
                if (!$autotest = $DB->get_records('block_aia_autotest', ['courseid' => $this->page->course->id])) {
                    $autotest_url = $CFG->wwwroot . '/blocks/ai_assistant/autotest_import.php?courseid=' . $this->page->course->id;
                } else {
                    $autotest_url = $CFG->wwwroot . '/blocks/ai_assistant/autotest.php?courseid=' . $this->page->course->id;
                }
            }
        }

        $teacher_embed_code = '';
        $training_status_id = '';
        $training_status = '';

        if ($availability->exception == 'success' && $has_embed_loader && $is_teacher) {
            // Teachers should always see the launcher while managing the block,
            // even if student publishing is disabled.
            $teacher_embed_code = $embed_code_data;
        }

        // Get training status
        $show_bot = false;
        if ($availability->exception == 'success') {
            $syllabus_file_id = isset($course_record->cria_file_id) ? (int)$course_record->cria_file_id : 0;
            $syllabus_document_name = trim((string)($course_record->syllabus_document_name ?? ''));
            if (!empty($course_record->syllabus_trained)) {
                $training_status_id = 1;
                $training_status = '<div class="badge badge-success">'
                    . get_string('trained', 'block_ai_assistant') . '</div>';
                $show_bot = true;
            } else if ($syllabus_file_id > 0) {
                $results = cria::get_content_training_status($syllabus_file_id);
                $training_status_id = $results->training_status_id;
                $training_status = $results->training_status;
                if ((int)$training_status_id === 1) {
                    $show_bot = true;
                }
            } else if ($syllabus_document_name !== '') {
                $training_status_id = 1;
                $training_status = '<div class="badge badge-success">'
                    . get_string('trained', 'block_ai_assistant') . '</div>';
                $show_bot = true;
            } else if ($has_syllabus_file) {
                $training_status_id = 0;
                $training_status = '<div class="badge badge-warning">'
                    . get_string('pending', 'block_ai_assistant') . '</div>';
            } else {
                $training_status_id = 4;
                $training_status = '';
            }

            // Check to see if there are any trained modules
            $modules = $DB->count_records('block_aia_course_modules', array('courseid' => $this->page->course->id));
            if ($modules > 0) {
                $show_bot = true;
            }
        } else {
            $training_status_id = 4;
            $training_status = '';
        }

        // Get question files
        $question_file = $DB->get_record('block_aia_question_files', array('courseid' => $this->page->course->id));

        // Get training status
        $question_training_status_id = '';
        $question_training_status = '';
        if ($availability->exception == 'success') {
            if ($question_file) {
                $stored_question_name = trim((string)$question_file->name);
                $has_doc_marker = strpos($stored_question_name, '[doc:') !== false;
                if ($stored_question_name !== '' && $question_file->cria_fileid == 0) {
                    if ($has_doc_marker) {
                        $question_training_status_id = 1;
                        $question_training_status = '<div class="badge badge-success">'
                            . get_string('trained', 'block_ai_assistant') . '</div>';
                    } else {
                        $question_training_status_id = 0;
                        $question_training_status = '<div class="badge badge-warning">'
                            . get_string('pending', 'block_ai_assistant') . '</div>';
                    }
                } else if ($question_file->cria_fileid) {
                    $results = cria::get_content_training_status($question_file->cria_fileid);
                    $question_training_status_id = $results->training_status_id;
                    $question_training_status = $results->training_status;
                } else {
                    $question_training_status_id = 0;
                    $question_training_status = '<div class="badge badge-warning">'
                        . get_string('pending', 'block_ai_assistant') . '</div>';
                }
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

        if ($bot_api_key_exists == false && !$has_embed_loader) {
            $localcfg = get_config('local_cria');
            $has_criabot = (!empty($config->criabot_url) || !empty($localcfg->criabot_url));
            if ($has_criabot) {
                $error_code = 'BOT_API_KEY_MISSING';
                $error_message = 'Bot API key was not returned by Criabot. Check Criabot create response and API key configuration.';
            } else {
                $error_code = get_string('invalid_token', 'block_ai_assistant');
                $error_message = get_string('bot_api_key_not_found', 'block_ai_assistant');
            }
        }

        // Set question file id
        $question_file_id = 0;
        if ($question_file) {
           $question_file_id = $question_file->id;
        }

          $teacherpositionraw = isset($config->embed_position_teacher) ? (int)$config->embed_position_teacher : 0;
          $teacherposition = ($teacherpositionraw >= 1 && $teacherpositionraw <= 4) ? $teacherpositionraw : 1;
          $teacherembedpositionstyle = self::build_embed_position_style($teacherposition);

          $studentpositionraw = isset($course_record->embed_position) ? (int)$course_record->embed_position : (int)$config->embed_position;
          $studentposition = ($studentpositionraw >= 1 && $studentpositionraw <= 4) ? $studentpositionraw : 1;
          $studentembedpositionstyle = self::build_embed_position_style($studentposition);

        $tutorials = '';
        if (!property_exists($course_record, 'publish_tutorials')) {
            $course_record->publish_tutorials = 0;
        }
        if (has_capability('block/ai_assistant:teacher', $course_context)) {
            $tutorials = tutorials::get_tutorials($this->page->course->id);
        } else {
            if ($course_record->publish_tutorials) {
                $tutorials = tutorials::get_tutorials($this->page->course->id);
            }
        }


        $params = array(
            'blockid' => $this->instance->id,
            'courseid' => $this->page->course->id,
            'cria_file_id' => $course_record->cria_file_id,
            'published' => $course_record->published,
            'publish_tutorials' => $course_record->publish_tutorials,
            'is_published' => ($course_record->published == 1),
            'title' => get_string('title', 'block_ai_assistant'),
            'content' => 'This is the content',
            'configure_settings_url' => (new \moodle_url('/blocks/ai_assistant/configure_settings.php', [
                'courseid' => $this->page->course->id,
            ]))->out(false),
            'syllabus_url' => $syllabus_url,
            'questions_url' => $questions_url,
            'question_id' => $question_file_id,
            'embed_code' => $embed_code,
            'teacher_embed_code' => $teacher_embed_code,
            'autotest_url' => $autotest_url,
            'teacher_embed_position_style' => $teacherembedpositionstyle,
            'student_embed_position_style' => $studentembedpositionstyle,
            'launcher_proxy_title' => $launcherproxytitle,
            'launcher_proxy_color' => !empty($config->theme_color) ? (string)$config->theme_color : '#e31837',
            'training_status' => $training_status,
            'training_status_id' => $training_status_id,
            'question_training_status' => $question_training_status,
            'question_training_status_id' => $question_training_status_id,
            'error_code' => $error_code,
            'error_message' => $error_message,
            'embed_warning_message' => $embed_warning_message,
            'embed_warning_details' => $embed_warning_details,
            'is_admin' => has_capability('block/ai_assistant:view_autotest', $course_context),
            'tutorials' => $tutorials,
            'is_teacher' => $is_teacher,
            'is_student' => $is_student,
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
            'course-view-*' => true,
            'site-index' => false,
            'my' => false,
            'mod' => false,
            'tag' => false
        );
    }

    /**
     * Convert embed position code to fixed launcher CSS.
     * 1: bottom-left, 2: bottom-right, 3: top-right, 4: top-left.
     *
     * @param int $position
     * @return string
     */
    private static function build_embed_position_style(int $position): string
    {
        $distance = 24;

        switch ($position) {
            case 2:
                return 'left: auto !important; right: ' . $distance . 'px !important; bottom: ' . $distance
                    . 'px !important; top: auto !important;';
            case 3:
                return 'left: auto !important; right: ' . $distance . 'px !important; top: ' . $distance
                    . 'px !important; bottom: auto !important;';
            case 4:
                return 'left: ' . $distance . 'px !important; right: auto !important; top: ' . $distance
                    . 'px !important; bottom: auto !important;';
            case 1:
            default:
                return 'left: ' . $distance . 'px !important; right: auto !important; bottom: ' . $distance
                    . 'px !important; top: auto !important;';
        }
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

    public function instance_create()
    {
        global $DB, $USER;
        $config = get_config('block_ai_assistant');

        $availability = cria::get_availability();
        if ($availability->exception == 'success') {
            if (!$course_record = $DB->get_record('block_aia_settings', array('courseid' => $this->page->course->id))) {
                $record = new stdClass();
                $record->courseid = $this->page->course->id;
                $record->blockid = $this->instance->id;
                $created = cria::create_bot_instance($this->page->course->id);
                $bot_name = $created;
                $bot_id = 0;
                $bot_api_key = '';
                $decoded = json_decode((string)$created, true);
                if (is_array($decoded) && isset($decoded['name'])) {
                    $bot_name = (string)$decoded['name'];
                    $bot_id = (int)($decoded['bot_id'] ?? 0);
                    $bot_api_key = (string)($decoded['bot_api_key'] ?? '');
                }

                $record->bot_name = $bot_name;
                if ($bot_id > 0) {
                    $record->bot_id = $bot_id;
                }
                if ($bot_api_key !== '') {
                    $record->bot_api_key = $bot_api_key;
                }
                $record->no_context_message = $config->no_context_message;
                $record->subtitle = $config->subtitle;
                $record->welcome_message = $config->welcome_message;
                $record->lang = $config->default_language;
                $record->published = 0;
                $record->publish_tutorials = 0;
                $record->usermodified = $USER->id;
                $record->timecreated = time();
                $record->timemodified = time();
                $DB->insert_record('block_aia_settings', $record);
                $small_talk = cria::create_small_talk_questions($this->page->course->id);
            }
        }
    }

    public function instance_delete()
    {
        global $COURSE, $DB;
        // Get settings record
        $settings = $DB->get_record('block_aia_settings', array('courseid' => $COURSE->id));
        if (!empty($settings->bot_name)) {
            $blockcfg = get_config('block_ai_assistant');
            $localcfg = get_config('local_cria');
            $has_criabot = (!empty($blockcfg->criabot_url) || !empty($localcfg->criabot_url));

            if ($has_criabot) {
                $bot_name = (string)$settings->bot_name;
                $decoded = json_decode($bot_name, true);
                if (is_array($decoded) && isset($decoded['name'])) {
                    $bot_name = (string)$decoded['name'];
                }
                $api_key = !empty($localcfg->criadex_api_key) ? (string)$localcfg->criadex_api_key : (string)($blockcfg->criadex_api_key ?? '');
                $criabot_url = !empty($localcfg->criabot_url) ? rtrim((string)$localcfg->criabot_url, '/') : rtrim((string)($blockcfg->criabot_url ?? ''), '/');

                if ($api_key !== '' && $criabot_url !== '') {
                    require_once($GLOBALS['CFG']->libdir . '/filelib.php');
                    $curl = new \curl();
                    $curl->post($criabot_url . '/bots/' . rawurlencode($bot_name) . '/manage/delete', '', [
                        'CURLOPT_TIMEOUT' => 60,
                        'CURLOPT_CUSTOMREQUEST' => 'DELETE',
                        'CURLOPT_HTTPHEADER' => [
                            'Accept: application/json',
                            'X-API-Key: ' . $api_key,
                        ],
                    ]);
                }
            } else if (!empty($settings->bot_id)) {
                cria::delete_bot_instance((int)$settings->bot_id);
            }
        }

        // Delete all settings for this course
        $DB->delete_records('block_aia_settings', array('courseid' => $COURSE->id));
        // Delete Autotest questions
        $DB->delete_records('block_aia_autotest', array('courseid' => $COURSE->id));
        // Questions
        $DB->delete_records('block_aia_question_files', array('courseid' => $COURSE->id));
        // Delete course modules
        $DB->delete_records('block_aia_course_modules', array('courseid' => $COURSE->id));
        // Delete tutorials
        $DB->delete_records('block_aia_tutorials', array('courseid' => $COURSE->id));
        // Get all chats for this course
        $chats = $DB->get_records('block_aia_tutorial_chats', array('courseid' => $COURSE->id));
        foreach ($chats as $chat) {
            // Delete the chat
            $DB->delete_records('block_aia_chat_history', array('tutorialchatid' => $chat->id));
            // Delete the chat messages
            $DB->delete_records('block_aia_tutorial_chats', array('id' => $chat->id));
        }
        // Delete all chats for this course
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
