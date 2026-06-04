<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/grade/constants.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/grade/grade_category.php');
require_once($CFG->libdir . '/grade/grade_item.php');

use block_ai_assistant\cria;

list($options, $unrecognized) = cli_get_params(
    ['help' => false, 'courseid' => 0],
    ['h' => 'help', 'c' => 'courseid']
);

if ($unrecognized) {
    cli_error('Unknown options: ' . implode(' ', $unrecognized));
}

if (!empty($options['help'])) {
    echo "Remove stale duplicate course-total grade_items for a course.\n\n";
    echo "Options:\n";
    echo "  -c, --courseid   Course id (required)\n";
    echo "  -h, --help       Show this help\n";
    exit(0);
}

$courseid = (int)($options['courseid'] ?? 0);
if ($courseid < 1) {
    cli_error('Missing required --courseid');
}

$result = cria::ensure_course_gradebook_integrity($courseid);

cli_writeln('Course ' . $courseid . ' gradebook integrity:');
cli_writeln('  removed orphan rows: ' . (int)$result['removed']);
cli_writeln('  structural fixes:    ' . (int)$result['fixed']);
cli_writeln('  course-total items:  ' . (int)$result['course_items']);

if ((int)$result['course_items'] !== 1) {
    cli_error('Expected exactly 1 course-total grade_item after cleanup.');
}

exit(0);
