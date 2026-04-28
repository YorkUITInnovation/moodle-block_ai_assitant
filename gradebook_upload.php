<?php
/**
 * Gradebook upload fallback endpoint.
 *
 * Used when Moodle external function registration is stale/missing
 * (e.g. `external_functions` does not include block_ai_assistant_gradebook_upload yet).
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/cria.php');

use block_ai_assistant\cria;

header('Content-Type: application/json; charset=utf-8');

if (!isloggedin() || isguestuser()) {
    http_response_code(401);
    echo json_encode([
        'status' => 401,
        'code' => 'UNAUTHORIZED',
        'message' => 'Login required.',
    ]);
    exit;
}

try {
    require_sesskey();
} catch (\Throwable $e) {
    http_response_code(403);
    echo json_encode([
        'status' => 403,
        'code' => 'INVALID_SESSKEY',
        'message' => 'Invalid session key.',
    ]);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode((string)$raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode([
        'status' => 400,
        'code' => 'BAD_REQUEST',
        'message' => 'Invalid JSON body.',
    ]);
    exit;
}

$courseid = isset($payload['courseid']) ? (int)$payload['courseid'] : 0;
$sessionid = isset($payload['session_id']) ? trim((string)$payload['session_id']) : '';
$filename = isset($payload['filename']) ? (string)$payload['filename'] : '';
$filetype = isset($payload['filetype']) ? (string)$payload['filetype'] : '';
$base64 = isset($payload['base64']) ? (string)$payload['base64'] : '';

if ($courseid <= 0 || $sessionid === '' || $filename === '' || $base64 === '') {
    http_response_code(400);
    echo json_encode([
        'status' => 400,
        'code' => 'MISSING_FIELDS',
        'message' => 'courseid, session_id, filename, and base64 are required.',
    ]);
    exit;
}

try {
    $context = context_course::instance($courseid);
    require_login($courseid, false);
    require_capability('moodle/course:view', $context);
} catch (\Throwable $e) {
    http_response_code(403);
    echo json_encode([
        'status' => 403,
        'code' => 'FORBIDDEN',
        'message' => 'Access denied for this course.',
    ]);
    exit;
}

try {
    $rawResult = cria::gradebook_upload($courseid, $sessionid, $filename, $filetype, $base64);
    $parsed = json_decode((string)$rawResult, true);

    if (is_array($parsed)) {
        echo json_encode($parsed);
    } else {
        echo (string)$rawResult;
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 500,
        'code' => 'UPLOAD_FAILED',
        'message' => 'Failed to upload gradebook document.',
    ]);
}
