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

        return new class($this->enginestep, $query) extends dataflow_iterator {

            /**
             * Create an instance of this class.
             *
             * @param  flow_engine_step $step
             * @param  string $query
             */
            public function __construct(flow_engine_step $step, string $query) {
                global $DB;
                $input = $DB->get_recordset_sql($query);
                parent::__construct($step, $input);
            }

            /**
             * Any custom handling for on_abort
             */
            public function on_abort() {
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
     * @return string
     * @throws \moodle_exception
     */
    protected function construct_query(): string {
        // Get variables.
        $variables = $this->enginestep->get_variables();

        // Get raw SQL (because we need to know when to and when not to use the query fragments).
        $rawconfig = $this->enginestep->stepdef->get_raw_config();
        $rawsql = $rawconfig->sql;

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
        $parser = new parser();
        $finalsql = $rawsql;
        try {
            $errormsg = '';
            foreach ($matches as $match) {
                // Check expression evaluation using the current config object
                // first, then failing that, target the dataflow variables.
                $value = $parser->evaluate_or_fail(
                    $match['expressionwrapper'],
                    $variables,
                    function ($message, $e = null) use ($rawsql, &$errormsg) {
                        // Process the message and clarify it if required.
                        $message = $this->clarify_parser_error($message, $rawsql);
                        if (isset($e)) {
                            $errormsg = $message;
                            throw $e;
                        } else {
                            $this->enginestep->log($message);
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
                $this->enginestep->log($errormsg);
            }
            throw $e;
        }

        // Evalulate any remaining expressions as per normal.
        // NOTE: The expression statement itself MUST be on a single line (currently).
        $hasexpression = true;
        $max = 5;
        while ($hasexpression && $max) {
            $finalsql = $parser->evaluate_or_fail($finalsql, $variables, function ($message, $e) {
                // Process the message and clarify it if required.
                $message = $this->clarify_parser_error($message);

                // Log the message.
                $this->enginestep->log($message);

                // Throw the original exception (i.e. for the real stack trace).
                throw $e;
            });
            [$hasexpression] = $parser->has_expression($finalsql);
            $max--;
        }

        return $finalsql;
    }

    /**
     * Returns a clarified error message if applicable.
     *
     * @param   string $message
     * @param   string $sql replacement for the expression held by default.
     * @return  string clarified message if applicable
     */
    private function clarify_parser_error(string $message, ?string $sql = null): string {
        // Check and massage the message if required.
        $matches = null;
        preg_match_all(
            // phpcs:disable moodle.Strings.ForbiddenStrings.Found
            '/Variable "(?<expressionpath>.*)" is not valid.*`(?<expression>.*)`.*for expression (?<sql>.*)/ms',
            $message,
            $matches,
            PREG_SET_ORDER);

        if (!empty($matches)) {
            // Modify the SQL, adding a pointer to the first instance of the usage which resulted in the error.
            $match = (object) reset($matches);

            // Replace the field (using the sql key) in the matching expression with the one provided.
            if (isset($sql)) {
                $match->sql = $sql;
            }

            // Pinpoint the line and column of the expression in the SQL.
            $line = 0;
            $column = 0;
            $sqlbylines = explode("\n", $match->sql);
            foreach ($sqlbylines as $lineindex => $linecontents) {
                $position = strpos($linecontents, $match->expression);
                if ($position !== false) {
                    $column = $position + 1;
                    $line = $lineindex + 1;
                    break;
                }
            }
            // Insert the characters in the following line.
            $sqlwithannotations = $this->draw_arrow_to_string_position($match->sql, $column, $line);
            $a = (object) array_merge((array) $match, [
                'sql' => $sqlwithannotations,
                'column' => $column,
                'line' => $line,
            ]);
            $message = get_string('reader_sql:variable_not_valid_in_position_replacement_text', 'tool_dataflows', $a);
        }

        return $message;
    }

    /**
     * Draws an arrow pointing at the focused position in a string
     *                                ^
     * @param   string $string of the original contents
     * @param   int $column column position to focus on
     * @param   int $line line position to focus on
     * @return  string with arrow annotation
     */
    private function draw_arrow_to_string_position(string $string, int $column, int $line) {
        // Split by lines to ensure we insert it at the appropriate line.
        $sqlbylines = explode("\n", $string);

        // Insert the characters in the following line.
        $arrow = str_pad('', $column - 1, ' ') . '^';
        array_splice($sqlbylines, $line, 0, $arrow);

        return implode("\n", $sqlbylines);
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
            ['cols' => 50, 'rows' => 7, 'style' => 'font: 87.5% monospace;']);
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
     *
     * @param  \stdClass $value
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
