<?php

// Minimal setup for file serving
require_once(__DIR__ . '/../../config.php');

global $CFG;

// Get parameters
$cmid = optional_param('cmid', 0, PARAM_INT);
$filename = optional_param('filename', '', PARAM_FILE);

// Validate parameters
if (!$cmid || !$filename) {
    http_response_code(400);
    die('Missing required parameters');
}

// Enforce simple filename only (no subdirectories).
$filename = basename($filename);

$basepath  = $CFG->dataroot . '/temp/ai_assistant/' . $cmid . '/';
$imagepath = $basepath . $filename;

// Ensure the resolved path is still inside the intended directory.
$realbase  = realpath($basepath);
$realfile  = realpath($imagepath);
if ($realbase === false || $realfile === false || strpos($realfile, $realbase) !== 0) {
    http_response_code(400);
    die('Invalid path');
}

if (!is_readable($realfile)) {
    http_response_code(404);
    die('Image not found');
}

// Detect mime.
$finfo     = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = $finfo ? finfo_file($finfo, $realfile) : 'application/octet-stream';
if ($finfo) {
    finfo_close($finfo);
}

// Allow browser caching (tune max-age as needed).
$filesize = filesize($realfile);
$mtime    = filemtime($realfile);
$etag     = '"' . sha1($realfile . $filesize . $mtime) . '"';

header('Content-Type: ' . $mime_type);
header('Content-Length: ' . $filesize);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('ETag: ' . $etag);
header('Cache-Control: public, max-age=86400, immutable');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . rawurlencode($filename) . '"');

// Conditional GET support.
if ((isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) ||
    (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $mtime)) {
    http_response_code(304);
    exit;
}

// Stream the file directly without session management
readfile($realfile);
exit;
