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

defined('MOODLE_INTERNAL') || die();

/**
 * The task that provides all the steps to perform a complete backup is defined here.
 *
 * @package     block_ai_assistant
 * @category    backup
 * @copyright   2022 UIT Innovation  <thibaud@yorku.ca>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// More information about the backup process: {@link https://docs.moodle.org/dev/Backup_API}.
// More information about the restore process: {@link https://docs.moodle.org/dev/Restore_API}.

require_once($CFG->dirroot.'//blocks/ai_assistant/backup/moodle2/backup_ai_assistant_stepslib.php');

/**
 * Provides all the settings and steps to perform a complete backup of block_ai_assistant.
 */
class backup_ai_assistant_block_task extends backup_block_task {

    /**
     * Defines particular settings for the plugin.
     */
    protected function define_my_settings() {
        return;
    }

    /**
     * Defines particular steps for the backup process.
     */
    protected function define_my_steps() {
        return;
    }

    /**
     * Returns the array of file area names within the block context.
     *
     * @return string[] File area names.
     */
    public function get_fileareas() {
        return array();
    }

    /**
     * Returns the config elements that must be processed before they are stored for backup.
     *
     * @return string[] Config elements.
     */
    public function get_configdata_encoded_attributes() {
        return array();
    }

    /**
     * Codes the transformations to perform in the block in order to get transportable (encoded) links.
     *
     * @param string $content
     * @return string
     */
    public static function encode_content_links($content) {
        return $content;
    }
}
