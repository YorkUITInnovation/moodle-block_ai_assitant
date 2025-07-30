<?php

require_once($CFG->libdir . "/externallib.php");
require_once("$CFG->dirroot/config.php");

use block_ai_assistant\cria;
use block_ai_assistant\course_modules;

class block_ai_assistant_tutorial_ws extends external_api
{
    /**
     * Returns description of method parameters for delete
     */
    public static function delete_parameters() {
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, 'Tutorial id', VALUE_REQUIRED),
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED)
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
    public static function delete($id, $courseid) {
        global $CFG, $USER, $DB, $PAGE;

        $params = self::validate_parameters(
            self::delete_parameters(),
            array('id' => $id, 'courseid' => $courseid)
        );

        $context = CONTEXT_COURSE::instance($params['courseid']);
        self::validate_context($context);

        $tutorial = $DB->get_record('block_aia_tutorials', array('id' => $params['id']));
        if ($tutorial) {
            $DB->delete_records('block_aia_tutorials', array('id' => $params['id']));
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns return description for delete
     */
    public static function delete_returns() {
        return new external_value(PARAM_BOOL, 'True if record was deleted');
    }
}