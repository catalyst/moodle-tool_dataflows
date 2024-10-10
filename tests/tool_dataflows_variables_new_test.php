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


defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/local/execution/var_object_for_testing.php');

/**
 * Tests for the new variables module.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_variables_new_test extends \advanced_testcase {

    /**
     * Test for empty (unset) values.
     *
     * @covers \tool_dataflows\local\variables\var_object_visible
     */
    public function test_empty() {
        $vars = new var_object_for_testing('one');

        $this->assertNull($vars->get('a.b.c.d'));
    }

    /**
     * Test basic getting and setting.
     *
     * @covers \tool_dataflows\local\variables\var_object
     * @covers \tool_dataflows\local\variables\var_object_visible
     * @covers \tool_dataflows\local\variables\var_object_visible::get
     * @covers \tool_dataflows\local\variables\var_object_visible::set
     * @covers \tool_dataflows\local\variables\var_value
     */
    public function test_get_and_set() {
        $vars = new var_object_for_testing('one');
        $vars->set('a.b.c', 'two');
        $this->assertEquals('two', $vars->get('a.b.c'));
        $this->assertEquals('{"a":{"b":{"c":"two"}}}', json_encode($vars->get()));

        $vars->set('a.d', 'three');
        $vars->set('a.b.c', 'four');
        $this->assertEquals('{"a":{"b":{"c":"four"},"d":"three"}}', json_encode($vars->get()));
        $this->assertEquals('{"c":"four"}', json_encode($vars->get('a.b')));
    }

    /**
     * Tests filling out of objects
     *
     * @covers \tool_dataflows\local\variables\var_object::fill_tree
     */
    public function test_object_filling() {
        $vars = new var_object_for_testing('one');
        $vars->set('a.b.c', 'two');

        $obj = new \stdClass();
        $obj->a = 1;
        $obj->b = new \stdClass();
        $obj->b->a = 3;
        $obj->c = (object) ['a' => 8, 'b' => 9, 'c' => 10];

        $expected = json_encode([
            'a' => [
                'b' => ['c' => 'two', 'a' => 3],
                'a' => 1,
                'c' => ['a' => 8, 'b' => 9, 'c' => 10],
            ],
        ]);

        $vars->set('a', $obj);
        $this->assertEquals($expected, json_encode($vars->get()));
    }

    /**
     * Test use of expressions.
     *
     * @covers \tool_dataflows\local\variables\var_object
     * @covers \tool_dataflows\local\variables\var_object_visible
     * @covers \tool_dataflows\local\variables\var_object_visible::get_raw
     * @covers \tool_dataflows\local\variables\var_value
     */
    public function test_expression() {
        $vars = new var_object_for_testing('one');
        $vars->set('a.b.c', '${{1+2}}');
        $this->assertEquals('${{1+2}}', $vars->get_raw('a.b.c'));
        $this->assertEquals('3', $vars->get('a.b.c'));
    }

    /**
     * Test expressions that reference other values.
     *
     * @covers \tool_dataflows\local\variables\var_object
     * @covers \tool_dataflows\local\variables\var_object_visible
     * @covers \tool_dataflows\local\variables\var_value
     */
    public function test_dependency() {
        $vars = new var_object_for_testing('one');
        $vars->set('a.b.c', 'six');
        $vars->set('a.b.x', 'double ${{a.b.c}}');
        $this->assertEquals('double six', $vars->get('a.b.x'));
        $vars->set('a.b.c', 'four');
        $this->assertEquals('double four', $vars->get('a.b.x'));
    }

    /**
     * Test for an expression that is set before the one it references.
     *
     * @covers \tool_dataflows\local\variables\var_object
     * @covers \tool_dataflows\local\variables\var_object_visible
     * @covers \tool_dataflows\local\variables\var_value
     */
    public function test_dependency_inverse() {
        $vars = new var_object_for_testing('one');
        $vars->set('a.b.x', 'double ${{a.b.c}}');
        $vars->set('a.b.c', 'six');
        $this->assertEquals('double six', $vars->get('a.b.x'));
        $vars->set('a.b.c', 'four');
        $this->assertEquals('double four', $vars->get('a.b.x'));
    }

    /**
     * Test expressions with multiple references.
     *
     * @covers \tool_dataflows\local\variables\var_object
     * @covers \tool_dataflows\local\variables\var_object_visible
     * @covers \tool_dataflows\local\variables\var_value
     */
    public function test_multiple_dependencies() {
        $vars = new var_object_for_testing('one');
        $vars->set('a.b', 'double ${{a.x}} and ${{a.z}}');
        $vars->set('a.x', 'six');
        $vars->set('a.z', 'four');
        $this->assertEquals('double six and four', $vars->get('a.b'));
        $vars->set('a.z', 'five');
        $this->assertEquals('double six and five', $vars->get('a.b'));
        $vars->set('a.b', 'double ${{a.x}}');
        $this->assertEquals('double six', $vars->get('a.b'));
        $vars->set('a.x', 'one');
        $this->assertEquals('double one', $vars->get('a.b'));
        $vars->set('a.z', 'two');
        $this->assertEquals('double one', $vars->get('a.b'));
        $vars->set('a.b', 'double ${{a.x}} and ${{a.z}}');
        $this->assertEquals('double one and two', $vars->get('a.b'));
        $vars->set('a.z', 'three');
        $this->assertEquals('double one and three', $vars->get('a.b'));
    }

    /**
     * Test expressions that reference expressions that themselves reference other values.
     *
     * @covers \tool_dataflows\local\variables\var_object
     * @covers \tool_dataflows\local\variables\var_object_visible
     * @covers \tool_dataflows\local\variables\var_value
     */
    public function test_deep_dependency() {
        $vars = new var_object_for_testing('one');
        $vars->set('a.b', 'double ${{a.x}}');
        $vars->set('a.x', 'triple ${{c.z}}');
        $vars->set('c.z', 'four');
        $this->assertEquals('double triple four', $vars->get('a.b'));
        $vars->set('c.z', 'six');
        $this->assertEquals('double triple six', $vars->get('a.b'));
    }

    /**
     * Tests expressions that reference values that have not been set.
     *
     * @covers \tool_dataflows\local\variables\var_object
     * @covers \tool_dataflows\local\variables\var_object_visible
     * @covers \tool_dataflows\local\variables\var_value
     */
    public function test_unset() {
        $vars = new var_object_for_testing('one');
        $vars->set('a.b', 'double ${{b.x}}');
        $this->assertEquals('double ${{b.x}}', $vars->get('a.b'));
    }

    /**
     * test expressions that reference a value relative to a subtree.
     *
     * @covers \tool_dataflows\local\variables\var_object
     * @covers \tool_dataflows\local\variables\var_object_visible
     * @covers \tool_dataflows\local\variables\var_value
     */
    public function test_localisation() {
        $op1 = 4;
        $op2 = 15;
        $sum = $op1 + $op2;

        $vars = new var_object_for_testing('one');
        $vars->set('a.b.c', $op1);

        $local = $vars->create_local_node('a.z');
        $local->set('x.b', $op2);
        $this->assertEquals($op2, $local->get('x.b')); // Relative to $local.
        $this->assertEquals($op2, $vars->get('a.z.x.b')); // Relative to the root.

        // First ref is local, second is absolute.
        $local->set('y', '${{x.b + a.b.c}}');
        $this->assertEquals($sum, $local->get('y'));
        $op2 = 12;
        $sum = $op1 + $op2;
        $local->set('x.b', $op2);
        $this->assertEquals($sum, $vars->get('a.z.y'));
    }

    /**
     * Test for correct handling of non-string types.
     *
     * @dataProvider types_provider
     * @covers \tool_dataflows\local\variables\var_object
     * @covers \tool_dataflows\local\variables\var_object_visible
     * @covers \tool_dataflows\local\variables\var_value
     * @param var_object_for_testing $vars
     * @param mixed $value
     */
    public function test_types(var_object_for_testing $vars, $value) {
        $vars->set('a', $value);
        $this->assertSame($value, $vars->get('a'));
        $this->assertSame($value, $vars->get('d'));
    }

    /**
     * Data provider for test_types().
     *
     * @return array[]
     */
    public static function types_provider(): array {
        $vars = new var_object_for_testing('one');
        $vars->set('d', '${{a}}');
        return [
            [$vars, [1, 2, 3]],
            [$vars, 5],
        ];
    }

    /**
     * Test var_object_visible::evaluate()
     *
     * @covers \tool_dataflows\local\variables\var_object_visible::evaluate
     */
    public function test_independant_evaluate() {
        $vars = new var_object_for_testing('one');
        $vars->set('a.b', 23);
        $vars->set('b.c', 12);
        $vars->set('c.d', 'tet');

        $result = $vars->evaluate('${{a.b + b.c}} ${{c.d}}');
        $this->assertEquals('35 tet', $result);
    }
}
