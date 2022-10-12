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

namespace tool_dataflows;


/**
 * Tests the parser.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_parser_test extends \advanced_testcase {

    /**
     * Tests parser::find_variable_names().
     *
     * @dataProvider find_variable_names_provider
     * @covers \tool_dataflows\parser::find_variable_names
     * @param string $expression
     * @param array $expected
     */
    public function test_find_variable_names(string $expression, array $expected) {
        $parser = parser::get_parser();
        $varnames = $parser->find_variable_names($expression);
        $this->assertEquals($expected, $varnames);
    }

    /**
     * Provider for test_find_variable_names().
     *
     * @return array[]
     */
    public function find_variable_names_provider(): array {
        return [
            ['', []],
            ['${{a}}', ['a']],
            ['${{a.b + a.c}}', ['a.b', 'a.c']],
            ['${{a.b}} ${{a.d}} ${{b.c}}', ['a.b', 'a.d', 'b.c']],
            ['${{ isset(a[4]["somefield"]) }} ', ['a']],
            ['${{.g A.a+C * B.c. e.f + isset (C) 9 7 e g a["b"].v x.y .g}}', ['A.a', 'C', 'B.c.e.f', 'e', 'g', 'a', 'x.y.g']],
            ['${{A.a. + a[b]}}', ['A.a', 'a', 'b']],
        ];
    }

    /**
     * Test the functions available through the parser
     *
     * @param string $expression
     * @param array $variables
     * @param mixed $expected
     *
     * @covers        \tool_dataflows\parser
     * @dataProvider  parser_functions_data_provider
     */
    public function test_parser_functions(string $expression, array $variables, $expected) {
        $parser = parser::get_parser();
        $result = $parser->evaluate('${{' . $expression . '}}', $variables);
        $this->assertEquals($expected, $result);
    }

    /**
     * Ensure these expressions return the expected values (happy path)
     *
     * @return array of data
     */
    public function parser_functions_data_provider() {
        $example = [
            'a' => [
                'b' => null,
                'c' => null,
                'd' => (object) ['e' => 'f'],
            ],
        ];
        return [
            // Counts.
            ['count(a)', ['a' => [3, 2, 1]], 3],
            ['count(a["b"])', ['a' => ['b' => [1, 2]]], 2],

            // Issets.
            ['isset(a)', ['a' => [3, 2, 1]], true],
            ['isset(a[0])', ['a' => [3, 2, 1]], true],
            ['isset(a[4]["id"])', ['a' => [3, 2, 1, 2, ['id' => 1]]], true],
            ['isset(a[4]["somefield"])', ['a' => [3, 2, 1, 2, ['id' => 1]]], false],
            ['isset(a["d"].e)', $example, true],
            ['isset(a["e"])', $example, false], // Note: a["e"].id won't be resolved.
            ['isset(a["d"].f)', $example, false], // Works because "d" object exists.
            ['isset(a["something"])', ['a' => [3, 2, 1]], false], // Note: b["anything"] won't even be resolved.

            // From JSON.
            ['fromJSON(a)', ['a' => json_encode([3, 2, 1])], [3, 2, 1]],
            ['fromJSON(a)', ['a' => json_encode($example)], json_decode(json_encode($example))],
        ];
    }
}
