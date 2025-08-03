<?php

require_once($CFG->libdir . "/externallib.php");
require_once("$CFG->dirroot/config.php");

use block_ai_assistant\cria;
use block_ai_assistant\course_modules;

class block_ai_assistant_ai_policy_ws extends external_api
{
    /**
     * Returns description of method parameters for delete
     */
    public static function execute_parameters()
    {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'Context id', VALUE_REQUIRED)
            )
        );
    }

    /**
     * Deletes a tutorial record
     * @param int $id
     * @param int $courseid
     * @return bool
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws restricted_context_exception
     */
    public static function execute($contextid)
    {
        global $CFG, $USER, $DB, $PAGE;

        $params = self::validate_parameters(
            self::execute_parameters(),
            array(
                'contextid' => $contextid
            )
        );

        $context = context_user::instance($USER->id);
        self::validate_context($context);
        // Prepare the data to save user policy acceptance
        $params = array(
            'userid' => $USER->id,
            'contextid' => $params['contextid'],
            'timeaccepted' => time()
        );

        if ($DB->insert_record('ai_policy_register', $params)) {
            return true;
        }
        return false;
    }

    /**
     * Returns return description for delete
     */
    public static function execute_returns()
    {
        return new external_value(PARAM_BOOL, 'True if policy was registered successfully');
    }
}