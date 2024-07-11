<?php

namespace block_ai_assistant;

use block_ai_assistant\webservice;

class cria
{

    public static function create_bot_instance($course_id)
    {
        $method = get_string('create_cria_bot_endpoint', 'block_ai_assistant');
        $data = self::get_create_cria_bot_config($course_id);
        $bot_name = webservice::exec($method, $data);
        return $bot_name;
    }

    private static function get_create_cria_bot_config($course_id)
    {
        global $PAGE, $DB, $COURSE;
        $context = \context_course::instance($course_id);

        $course_data = $DB->get_record('course', array('id' => $course_id));
        if ($course_data) {
            if ($course_data->idnumber != '') {
                $name = $course_data->idnumber;
            } else {
                $name = $course_data->shortname;
            }
        }
        $config = get_config('block_ai_assistant');
        $data = array(
            'name' => $name,
            'description' => $config->description,
            'bot_type' => $config->bot_type,
            'bot_system_message' => $config->system_message,
            'model_id' => $config->criadex_model_id,
            'embedding_id' => $config->criadex_embed_id,
            'rerank_model_id' => $config->criadex_rerank_id,
            'requires_content_prompt' => $config->requires_content_prompt,
            'requires_user_prompt' => $config->requires_user_prompt,
            'user_prompt' => $config->user_prompt,
            'welcome_message' => $config->welcome_message,
            'theme_color' => $config->theme_color,
            'max_tokens' => $config->max_tokens,
            'temperature' => $config->temperature,
            'top_p' => $config->top_p,
            'top_k' => $config->top_k,
            'top_n' => $config->top_n,
            'min_k' => $config->min_k,
            'min_relevance' => $config->min_relevance,
            'max_context' => $config->max_context,
            'no_context_message' => $config->no_context_message,
            'no_context_use_message' => $config->no_context_use_message,
            'no_context_llm_guess' => $config->no_context_llm_guess,
            'email' => $config->email,
            'available_child' => $config->available_child,
            'parse_strategy' => $config->parse_strategy,
            'botwatermark' => $config->botwatermark,
            'title' => $config->title,
            'subtitle' => $config->subtitle,
            'embed_position' => $config->embed_position,
            'icon_url' => $config->icon_url,
            'bot_locale' => $config->bot_locale,
            'child_bots' => $config->child_bots,
        );
        return $data;
    }
}
