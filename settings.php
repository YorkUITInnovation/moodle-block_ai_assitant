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
        50
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
        'block_ai_assistant/cria_embed_url',
        get_string('cria_embed_url', 'block_ai_assistant'),
        get_string('cria_embed_url_help', 'block_ai_assistant'),
        '',
        PARAM_TEXT,
        50
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

    // Add a header
    $settings->add(new admin_setting_heading(
        'block_ai_assistant/bot_tuning',
        get_string('bot_tuning', 'block_ai_assistant'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'block_ai_assistant/max_tokens',
        get_string('max_tokens', 'block_ai_assistant'),
        '',
        4000,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_ai_assistant/temperature',
        get_string('temperature', 'block_ai_assistant'),
        '',
        0.9,
        PARAM_FLOAT
    ));

    $settings->add(new admin_setting_configtext(
        'block_ai_assistant/top_p',
        get_string('top_p', 'block_ai_assistant'),
        '',
        0.0,
        PARAM_FLOAT
    ));

    $settings->add(new admin_setting_configtext(
        'block_ai_assistant/top_k',
        get_string('top_k', 'block_ai_assistant'),
        '',
        500,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_ai_assistant/top_n',
        get_string('top_n', 'block_ai_assistant'),
        '',
        10,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_ai_assistant/min_k',
        get_string('min_k', 'block_ai_assistant'),
        '',
        0,
        PARAM_FLOAT
    ));

    $settings->add(new admin_setting_configtext(
        'block_ai_assistant/min_relevance',
        get_string('min_relevance', 'block_ai_assistant'),
        '',
        0,
        PARAM_FLOAT
    ));

    $settings->add(new admin_setting_configtext(
        'block_ai_assistant/max_context',
        get_string('max_context', 'block_ai_assistant'),
        '',
        120000,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_ai_assistant/no_context_llm_guess',
        get_string('no_context_llm_guess', 'block_ai_assistant'),
        '',
        1
    ));

    $settings->add(new admin_setting_configselect(
        'block_ai_assistant/embed_position',
        get_string('embed_position', 'block_ai_assistant'),
        '',
        1,
        array(
            1 => get_string('bottom_left', 'block_ai_assistant'),
            2 => get_string('bottom_right', 'block_ai_assistant'),
            3 => get_string('top_left', 'block_ai_assistant'),
            4 => get_string('top_right', 'block_ai_assistant'),
        )
    ));
    // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
    if ($ADMIN->fulltree) {
        // TODO: Define actual plugin settings page and add it to the tree - {@link https://docs.moodle.org/dev/Admin_settings}.
    }
}

set_config('description', '', 'block_ai_assistant');
set_config('bot_type', 1, 'block_ai_assistant');
set_config('requires_content_prompt', 0, 'block_ai_assistant');
set_config('requires_user_prompt', 1, 'block_ai_assistant');
set_config('user_prompt', '', 'block_ai_assistant');
set_config('theme_color', '#e31837', 'block_ai_assistant');
set_config('max_context', 120000, 'block_ai_assistant');
set_config('no_context_use_message', 1, 'block_ai_assistant');
set_config('no_context_llm_guess', 0, 'block_ai_assistant');
set_config('email', '', 'block_ai_assistant');
set_config('available_child', 0, 'block_ai_assistant');
set_config('parse_strategy', 'ALSYLLABUS', 'block_ai_assistant');
set_config('botwatermark', 0, 'block_ai_assistant');
set_config('icon_url', '', 'block_ai_assistant');
set_config('bot_locale', 'us_EN', 'block_ai_assistant');
set_config('child_bots', '', 'block_ai_assistant');
