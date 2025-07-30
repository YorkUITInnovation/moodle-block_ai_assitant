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

// Build HTML content for the summary
$content = '<p>' . htmlspecialchars(get_string('summary_prompt', 'block_ai_assistant')) . '</p>';
$content .= '<p><strong>' . get_string('tutorial', 'block_ai_assistant') . ':</strong> '
    . htmlspecialchars($tutorial_name ?? 'AI Assistant Chat') . '</p>';
$content .= '<p><strong>' . get_string('student', 'block_ai_assistant') . ':</strong> '
    . htmlspecialchars($full_name) . '</p>';
$content .= '<p><strong>' . get_string('date', 'block_ai_assistant') . ':</strong> '
    . date('Y-m-d H:i:s') . '</p>';
$content .= '<hr>';

foreach ($messages as $message) {
    // If the html message has an image, remove it.
    if (preg_match('/<img[^>]+src="data:image\/[^;]+;base64,[^"]+"[^>]*>/i', $message['message'])) {
        $message['message'] = preg_replace('/<img[^>]+src="data:image\/[^;]+;base64,[^"]+"[^>]*>/i', '', $message['message']);
    }

    $label = $message['is_human']
        ? htmlspecialchars($full_name)
        : htmlspecialchars(get_string('ai_assistant', 'block_ai_assistant'));
    $content .= '<p><strong>' . $label . ':</strong></p>';
    $content .= '<p>' . nl2br(htmlspecialchars($message['message'])) . '</p>';
}

//get a new chat session
    $chat_session = cria::chat_start();
    $summary = cria::chat_send($chat_session, $content, $bot_name);

// Delete the chat session after summarization
    cria::chat_end($chat_session);

// Create PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
    $pdf->SetCreator(get_string('ai_assistant', 'block_ai_assistant'));
    $pdf->SetAuthor($full_name);
    $pdf->SetTitle(get_string('chat_summary', 'block_ai_assistant') . ' - '
        . ($tutorial_name ?? 'AI Assistant Chat'));
    $pdf->SetSubject(get_string('ai_assistant', 'block_ai_assistant') . ' '
        . get_string('chat_summary', 'block_ai_assistant'));

// Set default header data
    $pdf->SetHeaderData('', 0, get_string('ai_assistant', 'block_ai_assistant') . ' '
        . get_string('chat_summary', 'block_ai_assistant'),
        ($tutorial_name ?? 'Chat Session') . ' - ' . date('Y-m-d H:i:s'));

// Set header and footer fonts
    $pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

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

// Set font
    $pdf->SetFont('helvetica', '', 11);

// Build HTML content for the PDF
$html = '<h2>' . get_string('chat_summary', 'block_ai_assistant') . '</h2>';
$html .= '<p><strong>' . get_string('tutorial', 'block_ai_assistant') . ':</strong> ' . htmlspecialchars($tutorial_name ?? 'AI Assistant Chat') . '</p>';
$html .= '<p><strong>' . get_string('student', 'block_ai_assistant') . ':</strong> ' . htmlspecialchars($full_name) . '</p>';
$html .= '<p><strong>' . get_string('date', 'block_ai_assistant') . ':</strong> ' . date('Y-m-d H:i:s') . '</p>';
$html .= '<hr>';
$html .= '<br>' . $summary;

// Output the HTML content
    $pdf->writeHTML($html, true, false, true, false, '');

// Generate filename
    $filename = 'chat_summary_' . str_replace(' ', '_', $tutorial_name) . '_' . date('Y-m-d_H-i-s') . '.pdf';

// Clean output buffer
    if (ob_get_length()) {
        ob_end_clean();
    }

// Output PDF for download
    $pdf->Output($filename, 'D');
    exit;
