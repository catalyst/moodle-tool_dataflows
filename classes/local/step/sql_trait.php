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
            if (!in_array(gettype($el), ['string', 'int'])) {
                throw new \moodle_exception('sql_trait:sql_param_type_not_valid', 'tool_dataflows');
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
}
