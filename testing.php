<?php

require_once(__DIR__ . '/../../config.php');
require_once('classes/external/course_modules_ws.php');
global $CFG, $DB, $OUTPUT, $USER, $PAGE;

use block_ai_assistant\webservice;
use block_ai_assistant\course_module_training;
use block_ai_assistant\course_modules;
use block_ai_assistant\cria;
use block_ai_assistant\chat;

$context = context_system::instance();

require_login(1, false);
$PAGE->set_url(new moodle_url('/blocks/learningassist/testing.php', []));
$PAGE->set_title('Testing');
$PAGE->set_heading('Test');
$PAGE->set_pagelayout('standard');
$PAGE->set_context($context);

$courseid = required_param('courseid', PARAM_INT);

echo $OUTPUT->header();

// Check WEBP support
echo "<h3>WEBP Support Check:</h3>";
echo "function_exists('imagecreatefromwebp'): " . (function_exists('imagecreatefromwebp') ? 'YES' : 'NO') . "<br>";
echo "function_exists('imagewebp'): " . (function_exists('imagewebp') ? 'YES' : 'NO') . "<br>";

// Check GD info
echo "<h3>GD Info:</h3>";
if (function_exists('gd_info')) {
    $gd_info = gd_info();
    echo "<pre>";
    print_r($gd_info);
    echo "</pre>";
}

// Check if WEBP is supported in imagetypes
echo "<h3>Image Types Support:</h3>";
$types = imagetypes();
echo "IMG_WEBP supported: " . (($types & IMG_WEBP) ? 'YES' : 'NO') . "<br>";

$webpFilePath  = '/var/www/moodledata/temp/img_68870537c938e.webp';

$jpegFilePath = '/var/www/moodledata/temp/output.jpeg';

// JPEG quality (0-100, 100 is best quality)
$quality = 90;

// Check if the WebP file exists
if (!file_exists($webpFilePath)) {
    die("Error: WebP file not found at $webpFilePath");
}

// Load the WebP image (only if function exists)
if (function_exists('imagecreatefromwebp')) {
    $image = imagecreatefromwebp($webpFilePath);
} else {
    echo "<p style='color: red;'>Error: imagecreatefromwebp() function not available. WEBP support not enabled.</p>";
    echo $OUTPUT->footer();
    exit;
}

// Check if image creation was successful
if ($image === false) {
    die("Error: Could not create image from WebP file.");
}

// Convert to JPEG and save
if (imagejpeg($image, $jpegFilePath, $quality)) {
    echo "WebP image successfully converted to JPEG and saved at $jpegFilePath";
} else {
    echo "Error: Failed to convert WebP to JPEG.";
}

// Destroy the image resource to free up memory
imagedestroy($image);


echo $OUTPUT->footer();
