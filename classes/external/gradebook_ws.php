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

    public static function reset_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
                'session_id' => new external_value(PARAM_RAW, 'Gradebook session id', VALUE_REQUIRED),
                'keep_extraction' => new external_value(PARAM_BOOL, 'Keep extracted syllabus/resources context', VALUE_DEFAULT, true),
            )
        );
    }

    public static function reset(int $courseid, string $session_id, bool $keep_extraction = true): string
    {
        self::validate_parameters(
            self::reset_parameters(),
            [
                'courseid' => $courseid,
                'session_id' => $session_id,
                'keep_extraction' => $keep_extraction,
            ]
        );

        $context = \context_course::instance($courseid);
        self::validate_context($context);

        return cria::gradebook_reset($session_id, $keep_extraction);
    }

    public static function reset_returns(): external_description
    {
        return new external_value(PARAM_RAW, 'JSON response from Criabot gradebook reset');
    }

    public static function delete_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
                'session_id' => new external_value(PARAM_RAW, 'Gradebook session id', VALUE_REQUIRED),
            )
        );
    }

    public static function delete(int $courseid, string $session_id): string
    {
        self::validate_parameters(
            self::delete_parameters(),
            [
                'courseid' => $courseid,
                'session_id' => $session_id,
            ]
        );

        $context = \context_course::instance($courseid);
        self::validate_context($context);

        return cria::gradebook_delete($session_id, $courseid);
    }

    public static function delete_returns(): external_description
    {
        return new external_value(PARAM_RAW, 'JSON response from Criabot gradebook delete');
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

        return cria::gradebook_finalize($courseid, $session_id, $decoded);
    }

    public static function finalize_returns(): external_description
    {
        return new external_value(PARAM_RAW, 'JSON response from Criabot gradebook finalize');
    }

    public static function upload_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
                'session_id' => new external_value(PARAM_RAW, 'Gradebook session id', VALUE_REQUIRED),
                'filename' => new external_value(PARAM_RAW, 'Uploaded filename', VALUE_REQUIRED),
                'filetype' => new external_value(PARAM_RAW, 'Uploaded mimetype', VALUE_DEFAULT, ''),
                'base64' => new external_value(PARAM_RAW, 'Base64 encoded file bytes', VALUE_REQUIRED),
            )
        );
    }

    public static function upload(int $courseid, string $session_id, string $filename, string $filetype, string $base64): string
    {
        self::validate_parameters(
            self::upload_parameters(),
            [
                'courseid' => $courseid,
                'session_id' => $session_id,
                'filename' => $filename,
                'filetype' => $filetype,
                'base64' => $base64,
            ]
        );

        $context = \context_course::instance($courseid);
        self::validate_context($context);

        return cria::gradebook_upload($courseid, $session_id, $filename, $filetype, $base64);
    }

    public static function upload_returns(): external_description
    {
        return new external_value(PARAM_RAW, 'JSON response from Criabot gradebook upload');
    }

    /**
     * Schema for the persisted state returned to the client.
     */
    private static function state_single_structure(): external_single_structure
    {
        return new external_single_structure([
            'found' => new external_value(PARAM_BOOL, 'Whether a state row exists'),
            'conflict' => new external_value(PARAM_BOOL, 'Whether this save was rejected due to a newer server record', VALUE_DEFAULT, false),
            'session_id' => new external_value(PARAM_RAW, 'Stored Criabot session id', VALUE_DEFAULT, ''),
            'phase' => new external_value(PARAM_RAW, 'Stored phase', VALUE_DEFAULT, ''),
            'chat_history_json' => new external_value(PARAM_RAW, 'Chat history JSON array', VALUE_DEFAULT, ''),
            'confirmed_mapping_json' => new external_value(PARAM_RAW, 'Confirmed mapping JSON', VALUE_DEFAULT, ''),
            'result_json' => new external_value(PARAM_RAW, 'Finalize result JSON', VALUE_DEFAULT, ''),
            'timemodified' => new external_value(PARAM_INT, 'Unix timestamp of last update', VALUE_DEFAULT, 0),
        ]);
    }

    public static function get_state_parameters(): external_function_parameters
    {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
        ]);
    }

    public static function get_state(int $courseid): array
    {
        global $USER;

        self::validate_parameters(self::get_state_parameters(), ['courseid' => $courseid]);

        $context = \context_course::instance($courseid);
        self::validate_context($context);

        return cria::gradebook_get_state($courseid, (int)$USER->id);
    }

    public static function get_state_returns(): external_description
    {
        return self::state_single_structure();
    }

    public static function save_state_parameters(): external_function_parameters
    {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
            'session_id' => new external_value(PARAM_RAW, 'Criabot session id', VALUE_DEFAULT, null),
            'phase' => new external_value(PARAM_RAW, 'Current phase', VALUE_DEFAULT, null),
            'chat_history_json' => new external_value(PARAM_RAW, 'Chat history JSON array', VALUE_DEFAULT, null),
            'confirmed_mapping_json' => new external_value(PARAM_RAW, 'Confirmed mapping JSON', VALUE_DEFAULT, null),
            'result_json' => new external_value(PARAM_RAW, 'Finalize result JSON', VALUE_DEFAULT, null),
            'clear' => new external_value(PARAM_BOOL, 'Delete the row instead of upsert', VALUE_DEFAULT, false),
            'last_known_timemodified' => new external_value(
                PARAM_INT,
                'Latest timemodified the client has seen; used for optimistic concurrency',
                VALUE_DEFAULT,
                0
            ),
            'force' => new external_value(
                PARAM_BOOL,
                'Force overwrite even if a newer server record exists or incoming values are empty',
                VALUE_DEFAULT,
                false
            ),
        ]);
    }

    public static function save_state(
        int $courseid,
        ?string $session_id = null,
        ?string $phase = null,
        ?string $chat_history_json = null,
        ?string $confirmed_mapping_json = null,
        ?string $result_json = null,
        bool $clear = false,
        int $last_known_timemodified = 0,
        bool $force = false
    ): array {
        global $USER;

        self::validate_parameters(self::save_state_parameters(), [
            'courseid' => $courseid,
            'session_id' => $session_id,
            'phase' => $phase,
            'chat_history_json' => $chat_history_json,
            'confirmed_mapping_json' => $confirmed_mapping_json,
            'result_json' => $result_json,
            'clear' => $clear,
            'last_known_timemodified' => $last_known_timemodified,
            'force' => $force,
        ]);

        $context = \context_course::instance($courseid);
        self::validate_context($context);

        if ($clear) {
            cria::gradebook_clear_state($courseid, (int)$USER->id);
            return [
                'found' => false,
                'conflict' => false,
                'session_id' => '',
                'phase' => '',
                'chat_history_json' => '',
                'confirmed_mapping_json' => '',
                'result_json' => '',
                'timemodified' => 0,
            ];
        }

        return cria::gradebook_save_state(
            $courseid,
            (int)$USER->id,
            $session_id,
            $phase,
            $chat_history_json,
            $confirmed_mapping_json,
            $result_json,
            $last_known_timemodified,
            $force
        );
    }

    public static function save_state_returns(): external_description
    {
        return self::state_single_structure();
    }

    /**
     * Keep-alive beacon endpoint used via navigator.sendBeacon on pagehide/visibilitychange.
     * Accepts the same payload as save_state but is tuned for last-gasp "save my chat" requests.
     */
    public static function save_state_beacon_parameters(): external_function_parameters
    {
        return self::save_state_parameters();
    }

    public static function save_state_beacon(
        int $courseid,
        ?string $session_id = null,
        ?string $phase = null,
        ?string $chat_history_json = null,
        ?string $confirmed_mapping_json = null,
        ?string $result_json = null,
        bool $clear = false,
        int $last_known_timemodified = 0,
        bool $force = false
    ): array {
        return self::save_state(
            $courseid,
            $session_id,
            $phase,
            $chat_history_json,
            $confirmed_mapping_json,
            $result_json,
            $clear,
            $last_known_timemodified,
            $force
        );
    }

    public static function save_state_beacon_returns(): external_description
    {
        return self::state_single_structure();
    }

    public static function export_parameters(): external_function_parameters
    {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
            'format' => new external_value(PARAM_ALPHA, 'Export format: pdf or docx', VALUE_REQUIRED),
        ]);
    }

    public static function export(int $courseid, string $format): array
    {
        global $USER;

        self::validate_parameters(self::export_parameters(), [
            'courseid' => $courseid,
            'format' => $format,
        ]);

        $context = \context_course::instance($courseid);
        self::validate_context($context);

        require_once(__DIR__ . '/../gradebook_export.php');

        $state = cria::gradebook_get_state($courseid, (int)$USER->id);
        if (empty($state['found'])) {
            throw new \moodle_exception('gradebook_export_nostate', 'block_ai_assistant');
        }

        $format = strtolower($format);
        if (!in_array($format, ['pdf', 'docx'], true)) {
            throw new \moodle_exception('gradebook_export_badformat', 'block_ai_assistant');
        }

        $exporter = new \block_ai_assistant\gradebook_export($courseid, (int)$USER->id, $state);

        if ($format === 'pdf') {
            $bytes = $exporter->render_pdf();
            $mime = 'application/pdf';
            $filename = $exporter->filename('pdf');
        } else {
            $bytes = $exporter->render_docx();
            // Using .doc with application/msword -- legacy Word-compatible HTML; opens natively in Word/LibreOffice.
            $mime = 'application/msword';
            $filename = $exporter->filename('doc');
        }

        return [
            'filename' => $filename,
            'mime' => $mime,
            'base64' => base64_encode($bytes),
        ];
    }

    public static function export_returns(): external_description
    {
        return new external_single_structure([
            'filename' => new external_value(PARAM_RAW, 'Suggested filename for download'),
            'mime' => new external_value(PARAM_RAW, 'MIME type of the returned blob'),
            'base64' => new external_value(PARAM_RAW, 'Base64-encoded document bytes'),
        ]);
    }
}

