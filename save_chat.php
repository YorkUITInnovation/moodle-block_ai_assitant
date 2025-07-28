<?php

require_once(__DIR__ . '/../../config.php');

use block_ai_assistant\cria;
use block_ai_assistant\chat;

global $CFG, $DB, $USER;
require_once($CFG->libdir . '/tcpdf/tcpdf.php');

$tutorialchatid = required_param('tutorialchatid', PARAM_TEXT);
$bot_name = optional_param('botname', '', PARAM_TEXT);
// Get Chat history form Cria
$data = chat::get_messages($tutorialchatid);
$messages = $data['messages'];
$tutorial_name = $data['tutorial_name'] ?? null;

$user = $DB->get_record('user', ['id' => $USER->id]);
$full_name = fullname($user);

// Create PDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('AI Assistant');
$pdf->SetAuthor($full_name);
$pdf->SetTitle('Chat History - ' . ($tutorial_name ?? 'AI Assistant Chat'));
$pdf->SetSubject('AI Assistant Chat History');

// Set default header data
$pdf->SetHeaderData('', 0, 'AI Assistant Chat History', ($tutorial_name ?? 'Chat Session') . ' - ' . date('Y-m-d H:i:s'));

// Set header and footer fonts
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Add a page
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 11);

// Build HTML content for the PDF
$html = '<h2>Chat History</h2>';
$html .= '<p><strong>Tutorial:</strong> ' . htmlspecialchars($tutorial_name ?? 'AI Assistant Chat') . '</p>';
$html .= '<p><strong>User:</strong> ' . htmlspecialchars($full_name) . '</p>';
$html .= '<p><strong>Date:</strong> ' . date('Y-m-d H:i:s') . '</p>';
$html .= '<hr>';
$tempFiles = [];

foreach ($messages as $message) {
    // Author
    $author = $message['is_human'] ? htmlspecialchars($full_name) : 'AI Assistant';
    $html .= '<p><strong>' . $author . ':</strong></p>';

    $text = $message['message'];

    // Convert base64 images to file references for TCPDF
    if (preg_match_all('/<img[^>]+src="data:image\/([^;]+);base64,([^"\s]+)"[^>]*>/i', $text, $matches)) {
        for ($i = 0; $i < count($matches[0]); $i++) {
            $base64 = urldecode($matches[2][$i]);
            $data = base64_decode($base64);

            if ($data !== false && strlen($data) > 0) {
                $tempDir = $CFG->dataroot . '/temp';
                if (!is_dir($tempDir)) {
                    mkdir($tempDir, 0755, true);
                }

                // Check if we have WEBP support
                if (function_exists('imagecreatefromwebp')) {
                    $image = @imagecreatefromstring($data);
                    if ($image !== false) {
                        // Convert to JPEG format (most reliable for TCPDF)
                        $jpgFile = $tempDir . '/img_' . uniqid() . '.jpg';
                        imagejpeg($image, $jpgFile, 90);
                        imagedestroy($image);
                        $tempFiles[] = $jpgFile;

                        // Replace the base64 img tag with a file reference
                        $text = str_replace($matches[0][$i], '<img src="' . $jpgFile . '" style="width:80mm;" />', $text);
                    } else {
                        $text = str_replace($matches[0][$i], '', $text);
                    }
                } else {
                    // No WEBP support - add a placeholder message
                    $text = str_replace($matches[0][$i], '<p><em>[Image not supported - WEBP format requires additional PHP configuration]</em></p>', $text);
                }
            }
        }
    }

    // Allow basic formatting
    $allowed = '<b><strong><i><em><u><ul><ol><li><p><br><img>';
    $text = strip_tags($text, $allowed);
    $html .= $text;
}

// Process HTML and insert images
$htmlParts = preg_split('/(<img[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

foreach ($htmlParts as $part) {
    if (preg_match('/^<img[^>]*src="([^"]+)"[^>]*>/', $part, $matches)) {
        // This is an image tag - insert it directly
        $imagePath = $matches[1];
        if (file_exists($imagePath)) {
            try {
                $imageInfo = getimagesize($imagePath);
                if ($imageInfo !== false) {
                    $imageType = '';
                    if ($imageInfo[2] == IMAGETYPE_PNG) {
                        $imageType = 'PNG';
                    } elseif ($imageInfo[2] == IMAGETYPE_JPEG) {
                        $imageType = 'JPG';
                    } elseif ($imageInfo[2] == IMAGETYPE_WEBP) {
                        $imageType = 'WEBP';
                    } else {
                        $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
                        if ($ext === 'png') $imageType = 'PNG';
                        elseif ($ext === 'jpg' || $ext === 'jpeg') $imageType = 'JPG';
                        elseif ($ext === 'webp') $imageType = 'WEBP';
                        else $imageType = 'PNG';
                    }

                    if ($imageType && $imageType !== 'WEBP') {
                        $pdf->Image($imagePath, '', '', 80, '', $imageType, '', 'T', false, 300);
                        $pdf->Ln(5);
                    }
                }
            } catch (Exception $e) {
                // Silently skip problematic images
            }
        }
    } else {
        // This is regular HTML content
        if (trim($part)) {
            $pdf->writeHTML($part, true, false, true, false, '');
        }
    }
}

// Clean up temp files
foreach ($tempFiles as $file) {
    @unlink($file);
}

// Generate filename
$filename = 'chat_history_' . str_replace(' ', '_', $tutorial_name) .  '_' . date('Y-m-d_H-i-s') . '.pdf';

// Clean output buffer
if (ob_get_length()) {
    ob_end_clean();
}

// Output PDF for download
$pdf->Output($filename, 'D');
exit;
