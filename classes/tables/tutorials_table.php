<?php
require_once("$CFG->libdir/tablelib.php");
/**
 * Tutorials table class for defining custom column names and processing
 * Tutorial data with custom column methods.
 */
class tutorials_table extends table_sql {

    /**
     * Constructor
     * @param int $uniqueid all tables have to have a unique id, this is used
     *      as a key when storing table properties like sort order in the session.
     */
    function __construct($uniqueid) {
        parent::__construct($uniqueid);
        // Define the list of columns to show.
        $columns = array('name', 'prompt', 'enabled', 'actions');
        $this->define_columns($columns);

        // Define the titles of columns to show in header.
        $headers = array(
            get_string('name', 'block_ai_assistant'),
            get_string('prompt', 'block_ai_assistant'),
            get_string('enabled', 'block_ai_assistant'),
            get_string('actions', 'block_ai_assistant')
        );
        $this->define_headers($headers);
    }

    /**
     * This function is called for each data row to allow processing of the
     * title value.
     *
     * @param object $values Contains object with all the values of record.
     * @return $string Return title with link to edit or title only
     *     when downloading.
     */
    function col_title($values) {
        global $CFG;
        // If the data is being downloaded than we don't want to show HTML.
        if ($this->is_downloading()) {
            return $values->title;
        } else {
            return '<a href="'. $CFG->wwwroot . '/blocks/ai_assistant/edit_tutorial.php?id='. $values->id .'&courseid=' . $values->courseid . '">' . $values->title.'</a>';
        }
    }

    /**
     * This function is called for each data row to allow processing of the
     * actions column.
     *
     * @param object $values Contains object with all the values of record.
     * @return $string Return action buttons or empty string when downloading.
     */
    function col_actions($values) {
        global $CFG;
        // If the data is being downloaded than we don't want to show HTML.
        if ($this->is_downloading()) {
            return '';
        } else {
            $html = '<a href="'. $CFG->wwwroot . '/blocks/ai_assistant/edit_tutorial.php?id='.$values->id .'&courseid=' . $values->courseid . '"><i class="fa fa-pencil"></i></a>';
            $html .= '<button class="btn btn-link btn-sm block-ai-assistant-delete-tutorial" data-id="'. $values->id.'" data-courseid="' . $values->courseid . '"><i class="fa fa-trash"></i></button>';
            return $html;
        }
    }

    function col_enabled($values) {
        // If the data is being downloaded than we don't want to show HTML.
        if ($this->is_downloading()) {
            return $values->enabled ? 'Yes' : 'No';
        } else {
            return $values->enabled ? '<i class="fa fa-check text-success"></i>' : '<i class="fa fa-times text-danger"></i>';
        }
    }
}
