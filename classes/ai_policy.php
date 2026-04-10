<?php

namespace block_ai_assistant;

class ai_policy
{
    /**
     * Return whether the user has accepted the AI policy.
     * Checks the Moodle 5.1 core cache first, then falls back to the DB.
     *
     * The acceptance is global (one row per user in ai_policy_register) —
     * once accepted it applies to every course the user visits.
     *
     * @return bool
     * @throws \dml_exception
     */
    public static function get_policy_status(): bool
    {
        global $DB, $USER;

        // Moodle's cache::get() returns false for both a missing key AND a stored false value,
        // so we can only trust it when it returns true (meaning user_policy_accepted() was called
        // and stored true in the cache for this user).
        try {
            $cached = \core_ai\manager::get_user_policy_status((int) $USER->id);
            if ($cached === true) {
                return true;
            }
        } catch (\Throwable $e) {
            // core_ai\manager not available — fall through to DB.
        }

        // Always verify against the DB as the source of truth.
        return $DB->record_exists('ai_policy_register', ['userid' => $USER->id]);
    }

    /**
     * Return whether any AI tool is active for the given course context.
     *
     * "Active" means either:
     *   - Moodle core AI tools are enabled on the course (course.enableaitools = 1), OR
     *   - The block_ai_assistant bot has been published for the course.
     *
     * @param \context_course $course_context
     * @param int             $courseid
     * @return bool
     * @throws \dml_exception
     */
    public static function is_ai_active_in_course(\context_course $course_context, int $courseid): bool
    {
        global $DB;

        // 1. Check Moodle core AI tools flag on the course row.
        $enableaitools = $DB->get_field('course', 'enableaitools', ['id' => $courseid]);
        if (!is_null($enableaitools) && (bool) $enableaitools) {
            return true;
        }

        // 2. Check whether the block AI assistant bot is published for this course.
        $published = $DB->get_field('block_aia_settings', 'published', ['courseid' => $courseid]);
        if ($published) {
            return true;
        }

        return false;
    }
}
