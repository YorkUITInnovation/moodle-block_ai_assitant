<?php
/**
 * CLI script to fix no_context_use_message and push updated bot config to CRIA for all courses.
 *
 * Usage: php blocks/ai_assistant/cli/fix_no_context.php
 */
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use block_ai_assistant\cria;

// Ensure no_context_use_message = 1 and no_context_llm_guess = 0 in plugin config.
set_config('no_context_use_message', 1, 'block_ai_assistant');
set_config('no_context_llm_guess', 0, 'block_ai_assistant');
cli_writeln('Set no_context_use_message = 1, no_context_llm_guess = 0 in plugin config.');

// Get all courses that have a bot configured.
$records = $DB->get_records('block_aia_settings', [], '', 'id, courseid, bot_name, no_context_message');

if (empty($records)) {
    cli_writeln('No block_aia_settings records found.');
    exit(0);
}

$global_no_context = get_config('block_ai_assistant', 'no_context_message');

foreach ($records as $record) {
    // Fix null/empty no_context_message in DB record - set to global default.
    if (empty($record->no_context_message)) {
        $DB->set_field('block_aia_settings', 'no_context_message', $global_no_context, ['id' => $record->id]);
        $record->no_context_message = $global_no_context; // keep in sync for display below
        cli_writeln("Fixed null no_context_message for courseid {$record->courseid}");
    }

    // Get the bot ID from the bot_name field.
    if (empty($record->bot_name)) {
        cli_writeln("Skipping courseid {$record->courseid} - no bot_name set.");
        continue;
    }

    $bot_parts = explode('-', $record->bot_name);
    $botid = intval(str_replace('"', '', $bot_parts[0]));

    if ($botid <= 0) {
        cli_writeln("Skipping courseid {$record->courseid} - invalid botid (raw bot_name: {$record->bot_name}).");
        continue;
    }

    cli_writeln("Pushing to CRIA - courseid {$record->courseid}, botid {$botid}, no_context_message: \"{$record->no_context_message}\"");

    // Push updated config to CRIA.
    try {
        $result = cria::update_bot_instance($record->courseid, $botid);
        cli_writeln("  -> Success. CRIA response: " . print_r($result, true));
    } catch (Exception $e) {
        cli_writeln("  -> ERROR: " . $e->getMessage());
    }
}

cli_writeln('Done.');
