<?php

require_once($CFG->libdir . "/externallib.php");
require_once("$CFG->dirroot/config.php");

use block_ai_assistant\cria;

class block_ai_assistant_gradebook_ws extends external_api
{
    public static function start_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
            )
        );
    }

    public static function start(int $courseid): string
    {
        global $USER;

        self::validate_parameters(
            self::start_parameters(),
            [
                'courseid' => $courseid,
            ]
        );

        $context = \context_course::instance($courseid);
        self::validate_context($context);

        // Delegate to plugin service wrapper; returns JSON string from Criabot.
        return cria::gradebook_start($courseid, (int)$USER->id);
    }

    public static function start_returns(): external_description
    {
        return new external_value(PARAM_RAW, 'JSON response from Criabot gradebook start');
    }

    public static function chat_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
                'session_id' => new external_value(PARAM_RAW, 'Gradebook session id', VALUE_REQUIRED),
                'prompt' => new external_value(PARAM_TEXT, 'Instructor prompt', VALUE_REQUIRED),
            )
        );
    }

    public static function chat(int $courseid, string $session_id, string $prompt): string
    {
        self::validate_parameters(
            self::chat_parameters(),
            [
                'courseid' => $courseid,
                'session_id' => $session_id,
                'prompt' => $prompt,
            ]
        );

        $context = \context_course::instance($courseid);
        self::validate_context($context);

        return cria::gradebook_chat($session_id, $prompt);
    }

    public static function chat_returns(): external_description
    {
        return new external_value(PARAM_RAW, 'JSON response from Criabot gradebook chat');
    }

    public static function proposal_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
                'session_id' => new external_value(PARAM_RAW, 'Gradebook session id', VALUE_REQUIRED),
            )
        );
    }

    public static function proposal(int $courseid, string $session_id): string
    {
        self::validate_parameters(
            self::proposal_parameters(),
            [
                'courseid' => $courseid,
                'session_id' => $session_id,
            ]
        );

        $context = \context_course::instance($courseid);
        self::validate_context($context);

        return cria::gradebook_proposal($session_id);
    }

    public static function proposal_returns(): external_description
    {
        return new external_value(PARAM_RAW, 'JSON response from Criabot gradebook proposal');
    }

    public static function status_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
                'session_id' => new external_value(PARAM_RAW, 'Gradebook session id', VALUE_REQUIRED),
            )
        );
    }

    public static function status(int $courseid, string $session_id): string
    {
        self::validate_parameters(
            self::status_parameters(),
            [
                'courseid' => $courseid,
                'session_id' => $session_id,
            ]
        );

        $context = \context_course::instance($courseid);
        self::validate_context($context);

        return cria::gradebook_status($session_id);
    }

    public static function status_returns(): external_description
    {
        return new external_value(PARAM_RAW, 'JSON response from Criabot gradebook session status');
    }

    public static function accept_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
                'session_id' => new external_value(PARAM_RAW, 'Gradebook session id', VALUE_REQUIRED),
            )
        );
    }

    public static function accept(int $courseid, string $session_id): string
    {
        self::validate_parameters(
            self::accept_parameters(),
            [
                'courseid' => $courseid,
                'session_id' => $session_id,
            ]
        );

        $context = \context_course::instance($courseid);
        self::validate_context($context);

        return cria::gradebook_accept($session_id);
    }

    public static function accept_returns(): external_description
    {
        return new external_value(PARAM_RAW, 'JSON response from Criabot gradebook accept');
    }

    public static function finalize_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
                'session_id' => new external_value(PARAM_RAW, 'Gradebook session id', VALUE_REQUIRED),
                'confirmed_mapping_json' => new external_value(PARAM_RAW, 'JSON array of {moodle_cmid, category}', VALUE_REQUIRED),
            )
        );
    }

    public static function finalize(int $courseid, string $session_id, string $confirmed_mapping_json): string
    {
        self::validate_parameters(
            self::finalize_parameters(),
            [
                'courseid' => $courseid,
                'session_id' => $session_id,
                'confirmed_mapping_json' => $confirmed_mapping_json,
            ]
        );

        $context = \context_course::instance($courseid);
        self::validate_context($context);

        $decoded = json_decode($confirmed_mapping_json, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        return cria::gradebook_finalize($session_id, $decoded);
    }

    public static function finalize_returns(): external_description
    {
        return new external_value(PARAM_RAW, 'JSON response from Criabot gradebook finalize');
    }
}

