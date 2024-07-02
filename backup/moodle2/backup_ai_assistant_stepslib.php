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
 * Backup steps for block_ai_assistant are defined here.
 *
 * @package     block_ai_assistant
 * @category    backup
 * @copyright   2022 UIT Innovation  <thibaud@yorku.ca>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// More information about the backup process: {@link https://docs.moodle.org/dev/Backup_API}.
// More information about the restore process: {@link https://docs.moodle.org/dev/Restore_API}.

/**
 * Define the complete structure for backup, with file and id annotations.
 */
class backup_ai_assistant_block_structure_step extends backup_block_structure_step {

    /**
     * Defines the structure of the resulting xml file.
     *
     * @return backup_nested_element The structure wrapped in the block tag.
     */
    protected function define_structure() {
        global $DB;

        // Replace with the attributes and final elements that the element will handle.
        $attributes = null;
        $finalelements = null;
        $root = new backup_nested_element('block_ai_assistant', $attributes, $finalelements);

        // Replace with the attributes and final elements that the element will handle.
        $attributes = null;
        $finalelements = null;
        $al_assistant = new backup_nested_element('al_assistant', $attributes, $finalelements);

        // Build the tree with these elements with $root as the root of the backup tree.

        // Define the source tables for the elements.

        // Define id annotations.

        // Define file annotations.

        return $this->prepare_block_structure($root);
    }
}
