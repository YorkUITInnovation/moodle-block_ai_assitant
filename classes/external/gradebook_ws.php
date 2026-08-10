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
                'import_mode' => new external_value(PARAM_ALPHA, 'Session origin mode: fresh|baseline', VALUE_DEFAULT, ''),
            )
        );
    }

    public static function start(int $courseid, string $import_mode = ''): string
    {
        global $USER;

        self::validate_parameters(
            self::start_parameters(),
            [
                'courseid' => $courseid,
                'import_mode' => $import_mode,
            ]
        );

        $context = \context_course::instance($courseid);
        self::validate_context($context);

        // Delegate to plugin service wrapper; returns JSON string from Criabot.
        return cria::gradebook_start($courseid, (int)$USER->id, $import_mode);
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

        cria::gradebook_sync_context_for_chat($courseid, $session_id, $prompt);

        $raw = cria::gradebook_chat($session_id, $prompt);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $raw;
        }

        return json_encode(cria::enrich_gradebook_session_flags($courseid, $session_id, $decoded));
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
        global $USER;

        self::validate_parameters(
            self::status_parameters(),
            [
                'courseid' => $courseid,
                'session_id' => $session_id,
            ]
        );

        $context = \context_course::instance($courseid);
        self::validate_context($context);

        $status_json = cria::gradebook_status($session_id);
        $status = json_decode($status_json, true);

        if (!is_array($status)) {
            return $status_json;
        }

        $revertavailable = cria::gradebook_revert_available($courseid, $session_id, $status);
        $hasaitree = cria::has_ai_gradebook_tree($courseid);
        $missingtreeflag = cria::gradebook_tree_missing_flag($courseid);

        $statusphase = strtoupper(trim((string)($status['phase'] ?? ($status['session']['phase'] ?? ''))));
        $finalizedphase = in_array($statusphase, ['COMPLETED', 'FINALIZED'], true);
        $missingafterfinalize = $finalizedphase && !$hasaitree;

        $status['revert_available'] = $revertavailable;
        $status['data'] = array_merge(
            is_array($status['data'] ?? null) ? $status['data'] : [],
            [
                'revert_available' => $revertavailable,
                'grade_setup_present' => $hasaitree,
                'grade_setup_missing_after_finalize' => $missingafterfinalize,
                'grade_setup_deleted_event_detected' => $missingtreeflag,
            ]
        );
        $status_json = json_encode($status);

        $localstate = cria::gradebook_get_state($courseid, (int)$USER->id);
        if (!is_array($localstate)
            || empty($localstate['found'])
            || trim((string)($localstate['session_id'] ?? '')) !== trim($session_id)) {
            return $status_json;
        }

        $localphase = strtoupper(trim((string)($localstate['phase'] ?? '')));
        $remotephase = strtoupper(trim((string)(
            $status['phase']
            ?? ($status['session']['phase'] ?? '')
        )));

        // Keep Moodle UI authoritative when local grade setup apply was blocked/rolled back.
        $localresult = [];
        if (!empty($localstate['result_json'])) {
            $decodedresult = json_decode((string)$localstate['result_json'], true);
            if (is_array($decodedresult)) {
                $localresult = $decodedresult;
            }
        }

        $localdata = is_array($localresult['data'] ?? null) ? $localresult['data'] : [];
        $locallyblocked = (
            ($localphase === 'REFINEMENT' && in_array($remotephase, ['COMPLETED', 'FINALIZED'], true))
            || !empty($localdata['grade_setup_skipped'])
            || !empty($localdata['grade_setup_apply_rolled_back'])
        );

        if (!$locallyblocked) {
            return $status_json;
        }

        if (isset($status['session']) && is_array($status['session'])) {
            $status['session']['phase'] = $localphase !== '' ? $localphase : 'REFINEMENT';
        } else {
            $status['phase'] = $localphase !== '' ? $localphase : 'REFINEMENT';
        }

        $status['data'] = array_merge(
            is_array($status['data'] ?? null) ? $status['data'] : [],
            [
                'revert_available' => $revertavailable,
                'grade_setup_present' => $hasaitree,
                'grade_setup_missing_after_finalize' => $missingafterfinalize,
                'grade_setup_deleted_event_detected' => $missingtreeflag,
                'grade_setup_skipped' => !empty($localdata['grade_setup_skipped']) || !empty($localdata['grade_setup_apply_rolled_back']),
                'grade_setup_apply_rolled_back' => !empty($localdata['grade_setup_apply_rolled_back']),
                'grade_setup_skip_reason' => (string)($localdata['grade_setup_skip_reason'] ?? 'local_state_reconciliation'),
            ]
        );

        return json_encode($status);
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

        $raw = cria::gradebook_accept($session_id);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $raw;
        }

        return json_encode(cria::enrich_gradebook_session_flags($courseid, $session_id, $decoded));
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

    public static function revert_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
                'session_id' => new external_value(PARAM_RAW, 'Gradebook session id', VALUE_REQUIRED),
                'revision' => new external_value(PARAM_INT, 'Revision to restore (0 = immutable baseline)', VALUE_DEFAULT, 0),
            )
        );
    }

    public static function revert(int $courseid, string $session_id, int $revision = 0): string
    {
        self::validate_parameters(
            self::revert_parameters(),
            [
                'courseid' => $courseid,
                'session_id' => $session_id,
                'revision' => $revision,
            ]
        );

        $context = \context_course::instance($courseid);
        self::validate_context($context);

        return cria::gradebook_revert($courseid, $session_id, $revision);
    }

    public static function revert_returns(): external_description
    {
        return new external_value(PARAM_RAW, 'JSON response from Moodle gradebook snapshot revert');
    }

    public static function finalize_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
                'session_id' => new external_value(PARAM_RAW, 'Gradebook session id', VALUE_REQUIRED),
                'confirmed_mapping_json' => new external_value(PARAM_RAW, 'JSON array of mapping rows: {grade_item_id, activity_name, moodle_cmid, category, subcategory}', VALUE_REQUIRED),
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
            throw new invalid_parameter_exception('confirmed_mapping_json must be a valid JSON array.');
        }

        $raw = cria::gradebook_finalize($courseid, $session_id, $decoded);
        $response = json_decode($raw, true);
        if (!is_array($response)) {
            return $raw;
        }

        return json_encode(cria::enrich_gradebook_session_flags($courseid, $session_id, $response));
    }

    public static function finalize_returns(): external_description
    {
        return new external_value(PARAM_RAW, 'JSON response from Criabot gradebook finalize');
    }

    public static function create_manual_item_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
                'item_name' => new external_value(PARAM_TEXT, 'Manual grade item name', VALUE_REQUIRED),
                'category' => new external_value(PARAM_TEXT, 'Target category name', VALUE_DEFAULT, ''),
                'subcategory' => new external_value(PARAM_TEXT, 'Target subcategory name', VALUE_DEFAULT, ''),
            )
        );
    }

    public static function create_manual_item(int $courseid, string $item_name, string $category = '', string $subcategory = ''): array
    {
        self::validate_parameters(
            self::create_manual_item_parameters(),
            [
                'courseid' => $courseid,
                'item_name' => $item_name,
                'category' => $category,
                'subcategory' => $subcategory,
            ]
        );

        $context = \context_course::instance($courseid);
        self::validate_context($context);

        return cria::gradebook_create_manual_item($courseid, $item_name, $category, $subcategory);
    }

    public static function create_manual_item_returns(): external_description
    {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'Whether the manual grade item was created.'),
                'message' => new external_value(PARAM_TEXT, 'Result message.'),
                'grade_item_id' => new external_value(PARAM_INT, 'Created grade item id.'),
                'activity_name' => new external_value(PARAM_TEXT, 'Manual item display name.'),
                'moodle_cmid' => new external_value(PARAM_INT, 'CMID for the item, 0 for manual rows.'),
                'itemtype' => new external_value(PARAM_ALPHA, 'Grade item type.'),
                'module' => new external_value(PARAM_RAW, 'Module name, empty for manual rows.'),
            )
        );
    }

    public static function create_assignment_activity_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
                'activity_name' => new external_value(PARAM_TEXT, 'Assignment activity name', VALUE_REQUIRED),
                'section_num' => new external_value(PARAM_INT, 'Course section number (default 0)', VALUE_DEFAULT, 0),
            )
        );
    }

    public static function create_assignment_activity(int $courseid, string $activity_name, int $section_num = 0): array
    {
        self::validate_parameters(
            self::create_assignment_activity_parameters(),
            [
                'courseid' => $courseid,
                'activity_name' => $activity_name,
                'section_num' => $section_num,
            ]
        );

        $context = \context_course::instance($courseid);
        self::validate_context($context);

        return cria::gradebook_create_assignment_activity($courseid, $activity_name, $section_num);
    }

    public static function create_assignment_activity_returns(): external_description
    {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'Whether the assignment activity was created or reused.'),
                'message' => new external_value(PARAM_TEXT, 'Result message.'),
                'activity_name' => new external_value(PARAM_TEXT, 'Assignment activity display name.'),
                'moodle_cmid' => new external_value(PARAM_INT, 'CMID for the assignment activity.'),
                'module' => new external_value(PARAM_ALPHA, 'Module type, assign.'),
                'grade_item_id' => new external_value(PARAM_INT, 'Grade item id if available.'),
                'reused' => new external_value(PARAM_BOOL, 'True when an existing activity with the same name was reused.'),
            )
        );
    }

    public static function sync_context_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
                'session_id' => new external_value(PARAM_RAW, 'Gradebook session id', VALUE_REQUIRED),
                'refresh_proposal_candidates' => new external_value(
                    PARAM_BOOL,
                    'Whether to force proposal candidate refresh during sync.',
                    VALUE_DEFAULT,
                    false
                ),
            )
        );
    }

    public static function sync_context(int $courseid, string $session_id, bool $refresh_proposal_candidates = false): array
    {
        self::validate_parameters(
            self::sync_context_parameters(),
            [
                'courseid' => $courseid,
                'session_id' => $session_id,
                'refresh_proposal_candidates' => $refresh_proposal_candidates,
            ]
        );

        $context = \context_course::instance($courseid);
        self::validate_context($context);

        cria::gradebook_sync_context($courseid, $session_id, $refresh_proposal_candidates);

        return [
            'success' => true,
            'message' => 'Context synced.',
        ];
    }

    public static function sync_context_returns(): external_description
    {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'Whether sync call completed.'),
                'message' => new external_value(PARAM_TEXT, 'Sync result message.'),
            )
        );
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

