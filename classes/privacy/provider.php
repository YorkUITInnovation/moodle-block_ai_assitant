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

namespace block_ai_assistant\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementation for block_ai_assistant.
 *
 * @package     block_ai_assistant
 * @copyright   2026 Carlos Arce <carlosarcelopera@catalyst-ca.net>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Return the fields which contain personal data.
     *
     * @param collection $collection a reference to the collection to use to store the metadata.
     * @return collection the updated collection of metadata items.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'block_aia_settings',
            [
                'blockid' => 'privacy:metadata:block_aia_settings:blockid',
                'courseid' => 'privacy:metadata:block_aia_settings:courseid',
                'bot_name' => 'privacy:metadata:block_aia_settings:bot_name',
                'bot_help_text' => 'privacy:metadata:block_aia_settings:bot_help_text',
                'bot_contact' => 'privacy:metadata:block_aia_settings:bot_contact',
                'subtitle' => 'privacy:metadata:block_aia_settings:subtitle',
                'welcome_message' => 'privacy:metadata:block_aia_settings:welcome_message',
                'no_context_message' => 'privacy:metadata:block_aia_settings:no_context_message',
                'usermodified' => 'privacy:metadata:block_aia_settings:usermodified',
                'timecreated' => 'privacy:metadata:block_aia_settings:timecreated',
                'timemodified' => 'privacy:metadata:block_aia_settings:timemodified',
            ],
            'privacy:metadata:block_aia_settings'
        );

        $collection->add_database_table(
            'block_aia_autotest',
            [
                'courseid' => 'privacy:metadata:block_aia_autotest:courseid',
                'section' => 'privacy:metadata:block_aia_autotest:section',
                'questions' => 'privacy:metadata:block_aia_autotest:questions',
                'human_answer' => 'privacy:metadata:block_aia_autotest:human_answer',
                'bot_answer' => 'privacy:metadata:block_aia_autotest:bot_answer',
                'usermodified' => 'privacy:metadata:block_aia_autotest:usermodified',
                'timecreated' => 'privacy:metadata:block_aia_autotest:timecreated',
                'timemodified' => 'privacy:metadata:block_aia_autotest:timemodified',
            ],
            'privacy:metadata:block_aia_autotest'
        );

        $collection->add_database_table(
            'block_aia_question_files',
            [
                'courseid' => 'privacy:metadata:block_aia_question_files:courseid',
                'name' => 'privacy:metadata:block_aia_question_files:name',
                'usermodified' => 'privacy:metadata:block_aia_question_files:usermodified',
                'timecreated' => 'privacy:metadata:block_aia_question_files:timecreated',
                'timemodified' => 'privacy:metadata:block_aia_question_files:timemodified',
            ],
            'privacy:metadata:block_aia_question_files'
        );

        $collection->add_database_table(
            'block_aia_course_modules',
            [
                'courseid' => 'privacy:metadata:block_aia_course_modules:courseid',
                'cmid' => 'privacy:metadata:block_aia_course_modules:cmid',
                'modname' => 'privacy:metadata:block_aia_course_modules:modname',
                'trained' => 'privacy:metadata:block_aia_course_modules:trained',
                'usermodified' => 'privacy:metadata:block_aia_course_modules:usermodified',
                'timecreated' => 'privacy:metadata:block_aia_course_modules:timecreated',
                'timemodified' => 'privacy:metadata:block_aia_course_modules:timemodified',
            ],
            'privacy:metadata:block_aia_course_modules'
        );

        $collection->add_database_table(
            'block_aia_tutorials',
            [
                'courseid' => 'privacy:metadata:block_aia_tutorials:courseid',
                'name' => 'privacy:metadata:block_aia_tutorials:name',
                'description' => 'privacy:metadata:block_aia_tutorials:description',
                'prompt' => 'privacy:metadata:block_aia_tutorials:prompt',
                'enabled' => 'privacy:metadata:block_aia_tutorials:enabled',
                'usermodified' => 'privacy:metadata:block_aia_tutorials:usermodified',
                'timecreated' => 'privacy:metadata:block_aia_tutorials:timecreated',
                'timemodified' => 'privacy:metadata:block_aia_tutorials:timemodified',
            ],
            'privacy:metadata:block_aia_tutorials'
        );

        $collection->add_database_table(
            'block_aia_tutorial_chats',
            [
                'courseid' => 'privacy:metadata:block_aia_tutorial_chats:courseid',
                'tutorialid' => 'privacy:metadata:block_aia_tutorial_chats:tutorialid',
                'chatid' => 'privacy:metadata:block_aia_tutorial_chats:chatid',
                'userid' => 'privacy:metadata:block_aia_tutorial_chats:userid',
                'cmid' => 'privacy:metadata:block_aia_tutorial_chats:cmid',
                'name' => 'privacy:metadata:block_aia_tutorial_chats:name',
                'history' => 'privacy:metadata:block_aia_tutorial_chats:history',
                'timecreated' => 'privacy:metadata:block_aia_tutorial_chats:timecreated',
                'timemodified' => 'privacy:metadata:block_aia_tutorial_chats:timemodified',
            ],
            'privacy:metadata:block_aia_tutorial_chats'
        );

        $collection->add_database_table(
            'block_aia_chat_history',
            [
                'userid' => 'privacy:metadata:block_aia_chat_history:userid',
                'is_human' => 'privacy:metadata:block_aia_chat_history:is_human',
                'message' => 'privacy:metadata:block_aia_chat_history:message',
                'timecreated' => 'privacy:metadata:block_aia_chat_history:timecreated',
            ],
            'privacy:metadata:block_aia_chat_history'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user data for the specified user.
     *
     * @param int $userid the user id.
     * @return contextlist the list of contexts containing user data.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        if (self::user_has_system_data($userid)) {
            $contextlist->add_system_context();
        }

        // Course-level contexts: settings/tutorials/chats/autotest/modules with courseid > 0.
        $sql = "
            SELECT DISTINCT ctx.id AS contextid
            FROM {block_aia_settings} s
            JOIN {context} ctx ON ctx.instanceid = s.courseid AND ctx.contextlevel = :cl1
            WHERE s.usermodified = :u1 AND s.courseid > 0
            UNION
            SELECT DISTINCT ctx.id
            FROM {block_aia_tutorials} t
            JOIN {context} ctx ON ctx.instanceid = t.courseid AND ctx.contextlevel = :cl2
            WHERE t.usermodified = :u2 AND t.courseid > 0
            UNION
            SELECT DISTINCT ctx.id
            FROM {block_aia_chat_history} ch
            JOIN {block_aia_tutorial_chats} tc ON ch.tutorialchatid = tc.id
            JOIN {context} ctx ON ctx.instanceid = tc.courseid AND ctx.contextlevel = :cl3
            WHERE ch.userid = :u3
            UNION
            SELECT DISTINCT ctx.id
            FROM {block_aia_autotest} a
            JOIN {context} ctx ON ctx.instanceid = a.courseid AND ctx.contextlevel = :cl4
            WHERE a.usermodified = :u4 AND a.courseid > 0
            UNION
            SELECT DISTINCT ctx.id
            FROM {block_aia_course_modules} cm
            JOIN {context} ctx ON ctx.instanceid = cm.courseid AND ctx.contextlevel = :cl5
            WHERE cm.usermodified = :u5 AND cm.courseid > 0
            UNION
            SELECT DISTINCT ctx.id
            FROM {block_aia_question_files} qf
            JOIN {context} ctx ON ctx.instanceid = qf.courseid AND ctx.contextlevel = :cl6
            WHERE qf.usermodified = :u6 AND qf.courseid > 0
        ";
        $contextlist->add_from_sql($sql, [
            'cl1' => CONTEXT_COURSE,
            'cl2' => CONTEXT_COURSE,
            'cl3' => CONTEXT_COURSE,
            'cl4' => CONTEXT_COURSE,
            'cl5' => CONTEXT_COURSE,
            'cl6' => CONTEXT_COURSE,
            'u1' => $userid,
            'u2' => $userid,
            'u3' => $userid,
            'u4' => $userid,
            'u5' => $userid,
            'u6' => $userid,
        ]);

        // Block instance contexts: settings with blockid > 0.
        $sql = "
            SELECT DISTINCT ctx.id AS contextid
            FROM {block_aia_settings} s
            JOIN {context} ctx ON ctx.instanceid = s.blockid AND ctx.contextlevel = :cl
            WHERE s.usermodified = :userid AND s.blockid > 0
        ";
        $contextlist->add_from_sql($sql, [
            'cl' => CONTEXT_BLOCK,
            'userid' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Get the list of users within a specific context.
     *
     * @param userlist $userlist the userlist to add users to.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if ($context->contextlevel == CONTEXT_SYSTEM) {
            // Get users who modified system-level settings and tutorials (courseid=0 AND blockid=0).
            $sql = "SELECT DISTINCT usermodified AS userid FROM {block_aia_settings}
                    WHERE usermodified != 0 AND courseid = 0 AND blockid = 0
                    UNION
                    SELECT DISTINCT usermodified AS userid FROM {block_aia_tutorials}
                    WHERE usermodified != 0 AND courseid = 0";

            $userlist->add_from_sql('userid', $sql, []);
        } else if ($context->contextlevel == CONTEXT_COURSE) {
            // Get users with course-level data.
            $courseid = $context->instanceid;

            $sql = "SELECT DISTINCT usermodified AS userid FROM {block_aia_settings}
                    WHERE courseid = :c1 AND usermodified != 0
                    UNION
                    SELECT DISTINCT usermodified AS userid FROM {block_aia_tutorials}
                    WHERE courseid = :c2 AND usermodified != 0
                    UNION
                    SELECT DISTINCT userid FROM {block_aia_chat_history}
                    WHERE userid != 0
                    AND tutorialchatid IN (SELECT id FROM {block_aia_tutorial_chats} WHERE courseid = :c3)
                    UNION
                    SELECT DISTINCT userid FROM {block_aia_tutorial_chats}
                    WHERE courseid = :c4 AND userid != 0
                    UNION
                    SELECT DISTINCT usermodified AS userid FROM {block_aia_autotest}
                    WHERE courseid = :c5 AND usermodified != 0
                    UNION
                    SELECT DISTINCT usermodified AS userid FROM {block_aia_question_files}
                    WHERE courseid = :c6 AND usermodified != 0
                    UNION
                    SELECT DISTINCT usermodified AS userid FROM {block_aia_course_modules}
                    WHERE courseid = :c7 AND usermodified != 0";

            $userlist->add_from_sql('userid', $sql, [
                'c1' => $courseid,
                'c2' => $courseid,
                'c3' => $courseid,
                'c4' => $courseid,
                'c5' => $courseid,
                'c6' => $courseid,
                'c7' => $courseid,
            ]);
        } else if ($context->contextlevel == CONTEXT_BLOCK) {
            // Get users who modified block-level settings.
            $blockid = $context->instanceid;

            $sql = "SELECT DISTINCT usermodified AS userid FROM {block_aia_settings}
                    WHERE blockid = :blockid AND usermodified != 0";

            $userlist->add_from_sql('userid', $sql, ['blockid' => $blockid]);
        }
    }

    /**
     * Export all user data for the specified user within the contexts.
     *
     * @param approved_contextlist $contextlist the approved contexts list.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                self::export_system_data($userid, $context, $DB);
            } else if ($context->contextlevel == CONTEXT_COURSE) {
                self::export_course_data($userid, $context, $DB);
            } else if ($context->contextlevel == CONTEXT_BLOCK) {
                self::export_block_data($userid, $context, $DB);
            }
        }
    }

    /**
     * Export system-level data (settings, tutorials) for the user.
     *
     * @param int $userid the user id.
     * @param \context $context the context.
     * @param \moodle_database $db database instance.
     */
    private static function export_system_data(int $userid, \context $context, \moodle_database $db) {
        // Export settings modified by user at system level only (courseid=0 AND blockid=0).
        $sql = "SELECT blockid, courseid, bot_name, bot_help_text, bot_contact, subtitle,
                       welcome_message, no_context_message, timecreated, timemodified
                FROM {block_aia_settings}
                WHERE usermodified = :userid AND courseid = 0 AND blockid = 0
                ORDER BY id";
        $records = $db->get_records_sql($sql, ['userid' => $userid]);

        if (!empty($records)) {
            $data = [];
            foreach ($records as $record) {
                $data[] = (object) [
                    'bot_name' => $record->bot_name,
                    'bot_help_text' => $record->bot_help_text,
                    'bot_contact' => $record->bot_contact,
                    'subtitle' => $record->subtitle,
                    'welcome_message' => $record->welcome_message,
                    'no_context_message' => $record->no_context_message,
                    'timecreated' => \core_privacy\local\request\transform::datetime($record->timecreated),
                    'timemodified' => \core_privacy\local\request\transform::datetime($record->timemodified),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'block_ai_assistant')],
                (object) ['ai_assistant_settings' => $data]
            );
        }

        // Export tutorials modified by user at system level only (courseid=0).
        $sql = "SELECT courseid, blockid, name, description, prompt, enabled, timecreated, timemodified
                FROM {block_aia_tutorials}
                WHERE usermodified = :userid AND courseid = 0
                ORDER BY id";
        $records = $db->get_records_sql($sql, ['userid' => $userid]);

        if (!empty($records)) {
            $data = [];
            foreach ($records as $record) {
                $data[] = (object) [
                    'name' => $record->name,
                    'description' => $record->description,
                    'prompt' => $record->prompt,
                    'enabled' => \core_privacy\local\request\transform::yesno($record->enabled),
                    'timecreated' => \core_privacy\local\request\transform::datetime($record->timecreated),
                    'timemodified' => \core_privacy\local\request\transform::datetime($record->timemodified),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'block_ai_assistant')],
                (object) ['ai_assistant_tutorials' => $data]
            );
        }
    }

    /**
     * Export course-level data for the user in the specified course.
     *
     * @param int $userid the user id.
     * @param \context $context the course context.
     * @param \moodle_database $db database instance.
     */
    private static function export_course_data(int $userid, \context $context, \moodle_database $db) {
        $courseid = $context->instanceid;

        // Export settings modified by user in this course (courseid > 0).
        $sql = "SELECT blockid, courseid, bot_name, bot_help_text, bot_contact, subtitle,
                       welcome_message, no_context_message, timecreated, timemodified
                FROM {block_aia_settings}
                WHERE usermodified = :userid AND courseid = :courseid
                ORDER BY id";
        $records = $db->get_records_sql($sql, ['userid' => $userid, 'courseid' => $courseid]);

        if (!empty($records)) {
            $data = [];
            foreach ($records as $record) {
                $data[] = (object) [
                    'bot_name' => $record->bot_name,
                    'bot_help_text' => $record->bot_help_text,
                    'bot_contact' => $record->bot_contact,
                    'subtitle' => $record->subtitle,
                    'welcome_message' => $record->welcome_message,
                    'no_context_message' => $record->no_context_message,
                    'timecreated' => \core_privacy\local\request\transform::datetime($record->timecreated),
                    'timemodified' => \core_privacy\local\request\transform::datetime($record->timemodified),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'block_ai_assistant')],
                (object) ['ai_assistant_settings' => $data]
            );
        }

        // Export tutorials in this course modified by user (courseid > 0).
        $sql = "SELECT blockid, name, description, prompt, enabled, timecreated, timemodified
                FROM {block_aia_tutorials}
                WHERE courseid = :courseid AND usermodified = :userid
                ORDER BY id";
        $records = $db->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);

        if (!empty($records)) {
            $data = [];
            foreach ($records as $record) {
                $data[] = (object) [
                    'name' => $record->name,
                    'description' => $record->description,
                    'prompt' => $record->prompt,
                    'enabled' => \core_privacy\local\request\transform::yesno($record->enabled),
                    'timecreated' => \core_privacy\local\request\transform::datetime($record->timecreated),
                    'timemodified' => \core_privacy\local\request\transform::datetime($record->timemodified),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'block_ai_assistant')],
                (object) ['course_tutorials' => $data]
            );
        }

        // Export tutorial chats (user as participant).
        $sql = "SELECT tutorialid, chatid, cmid, name, history, timecreated, timemodified
                FROM {block_aia_tutorial_chats}
                WHERE courseid = :courseid AND userid = :userid
                ORDER BY id";
        $records = $db->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);

        if (!empty($records)) {
            $data = [];
            foreach ($records as $record) {
                $data[] = (object) [
                    'tutorialid' => $record->tutorialid,
                    'chatid' => $record->chatid,
                    'cmid' => $record->cmid,
                    'name' => $record->name,
                    'history' => $record->history,
                    'timecreated' => \core_privacy\local\request\transform::datetime($record->timecreated),
                    'timemodified' => \core_privacy\local\request\transform::datetime($record->timemodified),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'block_ai_assistant')],
                (object) ['tutorial_chats' => $data]
            );
        }

        // Export chat history messages (individual messages).
        $sql = "SELECT ch.userid, ch.is_human, ch.message, ch.timecreated
                FROM {block_aia_chat_history} ch
                JOIN {block_aia_tutorial_chats} tc ON ch.tutorialchatid = tc.id
                WHERE tc.courseid = :courseid AND ch.userid = :userid
                ORDER BY ch.id";
        $records = $db->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);

        if (!empty($records)) {
            $data = [];
            foreach ($records as $record) {
                $data[] = (object) [
                    'is_human' => \core_privacy\local\request\transform::yesno($record->is_human),
                    'message' => $record->message,
                    'timecreated' => \core_privacy\local\request\transform::datetime($record->timecreated),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'block_ai_assistant')],
                (object) ['chat_history' => $data]
            );
        }

        // Export autotest records modified by user.
        $sql = "SELECT section, questions, human_answer, bot_answer, timecreated, timemodified
                FROM {block_aia_autotest}
                WHERE courseid = :courseid AND usermodified = :userid
                ORDER BY id";
        $records = $db->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);

        if (!empty($records)) {
            $data = [];
            foreach ($records as $record) {
                $data[] = (object) [
                    'section' => $record->section,
                    'questions' => $record->questions,
                    'human_answer' => $record->human_answer,
                    'bot_answer' => $record->bot_answer,
                    'timecreated' => \core_privacy\local\request\transform::datetime($record->timecreated),
                    'timemodified' => \core_privacy\local\request\transform::datetime($record->timemodified),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'block_ai_assistant')],
                (object) ['autotest_records' => $data]
            );
        }

        // Export question files modified by user.
        $sql = "SELECT name, timecreated, timemodified
                FROM {block_aia_question_files}
                WHERE courseid = :courseid AND usermodified = :userid
                ORDER BY id";
        $records = $db->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);

        if (!empty($records)) {
            $data = [];
            foreach ($records as $record) {
                $data[] = (object) [
                    'name' => $record->name,
                    'timecreated' => \core_privacy\local\request\transform::datetime($record->timecreated),
                    'timemodified' => \core_privacy\local\request\transform::datetime($record->timemodified),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'block_ai_assistant')],
                (object) ['question_files' => $data]
            );
        }

        // Export course module training data modified by user.
        $sql = "SELECT cmid, modname, trained, timecreated, timemodified
                FROM {block_aia_course_modules}
                WHERE courseid = :courseid AND usermodified = :userid
                ORDER BY id";
        $records = $db->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);

        if (!empty($records)) {
            $data = [];
            foreach ($records as $record) {
                $data[] = (object) [
                    'cmid' => $record->cmid,
                    'modname' => $record->modname,
                    'trained' => \core_privacy\local\request\transform::yesno($record->trained),
                    'timecreated' => \core_privacy\local\request\transform::datetime($record->timecreated),
                    'timemodified' => \core_privacy\local\request\transform::datetime($record->timemodified),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'block_ai_assistant')],
                (object) ['course_modules' => $data]
            );
        }
    }

    /**
     * Export block instance-level data for the user in the specified block.
     *
     * @param int $userid the user id.
     * @param \context $context the block context.
     * @param \moodle_database $db database instance.
     */
    private static function export_block_data(int $userid, \context $context, \moodle_database $db) {
        $blockid = $context->instanceid;

        // Export settings modified by user in this block instance (blockid > 0).
        $sql = "SELECT courseid, bot_name, bot_help_text, bot_contact, subtitle,
                       welcome_message, no_context_message, timecreated, timemodified
                FROM {block_aia_settings}
                WHERE usermodified = :userid AND blockid = :blockid
                ORDER BY id";
        $records = $db->get_records_sql($sql, ['userid' => $userid, 'blockid' => $blockid]);

        if (!empty($records)) {
            $data = [];
            foreach ($records as $record) {
                $data[] = (object) [
                    'bot_name' => $record->bot_name,
                    'bot_help_text' => $record->bot_help_text,
                    'bot_contact' => $record->bot_contact,
                    'subtitle' => $record->subtitle,
                    'welcome_message' => $record->welcome_message,
                    'no_context_message' => $record->no_context_message,
                    'timecreated' => \core_privacy\local\request\transform::datetime($record->timecreated),
                    'timemodified' => \core_privacy\local\request\transform::datetime($record->timemodified),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'block_ai_assistant')],
                (object) ['ai_assistant_settings' => $data]
            );
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context the context to delete data from.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel == CONTEXT_SYSTEM) {
            // Anonymize system-level records (courseid=0 AND blockid=0).
            $DB->set_field_select('block_aia_settings', 'usermodified', 0, 'courseid = 0 AND blockid = 0');
            $DB->set_field_select('block_aia_tutorials', 'usermodified', 0, 'courseid = 0');
        } else if ($context->contextlevel == CONTEXT_COURSE) {
            $courseid = $context->instanceid;

            // Delete chat history BEFORE tutorial chats to avoid orphaned records (foreign key order).
            $DB->delete_records_select(
                'block_aia_chat_history',
                'tutorialchatid IN (SELECT id FROM {block_aia_tutorial_chats} WHERE courseid = :courseid)',
                ['courseid' => $courseid]
            );

            // Delete user chat records.
            $DB->delete_records('block_aia_tutorial_chats', ['courseid' => $courseid]);

            // Anonymize course-specific configuration records.
            $DB->set_field_select('block_aia_settings', 'usermodified', 0, 'courseid = :courseid', ['courseid' => $courseid]);
            $DB->set_field_select('block_aia_tutorials', 'usermodified', 0, 'courseid = :courseid', ['courseid' => $courseid]);
            $DB->set_field_select('block_aia_autotest', 'usermodified', 0, 'courseid = :courseid', ['courseid' => $courseid]);
            $DB->set_field_select('block_aia_question_files', 'usermodified', 0, 'courseid = :courseid', ['courseid' => $courseid]);
            $DB->set_field_select('block_aia_course_modules', 'usermodified', 0, 'courseid = :courseid', ['courseid' => $courseid]);
        } else if ($context->contextlevel == CONTEXT_BLOCK) {
            $blockid = $context->instanceid;

            // Anonymize block instance settings.
            $DB->set_field_select('block_aia_settings', 'usermodified', 0, 'blockid = :blockid', ['blockid' => $blockid]);
        }
    }

    /**
     * Delete all user data for the specified users in the approved userlist.
     *
     * @param approved_userlist $userlist the list of users to delete data for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        $userids = $userlist->get_userids();

        if (empty($userids)) {
            return;
        }

        [$sql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        if ($context->contextlevel == CONTEXT_SYSTEM) {
            // Anonymize system-level records for these users (courseid=0 AND blockid=0).
            $DB->set_field_select(
                'block_aia_settings',
                'usermodified',
                0,
                "usermodified $sql AND courseid = 0 AND blockid = 0",
                $params
            );
            $DB->set_field_select('block_aia_tutorials', 'usermodified', 0, "usermodified $sql AND courseid = 0", $params);
        } else if ($context->contextlevel == CONTEXT_COURSE) {
            $courseid = $context->instanceid;

            // Delete chat history BEFORE tutorial chats (foreign key order).
            $DB->delete_records_select(
                'block_aia_chat_history',
                "userid $sql AND tutorialchatid IN (SELECT id FROM {block_aia_tutorial_chats} WHERE courseid = :courseid)",
                array_merge(['courseid' => $courseid], $params)
            );

            // Delete user chat records in this course.
            $DB->delete_records_select(
                'block_aia_tutorial_chats',
                "courseid = :courseid AND userid $sql",
                array_merge(['courseid' => $courseid], $params)
            );

            // Anonymize course-specific configuration records.
            $DB->set_field_select(
                'block_aia_settings',
                'usermodified',
                0,
                "courseid = :courseid AND usermodified $sql",
                array_merge(['courseid' => $courseid], $params)
            );
            $DB->set_field_select(
                'block_aia_tutorials',
                'usermodified',
                0,
                "courseid = :courseid AND usermodified $sql",
                array_merge(['courseid' => $courseid], $params)
            );
            $DB->set_field_select(
                'block_aia_autotest',
                'usermodified',
                0,
                "courseid = :courseid AND usermodified $sql",
                array_merge(['courseid' => $courseid], $params)
            );
            $DB->set_field_select(
                'block_aia_question_files',
                'usermodified',
                0,
                "courseid = :courseid AND usermodified $sql",
                array_merge(['courseid' => $courseid], $params)
            );
            $DB->set_field_select(
                'block_aia_course_modules',
                'usermodified',
                0,
                "courseid = :courseid AND usermodified $sql",
                array_merge(['courseid' => $courseid], $params)
            );
        } else if ($context->contextlevel == CONTEXT_BLOCK) {
            $blockid = $context->instanceid;

            // Anonymize block instance settings.
            $DB->set_field_select(
                'block_aia_settings',
                'usermodified',
                0,
                "blockid = :blockid AND usermodified $sql",
                array_merge(['blockid' => $blockid], $params)
            );
        }
    }

    /**
     * Delete all user data for the specified user in the approved contextlist.
     *
     * @param approved_contextlist $contextlist the list of contexts to delete data from.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                // Anonymize system-level records for this user (courseid=0 AND blockid=0).
                $DB->set_field_select(
                    'block_aia_settings',
                    'usermodified',
                    0,
                    'usermodified = :userid AND courseid = 0 AND blockid = 0',
                    ['userid' => $userid]
                );
                $DB->set_field_select(
                    'block_aia_tutorials',
                    'usermodified',
                    0,
                    'usermodified = :userid AND courseid = 0',
                    ['userid' => $userid]
                );
            } else if ($context->contextlevel == CONTEXT_COURSE) {
                $courseid = $context->instanceid;

                // Delete chat history BEFORE tutorial chats (foreign key order).
                $DB->delete_records_select(
                    'block_aia_chat_history',
                    'userid = :userid AND tutorialchatid IN (SELECT id FROM {block_aia_tutorial_chats} WHERE courseid = :courseid)',
                    ['userid' => $userid, 'courseid' => $courseid]
                );

                // Delete user chat records.
                $DB->delete_records('block_aia_tutorial_chats', ['courseid' => $courseid, 'userid' => $userid]);

                // Anonymize course-specific configuration records.
                $DB->set_field_select(
                    'block_aia_settings',
                    'usermodified',
                    0,
                    'courseid = :courseid AND usermodified = :userid',
                    ['courseid' => $courseid, 'userid' => $userid]
                );
                $DB->set_field_select(
                    'block_aia_tutorials',
                    'usermodified',
                    0,
                    'courseid = :courseid AND usermodified = :userid',
                    ['courseid' => $courseid, 'userid' => $userid]
                );
                $DB->set_field_select(
                    'block_aia_autotest',
                    'usermodified',
                    0,
                    'courseid = :courseid AND usermodified = :userid',
                    ['courseid' => $courseid, 'userid' => $userid]
                );
                $DB->set_field_select(
                    'block_aia_question_files',
                    'usermodified',
                    0,
                    'courseid = :courseid AND usermodified = :userid',
                    ['courseid' => $courseid, 'userid' => $userid]
                );
                $DB->set_field_select(
                    'block_aia_course_modules',
                    'usermodified',
                    0,
                    'courseid = :courseid AND usermodified = :userid',
                    ['courseid' => $courseid, 'userid' => $userid]
                );
            } else if ($context->contextlevel == CONTEXT_BLOCK) {
                $blockid = $context->instanceid;

                // Anonymize block instance settings.
                $DB->set_field_select(
                    'block_aia_settings',
                    'usermodified',
                    0,
                    'blockid = :blockid AND usermodified = :userid',
                    ['blockid' => $blockid, 'userid' => $userid]
                );
            }
        }
    }

    /**
     * Check if a user has system-level data (settings or tutorials at courseid=0, blockid=0).
     *
     * @param int $userid the user id.
     * @return bool true if user has system-level data, false otherwise.
     */
    private static function user_has_system_data(int $userid): bool {
        global $DB;

        $sql = "SELECT 1 FROM {block_aia_settings} WHERE usermodified = :u1 AND courseid = 0 AND blockid = 0
                UNION
                SELECT 1 FROM {block_aia_tutorials} WHERE usermodified = :u2 AND courseid = 0
                LIMIT 1";

        $result = $DB->get_record_sql($sql, ['u1' => $userid, 'u2' => $userid]);
        return $result !== false;
    }
}
