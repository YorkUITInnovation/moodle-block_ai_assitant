<?php

require_once("../../config.php");

global $CFG, $DB, $OUTPUT, $USER, $PAGE;



$courseid = required_param('courseid', PARAM_INT);

$context = context_course::instance($courseid);

require_login($courseid, false);

$PAGE->set_url(new moodle_url('/blocks/ai_assistant/instructor_user_guide.php', []));
$PAGE->set_title(get_string('user_guide', 'block_ai_assistant'));
$PAGE->set_heading('');
$PAGE->set_pagelayout('standard');
$PAGE->set_context($context);

$user_guide = file_get_contents($CFG->dirroot . '/blocks/ai_assistant/doc/instructor_user_guide.md');
$html = markdown_to_html($user_guide);

// Inject id attributes on headings so TOC anchor links work.
// Converts heading text to a GitHub-style slug: lowercase, spaces to hyphens,
// strip everything except alphanumerics, hyphens and spaces.
$html = preg_replace_callback(
    '/<(h[1-6])>(.*?)<\/h[1-6]>/is',
    function ($matches) {
        $tag  = $matches[1];
        $text = strip_tags($matches[2]);
        // GitHub-style slug
        $slug = strtolower($text);
        $slug = preg_replace('/[^\w\s-]/u', '', $slug);   // remove punctuation except hyphens
        $slug = preg_replace('/[\s]+/', '-', trim($slug)); // spaces → hyphens
        $slug = preg_replace('/-+/', '-', $slug);          // collapse multiple hyphens
        return '<' . $tag . ' id="' . htmlspecialchars($slug) . '">' . $matches[2] . '</' . $tag . '>';
    },
    $html
);

echo $OUTPUT->header();
echo $html;
echo $OUTPUT->footer();
