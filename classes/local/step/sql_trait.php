<?php
// This file is part of Moodle - http://moodle.org/
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

use tool_dataflows\parser;

/**
 * SQL Trait
 *
 * @package    tool_dataflows
 * @author     Jason den Dulk <jasondendulk@catalyst-au.net>
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait sql_trait {

    /**
     * Evaluates exressions inside the given SQL query.
     * E.g. replaces variables inside the query.
     *
     * NOTE: The expression statement itself MUST be on a single line (currently).
     *
     * @param string $sql input sql query string
     * @return array array of evaluated SQL with placeholders, variable values.
     * @throws \moodle_exception when the SQL does not get evaluated correctly.
     */
    private function evaluate_expressions(string $sql) {
        $variables = $this->get_variables();
        $parser = parser::get_parser();

        // Before we try to replace the variables in the parser, we want to move all variables to a seperate string.
        // We should substitute this with ? in the sql for a prepared statement.
        $capturegroups = [];
        $pattern = '/\${{.*}}/mU';
        preg_match_all($pattern, $sql, $capturegroups, PREG_PATTERN_ORDER);

        // Preg_match_all split capture groups into array indexes.
        // There is only a single capture group in the regex, so extract it out from the 0th index.
        $expressions = $capturegroups[0];

        // Now that we have the matches, proceed to replace.
        $sql = preg_replace($pattern, '?', $sql);

        // Now we can map all the variables to their real values.
        $vars = array_map(function($el) use ($variables, $parser) {
            $hasexpression = true;
            $max = 5;
            while ($hasexpression && $max) {
                $el = $variables->evaluate($el, function ($message, $e) {
                    // Process the message and clarify it if required.
                    $message = $this->clarify_parser_error($message);

                    // Log the message.
                    $this->enginestep->log($message);

                    // Throw the original exception (i.e. for the real stack trace).
                    throw $e;
                });

                [$hasexpression] = $parser->has_expression($el);
                $max--;
            }
            if (!in_array(gettype($el), ['string', 'int', 'integer'])) {
                throw new \moodle_exception('sql_trait:sql_param_type_not_valid', 'tool_dataflows', '', gettype($el));
            }
            return $el;
        }, $expressions);

        return [$sql, $vars];
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
            $message = get_string('sql_trait:variable_not_valid_in_position_replacement_text', 'tool_dataflows', $a);
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
}
