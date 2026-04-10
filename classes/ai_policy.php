<?php

namespace block_ai_assistant;

class ai_policy
{
    /**
     * Return whether the user has accepted the AI policy.
     *
     * Uses the Moodle 5.1 core/ai_policy cache which has a datasource
     * (core_ai\cache\policy) that auto-loads from the DB on a cache miss.
     * When tool_consentwithdraw deletes the cache key on revoke, the next
     * call here auto-reloads from the DB and correctly returns false.
     * The acceptance is global (one row per user) — once accepted it applies
     * to every course the user visits.
     *
     * @return bool
     * @throws \dml_exception
     */
    public static function get_policy_status(): bool
    {
        global $DB, $USER;

        try {
            return \core_ai\manager::get_user_policy_status((int) $USER->id);
        } catch (\Throwable $e) {
            // core_ai\manager not available (e.g. Moodle < 4.5) — fall back to direct DB.
            return $DB->record_exists('ai_policy_register', ['userid' => $USER->id]);
        }
    }
}
