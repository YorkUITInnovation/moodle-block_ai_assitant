<?php

namespace block_ai_assistant;

class ai_policy
{
    /**
     * Return whether the user has accepted the AI policy.
     * @return bool
     * @throws \dml_exception
     */
    public static function get_policy_status()
    {
        global $DB, $USER;

        if (!$DB->record_exists('ai_policy_register', array('userid' => $USER->id))) {
            return false; // User has not accepted the policy
        }

        return true;
    }

}