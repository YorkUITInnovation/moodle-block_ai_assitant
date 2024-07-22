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

    if ($oldversion < 2024070803) {

        // Define table block_aia_settings to be created.
        $table = new xmldb_table('block_aia_settings');

        // Adding fields to table block_aia_settings.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('blockid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('published', XMLDB_TYPE_INTEGER, '1', null, null, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('bot_name', XMLDB_TYPE_CHAR, '10', null, null, null, '0');

        // Adding keys to table block_aia_settings.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        // Conditionally launch create table for block_aia_settings.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Ai_assistant savepoint reached.
        upgrade_block_savepoint(true, 2024070803, 'ai_assistant');
    }


    if ($oldversion < 2024070804) {

        // Define table block_aia_questions to be created.
        $table = new xmldb_table('block_aia_questions');

        // Adding fields to table block_aia_questions.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('value', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('answer', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('criaquestionid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table block_aia_questions.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for block_aia_questions.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Ai_assistant savepoint reached.
        upgrade_block_savepoint(true, 2024070804, 'ai_assistant');
    }
    if ($oldversion < 2024071609) {

        // Define field welcome_message to be added to block_aia_settings.
        $table = new xmldb_table('block_aia_settings');
        $field = new xmldb_field('welcome_message', XMLDB_TYPE_CHAR, '100', null, null, null, '0');

        // Conditionally launch add field welcome_message.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field no_context_message to be added to block_aia_settings.
        $field = new xmldb_field('no_context_message', XMLDB_TYPE_CHAR, '100', null, null, null, '0');

        // Conditionally launch add field no_context_message.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field subtitle to be added to block_aia_settings.
        $field = new xmldb_field('subtitle', XMLDB_TYPE_CHAR, '10', null, null, null, '0');

        // Conditionally launch add field subtitle.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field cria_file_id to be added to block_aia_settings.
        $field = new xmldb_field('cria_file_id', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');

        // Conditionally launch add field cria_file_id.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Ai_assistant savepoint reached.
        upgrade_block_savepoint(true, 2024071609, 'ai_assistant');
    }
    if ($oldversion < 2024071612) {

        // Define field subtitle to be modified in block_aia_settings.
        $table = new xmldb_table('block_aia_settings');
        $field = new xmldb_field('subtitle', XMLDB_TYPE_CHAR, '50', null, null, null, '0');

        // Conditionally launch alter field subtitle.
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }

        // Ai_assistant savepoint reached.
        upgrade_block_savepoint(true, 2024071612, 'ai_assistant');
    }

    return true;

    if ($oldversion < 2024072201) {

        // Define field related_questions to be added to block_aia_questions.
        $table = new xmldb_table('block_aia_questions');
        $field = new xmldb_field('related_questions', XMLDB_TYPE_TEXT, null, null, null, null, null, 'criaquestionid');

        // Conditionally launch add field related_questions.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Ai_assistant savepoint reached.
        upgrade_block_savepoint(true, 2024072201, 'ai_assistant');
    }
}
