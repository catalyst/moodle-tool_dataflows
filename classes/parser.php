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

namespace tool_dataflows;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Expression Parsing helper class
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class parser {

    public function __construct() {
        $expressionlanguage = new ExpressionLanguage();
        $this->expressionlanguage = $expressionlanguage;
    }

    /**
     * Given an expression, and data. Evaluate and return the result.
     *
     * @param      string $expression
     * @param      array $variables containing anything relevant to the evaluation of the result
     * @return     mixed
     */
    public function evaluate(string $expression, array $variables) {
        return $this->internal_evaluator($expression, $variables, false);
    }

    /**
     * Evalulates the expression provided and on fail will throw an exception.
     *
     * @param      string $expression
     * @param      array $variables containing anything relevant to the evaluation of the result
     * @return     mixed
     */
    public function evaluate_or_fail(string $expression, array $variables) {
        return $this->internal_evaluator($expression, $variables, true);
    }

    /**
     * Evalulates the expressions in the string provided.
     *
     * @param      string $expression
     * @param      array $variables containing anything relevant to the evaluation of the result
     * @return     mixed
     */
    private function internal_evaluator(string $expression, array $variables, $throwonfail) {
        $evaluatedexpression = $expression;
        preg_match_all('/\${{(?<expression>.*)}}/mU', $expression, $matches, PREG_SET_ORDER);
        if (!empty($matches)) {
            foreach ($matches as $match) {
                // Try and evalulate the expression. For results that could
                // not be matched, log this since it's probably defined in
                // the wrong spot.
                try {
                    $result = $this->expressionlanguage->evaluate(
                        trim($match['expression']),
                        $variables
                    );
                    // Set the evalulated expression to the one that passed through the expression language.
                    $evaluatedexpression = str_replace($match[0], $result, $evaluatedexpression);
                } catch (\Exception $e) {
                    if ($throwonfail) {
                        mtrace("Issue parsing {$match[0]} - {$e->getMessage()}");
                        throw $e;
                    }
                    continue;
                }
            }

            return $evaluatedexpression;
        }
        return $expression;
    }
}
