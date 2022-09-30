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

use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use tool_dataflows\local\provider\expression_provider;

/**
 * Expression Parsing helper class
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class parser {

    /**
     * Constructor for the parser
     *
     * Sets an expression language object and registers any supported methods.
     */
    public function __construct() {
        global $CFG;
        if (function_exists('apcu_enabled') && apcu_enabled()) {
            $cache = new ApcuAdapter();
        } else {
            $cache = new FilesystemAdapter('dataflows', 0, $CFG->cachedir);
        }
        $expressionlanguage = new ExpressionLanguage($cache, [new expression_provider()]);

        $this->expressionlanguage = $expressionlanguage;
    }

    /**
     * Loops through the properties of the input yaml and returns a parsed object back
     *
     * @param   \stdClass $yaml
     * @param   array $variables
     * @return  \stdClass $yaml
     */
    public function evaluate_recursive($yaml, $variables) {
        $resolvedvalues = new \stdClass;
        $unresolvedvalues = new \stdClass;
        foreach ($yaml as $key => &$string) {
            // If the field was NULL, then it should be skipped.
            if (is_null($string)) {
                continue;
            }

            // If the field is a nested object, recurse and continue evalulating.
            if (is_object($string) || is_array($string)) {
                $string = $this->evaluate_recursive($string, $variables);
                continue;
            }

            [$hasexpression] = $this->has_expression($string);
            if (!$hasexpression) {
                $resolvedvalues->{$key} = $string;
            } else {
                $unresolvedvalues->{$key} = $string;
            }
        }
        $max = 1;
        while ($max) {
            $max--;
            foreach ($unresolvedvalues as $key => &$string) {
                [$hasexpression] = $this->has_expression($string);
                if ($hasexpression) {
                    $resolved = $this->evaluate($string, $variables);
                    if ($resolved !== $string) {
                        if (is_array($yaml)) {
                            $yaml = (object) $yaml;
                        }
                        $yaml->{$key} = $resolved;
                        $resolvedvalues->{$key} = $resolved;
                        $max++;

                        // If it doesn't have an expression, then it is resolved so no longer needed.
                        unset($unresolvedvalues->{$key});
                        if (!is_object($resolved) && !is_array($resolved)) {
                            [$hasexpression] = $this->has_expression($resolved);
                            if ($hasexpression) {
                                $unresolvedvalues->{$key} = $resolved;
                            }
                        }
                    }
                }
            }
        }

        return $yaml;
    }

    /**
     * Given an expression, and data. Evaluate and return the result.
     *
     * @param   string $expression
     * @param   array $variables containing anything relevant to the evaluation of the result
     * @return  mixed
     */
    public function evaluate(string $expression, array $variables) {
        return $this->internal_evaluator($expression, $variables, function () {
            // Do nothing.
        });
    }

    /**
     * Evalulates the expression provided and on fail will throw an exception.
     *
     * @param   string $expression
     * @param   array $variables containing anything relevant to the evaluation of the result
     * @param   callable $failcallback containing anything relevant to the evaluation of the result
     * @return  mixed
     */
    public function evaluate_or_fail(string $expression, array $variables, ?callable $failcallback = null) {
        return $this->internal_evaluator($expression, $variables, $failcallback);
    }

    /**
     * Returns whether or not the provided string contains an expression, and the matches
     *
     * @param   string $expression the raw string being checked - might contain an expression
     * @return  array $matches
     */
    public function has_expression(string $expression): array {
        preg_match_all('/(?<expressionwrapper>\${{(?<expression>.*)}})/mU', (string) $expression, $matches, PREG_SET_ORDER);
        return [!empty($matches), $matches];
    }

    /**
     * Evalulates the expressions in the string provided.
     *
     * @param   string $string
     * @param   array $variables containing anything relevant to the evaluation of the result
     * @param   callable $failcallback containing anything relevant to the evaluation of the result
     * @return  mixed
     */
    private function internal_evaluator(string $string, array $variables, ?callable $failcallback) {
        // TODO: lint expressions before storing them. https://symfony.com/blog/new-in-symfony-5-1-expressionlanguage-validator .
        $evaluatedexpression = $string;
        [$hasexpression, $matches] = $this->has_expression($string);
        if ($hasexpression) {
            foreach ($matches as $match) {
                // Try and evalulate the expression. For results that could
                // not be matched, log this since it's probably defined in
                // the wrong spot.
                try {
                    $parsethis = trim($match['expression']);
                    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
                    $result = $this->expressionlanguage->evaluate(
                        $parsethis,
                        $variables
                    );
                    error_reporting();
                    if ($result === null) {
                        if ($failcallback) {
                            $failcallback('Could not evaluate the expression ${{ ' . $parsethis . ' }}');
                        } else {
                            throw new \Exception('Could not evaluate the expression ${{ ' . $parsethis . ' }}');
                        }
                    }
                    // Set the evalulated expression to the one that passed through the expression language.
                    if (isset($result)) {
                        if (!is_object($result) && $match['expressionwrapper'] !== $string) {
                            // If the result returned from the expression is a non-scalar value, and the expression was only
                            // part of the bigger picture. Replace the occurance of it in the original provided expression with
                            // the result.
                            $evaluatedexpression = str_replace($match['expressionwrapper'], $result, $evaluatedexpression);
                        } else if (count($matches) === 1) {
                            // Number of expressions in this string is one, so we can safely return the result of the evaluation.
                            $evaluatedexpression = $result;
                        }
                    }
                } catch (\Throwable $e) {
                    if (isset($failcallback)) {
                        $failcallback("{$e->getMessage()}. for expression $string", $e);
                    } else {
                        throw $e;
                    }
                    continue;
                }
            }

            return $evaluatedexpression;
        }
        return $string;
    }

    /**
     * Returns whether or not the string content provided is valid YAML
     *
     * Note that this method assumes a flat string value (which is valid by
     * default) is invalid. Instead it must parse and represent some structured
     * content.
     *
     * @param   string $contents
     * @return  true|string True if the contents is valid yaml, or an error as a string if not
     */
    public static function validate_yaml(string $contents) {
        $invalidyaml = false;
        try {
            $yaml = Yaml::parse($contents, Yaml::PARSE_OBJECT_FOR_MAP);
            if (isset($yaml) && gettype($yaml) !== 'object') {
                $invalidyaml = true;
            }
        } catch (ParseException $e) {
            $invalidyaml = true;
        }

        if ($invalidyaml) {
            return new \lang_string('invalidyaml', 'tool_dataflows');
        }

        // Contents is valid YAML contents.
        return true;
    }
}
