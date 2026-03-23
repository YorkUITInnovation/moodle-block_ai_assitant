<?php

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;

class block_ai_assistant_ai_policy_ws extends external_api
{
    /**
     * Returns description of method parameters
     */
    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'Context id', VALUE_REQUIRED)
            )
        );
    }

    /**
     * Registers the user's acceptance of the AI policy.
     * In Moodle 5.1+, ai_policy_register is a native core table.
     * @param int $contextid
     * @return bool
     * @throws \dml_exception
     * @throws \core_external\invalid_parameter_exception
     * @throws \core_external\restricted_context_exception
     */
    public static function execute($contextid): bool
    {
        global $USER, $DB;

        $params = self::validate_parameters(
            self::execute_parameters(),
            array(
                'contextid' => $contextid
            )
        );

        $context = \context_user::instance($USER->id);
        self::validate_context($context);

        // In Moodle 5.1+, ai_policy_register is a native core table.
        // Check if the user has already accepted to avoid duplicate records.
        if (!$DB->record_exists('ai_policy_register', ['userid' => $USER->id])) {
            $record = [
                'userid'       => $USER->id,
                'contextid'    => $params['contextid'],
                'timeaccepted' => time(),
            ];
            if ($DB->insert_record('ai_policy_register', $record)) {
                return true;
            }
            return false;
        }

        return true; // Already accepted
    }

    /**
     * Returns return description
     */
    public static function execute_returns(): external_value
    {
        return new external_value(PARAM_BOOL, 'True if policy was registered successfully');
    }
}
