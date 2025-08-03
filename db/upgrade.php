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


    if ($oldversion < 2025072905) {

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

    if ($oldversion < 2025080100) {

        // Define table ai_policy_register to be created.
        $table = new xmldb_table('ai_policy_register');

        // Adding fields to table ai_policy_register.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timeaccepted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table ai_policy_register.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN_UNIQUE, ['userid'], 'user', ['id']);

        // Conditionally launch create table for ai_policy_register.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Ai_assistant savepoint reached.
        upgrade_block_savepoint(true, 2025080100, 'ai_assistant');
    }



    return true;

}
