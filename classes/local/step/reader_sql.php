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
use tool_dataflows\local\execution\iterators\dude_iterator;
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
     * @return iterator
     * @throws \moodle_exception
     */
    public function get_iterator(): iterator {
        $query = $this->construct_query();
        return new class($this->enginestep, $query) extends dude_iterator {

            public function __construct(flow_engine_step $step, string $query) {
                global $DB;
                $input = $DB->get_recordset_sql($query);
                parent::__construct($step, $input);
            }

            public function on_abort() {
                $this->input->close();
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
        $variables = $this->enginestep->get_variables();
        $rawsql = trim($variables['step']->config->sql);

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
        $finalsql = $rawsql;
        $parser = new parser();
        try {
            $errormsg = '';
            foreach ($matches as $match) {
                // Check expression evaluation using the current config object
                // first, then failing that, target the dataflow variables.
                $value = $parser->evaluate_or_fail(
                    $match['expressionwrapper'],
                    $variables,
                    function ($message, $e = null) use ($rawsql, &$errormsg) {
                        if (isset($e)) {
                            $errormsg = $message;
                            throw $e;
                        } else {
                            $this->enginestep->log($message . " in the following query:\n{$rawsql}");
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
            $message = "in query:\n{$rawsql}";
            if (!empty($errormsg)) {
                $message = "$errormsg $message";
            }
            $this->enginestep->log($message);
            throw $e;
        }

        // Evalulate any remaining expressions as per normal.
        // NOTE: The expression statement itself MUST be on a single line (currently).
        $finalsql = $parser->evaluate_or_fail($finalsql, $variables, function ($message, $e) {
            $this->enginestep->log($message);
            throw $e;
        });
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
        $sqlexamples = "
            SELECT id, username
              FROM {user}
          [[ WHERE id > \${{countervalue}} ]]
          ORDER BY id ASC
             LIMIT 10";
        $sqlexamples = \html_writer::tag('pre', trim($sqlexamples, " \t\r\0\x0B"));
        $mform->addElement('textarea', 'config_sql', get_string('reader_sql:sql', 'tool_dataflows'), ['cols' => 50, 'rows' => 7]);
        $mform->addElement('static', 'config_sql_help', '', get_string('reader_sql:sql_help', 'tool_dataflows', $sqlexamples));
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
        $config = $this->enginestep->stepdef->config;
        $counterfield = $config->counterfield ?? null;

        if (isset($counterfield)) {
            $parser = new parser();
            [$hasexpression] = $parser->has_expression($counterfield);
            if ($hasexpression) {
                $resolvedcounterfield = $parser->evaluate(
                    $counterfield,
                    $this->enginestep->get_variables()
                );
                $counterfield = null;
                if ($resolvedcounterfield !== $counterfield) {
                    $counterfield = $resolvedcounterfield;
                }
            }
            if (!empty($counterfield)) {
                // Updates the countervalue based on the current counterfield value.
                $this->enginestep->set_var('countervalue', $value->{$counterfield});
            }
        }

        return $value;
    }
}
