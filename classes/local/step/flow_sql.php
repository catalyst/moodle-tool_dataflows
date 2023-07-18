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
            'sql' => ['type' => PARAM_TEXT, 'required' => true],
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
            ['max_rows' => 40, 'rows' => 5, 'style' => 'font: 87.5% monospace; width: 100%; max-width: 100%']);
        $mform->addElement('static', 'config_sql_help', '', get_string('flow_sql:sql_help', 'tool_dataflows', $sqlexamples));
    }

    /**
     * Allow steps to setup the form depending on current values.
     *
     * This method is called after definition(), data submission and set_data().
     * All form setup that is dependent on form values should go in here.
     *
     * @param \MoodleQuickForm $mform
     * @param \stdClass $data
     */
    public function form_definition_after_data(\MoodleQuickForm &$mform, \stdClass $data) {
        // Validate the data.
        $sqllinecount = count(explode(PHP_EOL, trim($data->config_sql)));

        // Get the element.
        $element = $mform->getElement('config_sql');

        // Update the element height based on min/max settings, but preserve
        // other existing rules.
        $attributes = $element->getAttributes();

        // Set the rows at a minimum to the predefined amount in
        // form_add_custom_inputs, and expand as content grows up to a maximum.
        $attributes['rows'] = min(
            $attributes['max_rows'],
            max($attributes['rows'], $sqllinecount)
        );
        $element->setAttributes($attributes);
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
        $config = $variables->get_raw('config');
        [$sql, $params] = $this->evaluate_expressions($config->sql);

        // Now that we have the query, we want to get info on SQL keywords to figure out where to route the request.
        // This is not used for security, just to route the request via the correct pathway for readonly databases.
        $pattern = '/(SELECT|UPDATE|INSERT|DELETE)/im';
        $matches = [];
        preg_match($pattern, $sql, $matches);

        // Matches[0] contains the match. Fallthrough to default on no match.
        $token = $matches[0] ?? '';
        $emptydefault = new \stdClass();

        switch(strtoupper($token)) {
            case 'SELECT':
                // Execute the query using get_records instead of get_record.
                // This is so we can expose the number of records returned which
                // can then be used by the dataflow in for e.g. a switch statement.
                $records = $DB->get_records_sql($sql, $params);

                $variables->set('count', count($records));
                $invalidnum = ($records === false || count($records) !== 1);
                $data = $invalidnum ? $emptydefault : array_pop($records);
                $variables->set('data', $data);
                break;
            default:
                // Default to execute.
                $success = $DB->execute($sql, $params);

                // We can't really do anything with the response except check for success.
                $variables->set('count', (int) $success);
                $variables->set('data', $emptydefault);
                break;
        }

        return $input;
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
