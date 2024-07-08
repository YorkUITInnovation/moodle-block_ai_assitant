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
 * Plugin administration pages are defined here.
 *
 * @package     block_ai_assistant
 * @category    admin
 * @copyright   2022 UIT Innovation  <thibaud@yorku.ca>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('block_ai_assistant_settings', new lang_string('pluginname', 'block_ai_assistant'));

    //Bot Server
    $settings->add(new admin_setting_configtext(
        'block_ai_assistant/cria_url',
        get_string('cria_url', 'block_ai_assistant'),
        get_string('cria_url_help', 'block_ai_assistant'),
        '',
        PARAM_TEXT,
        115
    ));
    $settings->add(new admin_setting_configtext(
        'block_ai_assistant/cria_token',
        get_string('cria_token', 'block_ai_assistant'),
        get_string('cria_token_help', 'block_ai_assistant'),
        '',
        PARAM_TEXT,
        10
    ));
    $settings->add(new admin_setting_configtext(
        'block_ai_assistant/criadex_model_id',
        get_string('criadex_model_id', 'block_ai_assistant'),
        get_string('criadex_model_id_help', 'block_ai_assistant'),
        '',
        PARAM_TEXT,
        10
    ));
    $settings->add(new admin_setting_configtext(
        'block_ai_assistant/criadex_embed_id',
        get_string('criadex_embed_id', 'block_ai_assistant'),
        get_string('criadex_embed_id_help', 'block_ai_assistant'),
        '',
        PARAM_TEXT,
        10
    ));
    $settings->add(new admin_setting_configtext(
        'block_ai_assistant/criadex_rerank_id',
        get_string('criadex_rerank_id', 'block_ai_assistant'),
        get_string('criadex_rerank_id_help', 'block_ai_assistant'),
        '',
        PARAM_TEXT,
        10
    ));
    // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
    if ($ADMIN->fulltree) {
        // TODO: Define actual plugin settings page and add it to the tree - {@link https://docs.moodle.org/dev/Admin_settings}.
    }
}
