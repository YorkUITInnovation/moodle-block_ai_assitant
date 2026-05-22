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
 * Plugin upgrade steps are defined here.
 *
 * @package     block_ai_assistant
 * @category    upgrade
 * @copyright   2022 UIT Innovation  <thibaud@yorku.ca>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute block_ai_assistant upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_block_ai_assistant_upgrade($oldversion)
{
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026031600) {
        $table = new xmldb_table('block_aia_settings');

        $field = new xmldb_field('bot_name', XMLDB_TYPE_CHAR, '255', null, null, null, '0', 'publish_tutorials');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }

        $field = new xmldb_field('bot_id', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'bot_name');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('syllabus_document_name', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'cria_file_id');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('syllabus_trained', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'syllabus_document_name');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_block_savepoint(true, 2026031600, 'ai_assistant');
    }

    if ($oldversion < 2025072905) {

        // Define field publish_tutorials to be added to block_aia_settings.
        $table = new xmldb_table('block_aia_settings');
        $field = new xmldb_field('publish_tutorials', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'published');

        // Conditionally launch add field publish_tutorials.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define table block_aia_course_mod_files to be created.
        $table = new xmldb_table('block_aia_course_mod_files');

        // Adding fields to table block_aia_course_mod_files.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('bacmid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('cria_fileid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('trained', XMLDB_TYPE_INTEGER, '1', null, null, null, '0');
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, null);

        // Adding keys to table block_aia_course_mod_files.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table block_aia_course_mod_files.
        $table->add_index('xbacmid', XMLDB_INDEX_NOTUNIQUE, ['bacmid']);

        // Conditionally launch create table for block_aia_course_mod_files.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table block_aia_tutorials to be created.
        $table = new xmldb_table('block_aia_tutorials');

        // Adding fields to table block_aia_tutorials.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, '1');
        $table->add_field('blockid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('prompt', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, null, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table block_aia_tutorials.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        // Adding indexes to table block_aia_tutorials.
        $table->add_index('courseid_x', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        $table->add_index('blockid_x', XMLDB_INDEX_NOTUNIQUE, ['blockid']);
        $table->add_index('enabled_x', XMLDB_INDEX_NOTUNIQUE, ['enabled']);

        // Conditionally launch create table for block_aia_tutorials.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table block_aia_tutorial_chats to be created.
        $table = new xmldb_table('block_aia_tutorial_chats');

        // Adding fields to table block_aia_tutorial_chats.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('blockid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('tutorialid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('chatid', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('name', XMLDB_TYPE_CHAR, '1000', null, null, null, null);
        $table->add_field('history', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table block_aia_tutorial_chats.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table block_aia_tutorial_chats.
        $table->add_index('course_user_x', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'userid']);
        $table->add_index('courseid_x', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        $table->add_index('blockid_x', XMLDB_INDEX_NOTUNIQUE, ['blockid']);
        $table->add_index('tutorialid_x', XMLDB_INDEX_NOTUNIQUE, ['tutorialid']);
        $table->add_index('chatid_x', XMLDB_INDEX_NOTUNIQUE, ['chatid']);
        $table->add_index('userid_x', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('cmid_x', XMLDB_INDEX_NOTUNIQUE, ['cmid']);
        $table->add_index('changemecourse_tut_cm_x', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'tutorialid', 'cmid', 'userid']);

        // Conditionally launch create table for block_aia_tutorial_chats.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table block_aia_chat_history to be created.
        $table = new xmldb_table('block_aia_chat_history');

        // Adding fields to table block_aia_chat_history.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('tutorialchatid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('is_human', XMLDB_TYPE_INTEGER, '1', null, null, null, '0');
        $table->add_field('message', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '16', null, null, null, '0');

        // Adding keys to table block_aia_chat_history.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table block_aia_chat_history.
        $table->add_index('tutorialchatid_x', XMLDB_INDEX_NOTUNIQUE, ['tutorialchatid']);

        // Conditionally launch create table for block_aia_chat_history.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $DB->execute("TRUNCATE TABLE {block_aia_tutorials}"); // Clear existing tutorials.

        $tutor_params = [
            'courseid' => 1,
            'name' => get_string('tutorial_tutor_name', 'block_ai_assistant'),
            'description' => get_string('tutorial_tutor_description', 'block_ai_assistant'),
            'prompt' => get_string('tutorial_tutor_prompt', 'block_ai_assistant'),
            'enabled' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        $DB->insert_record('block_aia_tutorials', (object)$tutor_params);

        $quiz_params = [
            'courseid' => 1,
            'name' => get_string('tutorial_quiz_name', 'block_ai_assistant'),
            'description' => get_string('tutorial_quiz_description', 'block_ai_assistant'),
            'prompt' => get_string('tutorial_quiz_prompt', 'block_ai_assistant'),
            'enabled' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $DB->insert_record('block_aia_tutorials', (object)$quiz_params);

        // Ai_assistant savepoint reached.
        upgrade_block_savepoint(true, 2025072905, 'ai_assistant');
    }

    if ($oldversion < 2026042001) {
        // Define table block_aia_gradebook_state to be created.
        $table = new xmldb_table('block_aia_gradebook_state');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('session_id', XMLDB_TYPE_CHAR, '128', null, null, null, null);
        $table->add_field('phase', XMLDB_TYPE_CHAR, '32', null, null, null, null);
        $table->add_field('chat_history_json', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('confirmed_mapping_json', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('result_json', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('course_user_x', XMLDB_INDEX_UNIQUE, ['courseid', 'userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2026042001, 'ai_assistant');
    }

    if ($oldversion < 2026052102) {
        $table = new xmldb_table('block_aia_gradebook_state');

        $chatfield = new xmldb_field('chat_history_json', XMLDB_TYPE_TEXT, 'big', null, null, null, null);
        if ($dbman->field_exists($table, $chatfield)) {
            $dbman->change_field_type($table, $chatfield);
        }

        $mappingfield = new xmldb_field('confirmed_mapping_json', XMLDB_TYPE_TEXT, 'big', null, null, null, null);
        if ($dbman->field_exists($table, $mappingfield)) {
            $dbman->change_field_type($table, $mappingfield);
        }

        $resultfield = new xmldb_field('result_json', XMLDB_TYPE_TEXT, 'big', null, null, null, null);
        if ($dbman->field_exists($table, $resultfield)) {
            $dbman->change_field_type($table, $resultfield);
        }

        upgrade_block_savepoint(true, 2026052102, 'ai_assistant');
    }

    return true;

}
