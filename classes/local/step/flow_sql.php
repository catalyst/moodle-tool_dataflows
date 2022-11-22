<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_dataflows\local\step;

/**
 * SQL flow step
 *
 * @package   tool_dataflows
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flow_sql extends flow_step {
    use sql_trait;

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    public static function form_define_fields(): array {
        return [
            'sql' => ['type' => PARAM_TEXT, 'required' => true]
        ];
    }

    /**
     * Allows each step type to determine a list of optional/required form
     * inputs for their configuration
     *
     * It's recommended you prefix the additional config related fields to avoid
     * conflicts with any existing fields.
     *
     * @param \MoodleQuickForm $mform
     */
    public function form_add_custom_inputs(\MoodleQuickForm &$mform) {
        // SQL example and inputs.
        $sqlexample = "
        SELECT id, username
          FROM {user}
      WHERE id > \${{steps.xyz.var.number}}
      ORDER BY id ASC
         LIMIT 10";
        $sqlexamples = \html_writer::tag('pre', trim($sqlexample, " \t\r\0\x0B"));
        $mform->addElement('textarea', 'config_sql', get_string('flow_sql:sql', 'tool_dataflows'),
            ['cols' => 50, 'rows' => 7, 'style' => 'font: 87.5% monospace;']);
        $mform->addElement('static', 'config_sql_help', '', get_string('flow_sql:sql_help', 'tool_dataflows', $sqlexamples));
    }

    /**
     * Execute configured query
     *
     * @param   mixed $input
     * @return  mixed
     * @throws \dml_read_exception when the SQL is not valid.
     */
    public function execute($input = null) {
        global $DB;

        // Construct the query.
        $variables = $this->get_variables();
        $config = $variables->get('config');
        $sql = $this->evaluate_expressions($config->sql);

        // Execute the query using get_records instead of get_record.
        // This is so we can expose the number of records returned which
        // can then be used by the dataflow in for e.g. a switch statement.
        $records = $DB->get_records_sql($sql);

        $variables->set('count', count($records));

        // Only return the query data if there is exactly 1 record.
        // If multiple records are returned, it is undefined what should happen.
        $invalidnum = ($records === false || count($records) !== 1);
        $data = $invalidnum ? null : array_pop($records);
        $variables->set('data', $data);
    }

    /**
     * Validate the configuration settings.
     *
     * @param   object $config
     * @return  true|\lang_string[] true if valid, an array of errors otherwise
     */
    public function validate_config($config) {
        $errors = [];
        if (empty($config->sql)) {
            $errors['config_sql'] = get_string('config_field_missing', 'tool_dataflows', 'sql', true);
        }

        return empty($errors) ? true : $errors;
    }
}
