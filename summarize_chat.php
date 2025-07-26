<?php

require_once(__DIR__ . '/../../config.php');

use block_ai_assistant\cria;

global $CFG, $DB, $USER;
require_once($CFG->libdir . '/tcpdf/tcpdf.php');

$chatid = required_param('chatid', PARAM_TEXT);
$bot_name = optional_param('botname', '', PARAM_TEXT);
// Get Chat history form Cria
$full_chat_history = cria::chat_history($chatid);
$chat_history = json_decode($full_chat_history->history);
$messages = [];
if (isset($chat_history->history)) {
    $tutorial_name = $DB->get_field(
        'block_aia_tutorial_chats',
        'name',
        ['chatid' => $chatid]
    );
    $history = $chat_history->history;
    for ($i = 0; $i < count($history); $i++) {
        if ($i > 3) {
            if ($history[$i]->role == 'user') {
                $is_human = true;
            } else {
                $is_human = false;
            }
            $messages[] = [
                'is_human' => $is_human,
                'message' => $history[$i]->blocks[0]->text,
            ];
        }
    }
}

$user = $DB->get_record('user', ['id' => $USER->id]);
$full_name = fullname($user);

// Build Markdown content for the PDF
$content = 'Summarize the chat history below in as much detail as possible:' . "\n\n";
$content .= '**Tutorial:** ' . ($tutorial_name ?? 'AI Assistant Chat') . "\n\n";
$content .= '**User:** ' . $full_name . "\n\n";
$content .= '**Date:** ' . date('Y-m-d H:i:s') . "\n\n";
$content .= '---' . "\n\n";

foreach ($messages as $message) {
    if ($message['is_human']) {
        $content .= '**' . $full_name . ':**' . "\n\n";
    } else {
        $content .= '**AI Assistant:**' . "\n\n";
    }
    $content .= $message['message'] . "\n\n";
}

//get a new chat session
$chat_session = cria::chat_start();

$summary = cria::chat_send($chat_session, $content, $bot_name);

// Delete the chat session after summarization
cria::chat_end($chat_session);

// Create PDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('AI Assistant');
$pdf->SetAuthor($full_name);
$pdf->SetTitle('Chat Summary - ' . ($tutorial_name ?? 'AI Assistant Chat'));
$pdf->SetSubject('AI Assistant Chat Summary');

// Set default header data
$pdf->SetHeaderData('', 0, 'AI Assistant Chat Summary', ($tutorial_name ?? 'Chat Session') . ' - ' . date('Y-m-d H:i:s'));

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
$html = '<h2>Chat Summary</h2>';
$html .= '<p><strong>Tutorial:</strong> ' . htmlspecialchars($tutorial_name ?? 'AI Assistant Chat') . '</p>';
$html .= '<p><strong>User:</strong> ' . htmlspecialchars($full_name) . '</p>';
$html .= '<p><strong>Date:</strong> ' . date('Y-m-d H:i:s') . '</p>';
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
