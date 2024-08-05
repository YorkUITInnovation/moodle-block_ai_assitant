<?php
require_once("$CFG->libdir/tablelib.php");

/**
 * Test table class to be put in test_table.php of root of Moodle installation.
 *  for defining some custom column names and proccessing
 * Username and Password feilds using custom and other column methods.
 */
class questions_table extends table_sql
{

    /**
     * Constructor
     * @param int $uniqueid all tables have to have a unique id, this is used
     *      as a key when storing table properties like sort order in the session.
     */
    function __construct($uniqueid)
    {
        parent::__construct($uniqueid);
        // Define the list of columns to show.
        $columns = array('name', 'value', 'answer', 'actions');
        $this->define_columns($columns);

        // Define the titles of columns to show in header.
        $headers = array('Name', 'Question', 'Answer', 'Actions');
        $this->define_headers($headers);
    }

    /**
     * This function is called for each data row to allow processing of the
     * username value.
     *
     * @param object $values Contains object with all the values of record.
     * @return $string Return username with link to profile or username only
     *     when downloading.
     */
    function col_value($values)
    {
        global $CFG;
        // If the data is being downloaded than we don't want to show HTML.
        if ($this->is_downloading()) {
            return $values->value;
        } else {
            return '<a href="' . $CFG->wwwroot . '/blocks/ai_assistant/questions_edit.php?id=' . $values->id . '&courseid=' . $values->courseid . '">' . $values->value . '</a>';
        }
    }

    /**
     * This function is called for each data row to allow processing of the
     * username value.
     *
     * @param object $values Contains object with all the values of record.
     * @return $string Return username with link to profile or username only
     *     when downloading.
     */
    function col_actions($values)
    {
        global $CFG;
        // If the data is being downloaded than we don't want to show HTML.
        if ($this->is_downloading()) {
            return '';
        } else {
            $html = '<a href="' . $CFG->wwwroot . '/blocks/ai_assistant/questions_edit.php?id=' . $values->id . '&courseid=' . $values->courseid . '"><i class="fa fa-pencil"></i></a>';
            $html .= '<button class="btn btn-link btn-sm block-ai-assistant-delete-question" data-id="' . $values->id . '" data-courseid="' . $values->courseid . '"><i class="fa fa-trash"></i></button>';
            return $html;
        }
    }
}