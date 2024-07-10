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
        'block_ai_assistant/criadex_embed_id',
        get_string('criadex_embed_id', 'block_ai_assistant'),
        get_string('criadex_embed_id_help', 'block_ai_assistant'),
        2,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_ai_assistant/criadex_model_id',
        get_string('criadex_model_id', 'block_ai_assistant'),
        get_string('criadex_model_id_help', 'block_ai_assistant'),
        3,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_ai_assistant/criadex_rerank_id',
        get_string('criadex_rerank_id', 'block_ai_assistant'),
        get_string('criadex_rerank_id_help', 'block_ai_assistant'),
        1,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtextarea(
        'block_ai_assistant/no_context_message',
        get_string('no_context_message', 'block_ai_assistant'),
        get_string('no_context_message_help', 'block_ai_assistant'),
        get_string('no_context_message_default', 'block_ai_assistant'),
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtextarea(
        'block_ai_assistant/system_message',
        get_string('system_message', 'block_ai_assistant'),
        get_string('system_message_help', 'block_ai_assistant'),
        get_string('system_message_default', 'block_ai_assistant'),
        PARAM_TEXT
    ));
    $settings->add(new admin_setting_configtextarea(
        'block_ai_assistant/welcome_message',
        get_string('welcome_message', 'block_ai_assistant'),
        get_string('welcome_message_help', 'block_ai_assistant'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'block_ai_assistant/title',
        get_string('title', 'block_ai_assistant'),
        get_string('title_help', 'block_ai_assistant'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'block_ai_assistant/subtitle',
        get_string('subtitle', 'block_ai_assistant'),
        get_string('subtitle_help', 'block_ai_assistant'),
        '',
        PARAM_TEXT
    ));


    // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
    if ($ADMIN->fulltree) {
        // TODO: Define actual plugin settings page and add it to the tree - {@link https://docs.moodle.org/dev/Admin_settings}.
    }
}

set_config('description', '', 'block_ai_assistant');
set_config('bot_type', 1, 'block_ai_assistant');
set_config('requires_content_prompt', 0, 'block_ai_assistant');
set_config('requires_user_prompt', 0, 'block_ai_assistant');
set_config('user_prompt', '', 'block_ai_assistant');
set_config('theme_color', '#e31837', 'block_ai_assistant');
set_config('max_tokens', 4000, 'block_ai_assistant');
set_config('temperature', 0.1, 'block_ai_assistant');
set_config('top_p', 0.0, 'block_ai_assistant');
set_config('top_k', 30, 'block_ai_assistant');
set_config('top_n', 10, 'block_ai_assistant');
set_config('min_k', 0.6, 'block_ai_assistant');
set_config('min_relevance', 0.8, 'block_ai_assistant');
set_config('max_context', 120000, 'block_ai_assistant');
set_config('no_context_use_message', 1, 'block_ai_assistant');
set_config('no_context_llm_guess', 0, 'block_ai_assistant');
set_config('email', '', 'block_ai_assistant');
set_config('available_child', 0, 'block_ai_assistant');
set_config('parse_strategy', 'ALSYLLABUS', 'block_ai_assistant');
set_config('botwatermark', 0, 'block_ai_assistant');
set_config('embed_position', 1, 'block_ai_assistant');
set_config('icon_url', '', 'block_ai_assistant');
set_config('bot_locale', 'en', 'block_ai_assistant');
set_config('child_bots', '', 'block_ai_assistant');
