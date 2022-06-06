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

use tool_dataflows\local\execution\flow_engine_step;
use tool_dataflows\local\execution\iterators\iterator;
use tool_dataflows\local\execution\iterators\php_iterator;
use tool_dataflows\parser;

/**
 * SQL reader step
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reader_sql extends reader_step {

    private $flowenginestep;

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    protected static function form_define_fields(): array {
        return [
            'sql' => ['type' => PARAM_TEXT],
            'counterfield' => ['type' => PARAM_TEXT],
            'countervalue' => ['type' => PARAM_TEXT],
        ];
    }

    /**
     * Get the iterator for the step, based on configurations.
     *
     * @param flow_engine_step $step
     * @return iterator
     * @throws \moodle_exception
     */
    public function get_iterator(flow_engine_step $step): iterator {
        $this->flowenginestep = $step;
        $query = $this->construct_query($step);
        return new class($step, $query) extends php_iterator {

            public function __construct(flow_engine_step $step, string $query) {
                global $DB;
                $input = $DB->get_recordset_sql($query);
                parent::__construct($step, $input);
            }

            public function abort() {
                $this->input->close();
                $this->finished = true;
            }
        };
    }

    /**
     * Constructs the SQL query from the configuration options.
     *
     * @return string
     * @throws \moodle_exception
     */
    protected function construct_query(): string {
        $config = $this->flowenginestep->stepdef->config;

        $rawsql = $config->sql;
        // Parses the query, removing any optional blocks which cannot be resolved by the containing expression.
        preg_match_all(
            '/(?<fragmentwrapper>' .
            '\[\[(?<fragment>' .
            '.*(?<expressionwrapper>' .
            '\${{(?<expression>.*)}}' .
            ').*' .   // End of expressionwrapper.
            ')\]\]' . // End of fragment.
            ')/mU',   // End of fragment wrapper.
            $rawsql,
            $matches,
            PREG_SET_ORDER);

        // Remove all optional fragments from the raw sql, unless the expressed values are available.
        $finalsql = $rawsql;
        foreach ($matches as $match) {
            // Check expression evaluation using the current config object
            // first, then failing that, target the dataflow variables.
            $parser = new parser();
            $value = $parser->evaluate_or_fail($match['expressionwrapper'], $this->flowenginestep->get_variables());

            // If the expression cannot be evaluated (or evaluates to an empty
            // string), then the query fragment is ignored entirely.
            if ($match['expressionwrapper'] === $value || $value === '') {
                $finalsql = str_replace($match['fragmentwrapper'], '', $finalsql);
                continue;
            }

            // If the expression can be matched, replace the expression with its value, then it's wrapper, etc.
            $parsedmatch = $match;
            $parsedmatch['expressionwrapper'] = $value;
            $parsedmatch['fragment'] = str_replace(
                $match['expressionwrapper'],
                $parsedmatch['expressionwrapper'],
                $match['fragment']
            );
            $parsedmatch['fragmentwrapper'] = $parsedmatch['fragment'];
            $finalsql = str_replace(
                $match['fragmentwrapper'],
                $parsedmatch['fragmentwrapper'],
                $finalsql
            );
        }

        return $finalsql;
    }

    /**
     * Validate the configuration settings.
     *
     * @param object $config
     * @return true|\lang_string[] true if valid, an array of errors otherwise
     */
    public function validate_config($config) {
        $errors = [];
        if (empty($config->sql)) {
            $errors['config_sql'] = get_string('config_field_missing', 'tool_dataflows', 'sql', true);
        }
        return empty($errors) ? true : $errors;
    }

    /**
     * Allows each step type to determine a list of optional/required form
     * inputs for their configuration
     *
     * It's recommended you prefix the additional config related fields to avoid
     * conflicts with any existing fields.
     *
     * @param \MoodleQuickForm &$mform
     */
    public function form_add_custom_inputs(\MoodleQuickForm &$mform) {
        // SQL.
        $mform->addElement('textarea', 'config_sql', get_string('reader_sql:sql', 'tool_dataflows'), ['cols' => 50, 'rows' => 7]);
        // Counter field.
        $mform->addElement('text', 'config_counterfield', get_string('reader_sql:counterfield', 'tool_dataflows'));
        $mform->addElement('static', 'config_counterfield_help', '', get_string('reader_sql:counterfield_help', 'tool_dataflows'));
        // Counter value.
        $mform->addElement('text', 'config_countervalue', get_string('reader_sql:countervalue', 'tool_dataflows'));
        $mform->addElement('static', 'config_countervalue_help', '', get_string('reader_sql:countervalue_help', 'tool_dataflows'));
    }

    /**
     * Step callback handler
     *
     * Updates the counter value if a counterfield is supplied, but otherwise does nothing special to the data.
     */
    public function execute($value) {
        // Check the config for the counterfield.
        $config = $this->flowenginestep->stepdef->config;
        $counterfield = $config->counterfield ?? null;
        if (isset($counterfield)) {
            // Updates the countervalue based on the current counterfield value.
            $this->flowenginestep->set_var('countervalue', $value->{$counterfield});
        }

        return $value;
    }
}
