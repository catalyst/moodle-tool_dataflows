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

/**
 * SQL reader step
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_dataflows\local\step;

use tool_dataflows\local\execution\flow_engine_step;
use tool_dataflows\local\execution\iterators\iterator;
use tool_dataflows\local\execution\iterators\dataflow_iterator;
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
    use sql_trait;

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    public static function form_define_fields(): array {
        return [
            'sql' => ['type' => PARAM_TEXT, 'required' => true],
            'counterfield' => ['type' => PARAM_TEXT],
            'countervalue' => ['type' => PARAM_TEXT],
        ];
    }

    /**
     * Can dataflows with this step be executed in parallel.
     *
     * @return string|true True if concurrency is supported, or a string giving a reason why it doesn't.
     */
    public function is_concurrency_supported() {
        if (isset($this->stepdef)) {
            $config = $this->get_variables()->get('config');
            return empty($config->counterfield) ? true : get_string('reader_sql:counterfield_not_empty', 'tool_dataflows');
        }

        // If we don't know the stepdef, the play it safe and return false.
        return get_string('reader_sql:settings_unknown', 'tool_dataflows');
    }

    /**
     * Get the iterator for the step, based on configurations.
     *
     * @return iterator
     * @throws \moodle_exception
     */
    public function get_iterator(): iterator {
        [$query, $params] = $this->construct_query();

        return new class($this->enginestep, $query, $params) extends dataflow_iterator {

            /**
             * Create an instance of this class.
             *
             * @param  flow_engine_step $step
             * @param  string $query
             * @param  array $params
             */
            public function __construct(flow_engine_step $step, string $query, array $params) {
                global $DB;
                $input = $DB->get_recordset_sql($query, $params);
                parent::__construct($step, $input);
            }

            /**
             * Called when the iterator is stopped, either because of finishing ar due to an abort.
             */
            public function on_stop() {
                $this->input->close();
            }
        };
    }

    /**
     * Constructs the SQL query from the configuration options.
     *
     * Optional query fragments denoted by [[ ${{ expression }} ]], must have an
     * expression linking to a variable / field that does NOT contain another
     * expression as this is not currently supported.
     *
     * @return array of SQL and parameters.
     * @throws \moodle_exception
     */
    protected function construct_query(): array {
        // Get variables.
        $variables = $this->get_variables();

        // Get raw SQL (because we need to know when to and when not to use the query fragments).
        $rawsql = $this->stepdef->config->sql;

        // Parses the query, removing any optional blocks which cannot be resolved by the containing expression.
        preg_match_all(
            '/(?<fragmentwrapper>' .
            '\[\[(?<fragment>' .
            '.*(?<expressionwrapper>' .
            '\${{(?<expression>.*)}}' .
            ').*' .   // End of expressionwrapper.
            ')\]\]' . // End of fragment.
            ')/msU',   // End of fragment wrapper.
            $rawsql,
            $matches,
            PREG_SET_ORDER);

        // Remove all optional fragments from the raw sql, unless the expressed values are available.
        $parser = parser::get_parser();
        $finalsql = $rawsql;
        try {
            $errormsg = '';
            foreach ($matches as $match) {
                // Check expression evaluation using the current config object
                // first, then failing that, target the dataflow variables.
                $value = $variables->evaluate(
                    $match['expressionwrapper'],
                    function ($message, $e = null) use ($rawsql, &$errormsg) {
                        // Process the message and clarify it if required.
                        $message = $this->clarify_parser_error($message, $rawsql);
                        if (isset($e)) {
                            $errormsg = $message;
                            throw $e;
                        } else {
                            $this->enginestep->log->info($message);
                        }
                    }
                );

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
        } catch (\Throwable $e) {
            if (!empty($errormsg)) {
                $this->enginestep->log->error($errormsg);
            }
            throw $e;
        }

        // Evalulate any remaining expressions as per normal.
        return $this->evaluate_expressions($finalsql);
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
        // SQL.
        $sqlexamples = "
  SELECT id,
         username
    FROM {user}
[[ WHERE id > \${{countervalue}} ]]
ORDER BY id ASC
   LIMIT 10";
        $sqlexamples = \html_writer::tag('pre', trim($sqlexamples, " \t\r\0\x0B"));

        $mform->addElement('textarea', 'config_sql', get_string('reader_sql:sql', 'tool_dataflows'),
            ['max_rows' => 40, 'rows' => 5, 'style' => 'font: 87.5% monospace; width: 100%; max-width: 100%']);
        $mform->addElement('static', 'config_sql_help', '', get_string('reader_sql:sql_help', 'tool_dataflows', $sqlexamples));

        // Counter field.
        $mform->addElement('text', 'config_counterfield', get_string('reader_sql:counterfield', 'tool_dataflows'));
        $mform->addElement('static', 'config_counterfield_help', '', get_string('reader_sql:counterfield_help', 'tool_dataflows'));

        // Counter value.
        $mform->addElement('text', 'config_countervalue', get_string('reader_sql:countervalue', 'tool_dataflows'));
        $mform->addElement('static', 'config_countervalue_help', '', get_string('reader_sql:countervalue_help', 'tool_dataflows'));
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
     * Step callback handler
     *
     * Updates the counter value if a counterfield is supplied, but otherwise does nothing special to the data.
     *
     * @param   mixed $input
     * @return  mixed
     */
    public function execute($input = null) {
        // Check the config for the counterfield.
        $variables = $this->get_variables();
        $config = $variables->get('config');
        if (!empty($config->counterfield)) {
            $counterfield = $config->counterfield;
            $variables->set('config.countervalue', $input->{$counterfield});
            $this->stepdef->set_config_by_name('countervalue', $input->{$counterfield});
            if (!$this->is_dry_run()) {
                $this->stepdef->save();
            }
        }

        return $input;
    }
}
