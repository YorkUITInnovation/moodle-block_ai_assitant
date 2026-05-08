<?php

/**
 * External Web Service Template
 *
 * @package    localwstemplate
 * @copyright  2011 Moodle Pty Ltd (http://moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/config.php");

use block_ai_assistant\course_modules;
use block_ai_assistant\cria;
use block_ai_assistant\course_module_training;

class block_ai_assistant_course_modules_ws extends external_api
{
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function display_modules_parameters()
    {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED)
            )
        );
    }

    /**
     * Dispalys course modules
     * @param int $course_id
     * @return bool
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws restricted_context_exception
     */
    public static function display_modules($course_id)
    {
        global $OUTPUT;

        //Parameter validation
        $params = self::validate_parameters(
            self::display_modules_parameters(),
            array(
                'courseid' => $course_id
            )
        );

        //Context validation
        $context = \context_course::instance($course_id);
        self::validate_context($context);

        $course_modules = course_modules::get_course_modules($course_id);
        $display = $OUTPUT->render_from_template('block_ai_assistant/course_modules', $course_modules);

        return $display;
    }

    /**
     * Returns method result value
     * @return external_description
     */
    public static function display_modules_returns()
    {
        return new external_value(PARAM_RAW, 'HTML of course modules');
    }


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function display_student_modules_parameters()
    {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED)
            )
        );
    }

    /**
     * Dispalys course modules
     * @param int $course_id
     * @return bool
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws restricted_context_exception
     */
    public static function display_student_modules($course_id)
    {
        global $OUTPUT;

        //Parameter validation
        $params = self::validate_parameters(
            self::display_modules_parameters(),
            array(
                'courseid' => $course_id
            )
        );

        //Context validation
        $context = \context_course::instance($course_id);
        self::validate_context($context);

        $course_modules = course_modules::get_course_modules_available_to_students($course_id);
        $course_modules = ['modules' => array_values($course_modules)];
        $display = $OUTPUT->render_from_template('block_ai_assistant/student_course_modules', $course_modules);

        return $display;
    }

    /**
     * Returns method result value
     * @return external_description
     */
    public static function display_student_modules_returns()
    {
        return new external_value(PARAM_RAW, 'HTML of course modules');
    }

//new webservice to insert module

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function insert_parameters()
    {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
                'selected_modules' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'courseid' => new external_value(PARAM_INT, 'Course id'),
                            'cmid' => new external_value(PARAM_INT, 'CM ID'),
                            'modname' => new external_value(PARAM_TEXT, 'Module Name'),
                            'modtimemodified' => new external_value(PARAM_INT, 'Module Time Modified')
                        )
                    )
                )
            )
        );
    }

    /**
     * inserts course modules
     * @param int $courseid
     * @param array $selected_modules
     * @return array
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws restricted_context_exception
     */
    public static function insert($courseid, $selected_modules)
    {
        global $DB;

        //Parameter validation
        $params = self::validate_parameters(
            self::insert_parameters(),
            array(
                'courseid' => $courseid,
                'selected_modules' => $selected_modules
            )
        );

        $context = \context_course::instance($courseid);
        self::validate_context($context);
        $attempted = 0;
        $trainedcount = 0;
        $failures = [];

        foreach ($selected_modules as $key => $module) {
            if (isset($module['cmid']) && isset($module['courseid'])) {
                $attempted++;
                $file_id = course_modules::insert_record((object)$module); // Ensure the data is cast to an object
                // If there is a file id, send the content to cria
                if ($file_id > 0) {
                    try {
                        // Train the module
                        $trained = self::train_module((int)$module['cmid']);
                        if ($trained) {
                            $trainedcount++;
                            // Delete the module from the array
                            unset($selected_modules[$key]);
                        } else {
                            $failures[] = 'cmid=' . (int)$module['cmid'] . ' training returned false';
                        }
                    } catch (\Throwable $e) {
                        $failures[] = 'cmid=' . (int)$module['cmid'] . ' exception: ' . $e->getMessage();
                    }
                } else {
                    $failures[] = 'cmid=' . (int)$module['cmid'] . ' failed to insert module record';
                }
            }
        }

        if ($attempted === 0) {
            error_log('block_ai_assistant: no modules selected for training in course ' . (int)$courseid);
            return [
                'success' => false,
                'attempted' => 0,
                'trained' => 0,
                'failed' => 0,
                'message' => 'No modules were selected for training.',
            ];
        }

        if (!empty($failures)) {
            error_log('block_ai_assistant: module training issues for course ' . (int)$courseid . ': ' . implode(' | ', $failures));
        }

        $failedcount = max(0, $attempted - $trainedcount);
        $success = $trainedcount > 0;
        $message = 'Training result: 0/' . $attempted . ' modules trained, ' . $failedcount . ' failed.';
        if ($success && $failedcount === 0) {
            $message = 'Training result: ' . $trainedcount . '/' . $attempted . ' modules trained, 0 failed.';
        } else if ($success) {
            $message = 'Training result: ' . $trainedcount . '/' . $attempted . ' modules trained, ' . $failedcount . ' failed.';
        }

        if ($failedcount > 0 && !empty($failures)) {
            $failedcmids = [];
            foreach ($failures as $failure) {
                if (preg_match('/cmid=(\d+)/', (string)$failure, $matches)) {
                    $failedcmids[] = (int)$matches[1];
                }
            }
            $failedcmids = array_values(array_unique($failedcmids));
            if (!empty($failedcmids)) {
                $message .= ' Failed module IDs: ' . implode(', ', $failedcmids) . '.';
            }
        }

        return [
            'success' => $success,
            'attempted' => $attempted,
            'trained' => $trainedcount,
            'failed' => $failedcount,
            'message' => $message,
        ];
    }

    /**
     * Returns method result value
     * @return external_description
     */
    public static function insert_returns()
    {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'True when at least one module was trained'),
                'attempted' => new external_value(PARAM_INT, 'Number of selected modules processed'),
                'trained' => new external_value(PARAM_INT, 'Number of modules successfully trained'),
                'failed' => new external_value(PARAM_INT, 'Number of modules that failed training'),
                'message' => new external_value(PARAM_TEXT, 'Human-readable status message'),
            )
        );
    }


    // Delete course module

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function delete_parameters()
    {
        return new external_function_parameters(
            array(
                'bacmid' => new external_value(PARAM_INT, 'block_aia_course_modules->id', VALUE_REQUIRED)
            )
        );
    }

    /**
     * Deletes course module
     * @param int $cmid
     * @return bool
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws restricted_context_exception
     */
    public static function delete($bacmid)
    {
        global $DB;

        //Parameter validation
        $params = self::validate_parameters(
            self::delete_parameters(),
            array(
                'bacmid' => $bacmid
            )
        );

       course_modules::delete_course_module($params['bacmid']);

        return true;
    }

    /**
     * Returns method result value
     * @return external_description
     */
    public static function delete_returns()
    {
        return new external_value(PARAM_BOOL, 'True or false');
    }

    /**
     * Trains the module based on its type.
     * @param int $cmid
     * @return bool|null
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    private static function train_module(int $cmid)
    {
        $TRAINING = new course_module_training($cmid);
        $trained = false;

        switch ($TRAINING->get_module_type()) {
            case 'forum':
                $trained = $TRAINING->forum();
                break;
            case 'resource':
                $trained =  $TRAINING->resource();
                break;
            case 'page':
                $trained = $TRAINING->page();
                break;
            case 'label':
                $trained = $TRAINING->label();
                break;
            case 'folder':
                $trained = $TRAINING->folder();
                break;
            case 'book':
                $trained = $TRAINING->book();
                break;
            case 'glossary':
                $trained = $TRAINING->glossary();
                break;
            default:
                error_log('block_ai_assistant: unsupported module type for cmid=' . $cmid . ' type=' . $TRAINING->get_module_type());
                $trained = false;
                break;
        }

        return $trained;
    }

}