<?php
/**
 * Keep-alive beacon endpoint for the gradebook UI.
 *
 * Used by navigator.sendBeacon() on `visibilitychange` / `pagehide` so the last
 * chat messages and mapping edits survive a sudden tab close or refresh even
 * when a regular AJAX Promise can no longer complete.
 *
 * Accepts JSON via POST body. Requires an authenticated Moodle session for the
 * given course. Always returns 204 (empty) so the beacon layer is cheap; the
 * real authoritative save also happens via the normal ws path.
 */

// phpcs:disable moodle.Files.RequireLogin.Missing
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/cria.php');

use block_ai_assistant\cria;

// sendBeacon does not carry sesskey as a form field; fall back to session cookie auth only.
if (!isloggedin() || isguestuser()) {
    header('HTTP/1.1 401 Unauthorized');
    exit;
}

$raw = file_get_contents('php://input');
if (!is_string($raw) || $raw === '') {
    header('HTTP/1.1 204 No Content');
    exit;
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

$courseid = isset($payload['courseid']) ? (int)$payload['courseid'] : 0;
if ($courseid <= 0) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

try {
    $context = context_course::instance($courseid);
    require_login($courseid, false);
    require_capability('moodle/course:view', $context);
} catch (\Throwable $e) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$clear = !empty($payload['clear']);
if ($clear) {
    cria::gradebook_clear_state($courseid, (int)$USER->id);
    header('HTTP/1.1 204 No Content');
    exit;
}

cria::gradebook_save_state(
    $courseid,
    (int)$USER->id,
    isset($payload['session_id']) ? (string)$payload['session_id'] : null,
    isset($payload['phase']) ? (string)$payload['phase'] : null,
    isset($payload['chat_history_json']) ? (string)$payload['chat_history_json'] : null,
    isset($payload['confirmed_mapping_json']) ? (string)$payload['confirmed_mapping_json'] : null,
    isset($payload['result_json']) ? (string)$payload['result_json'] : null,
    isset($payload['last_known_timemodified']) ? (int)$payload['last_known_timemodified'] : 0,
    !empty($payload['force'])
);

header('HTTP/1.1 204 No Content');
