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
 * Tests for generic step functionality.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_step_test extends \advanced_testcase {
    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Tests alias value validation.
     *
     * @dataProvider alias_validation_provider
     * @covers \tool_dataflows\step::validate_alias
     * @param string $alias
     * @param \lang_string|true $expected
     */
    public function test_alias_validation(string $alias, $expected) {
        $step = new class extends step {
            /**
             * Function to access validate_alias()
             *
             * @param string $alias
             * @return true|\lang_string
             */
            public function access_validate_alias(string $alias) {
                return $this->validate_alias($alias);
            }
        };

        $result = $step->access_validate_alias($alias);
        $this->assertEquals($expected, $result);
    }

    /**
     * Data provider for test_alias_validation()
     *
     * @return array[]
     */
    public function alias_validation_provider(): array {
        return [
            ['simplename', true],
            ['snake_name', true],
            ['kebab-name', true],
            ['Captial-name-with-number5', true],
            ['has space', new \lang_string(
                'invalid_value_for_field',
                'tool_dataflows',
                ['value' => 'has space', 'field' => get_string('field_alias', 'tool_dataflows')]
            )],
            ['has_punctuation!', new \lang_string(
                'invalid_value_for_field',
                'tool_dataflows',
                ['value' => 'has_punctuation!', 'field' => get_string('field_alias', 'tool_dataflows')]
            )],
            ['has.dot', new \lang_string(
                'invalid_value_for_field',
                'tool_dataflows',
                ['value' => 'has.dot', 'field' => get_string('field_alias', 'tool_dataflows')]
            )],
        ];
    }

    /**
     * Tests the side effect of set_name() on alias.
     *
     * @dataProvider set_name_effects_alias_provider
     * @covers \tool_dataflows\step::set_name
     * @param string $name
     * @param string $expectedalias
     */
    public function test_set_name_effects_alias(string $name, string $expectedalias) {
        $step = new step();
        $step->set('name', $name);
        $this->assertEquals($expectedalias, $step->get('alias'));
    }

    /**
     * Provider for test_set_name_effects_alias.
     *
     * @return \string[][]
     */
    public function set_name_effects_alias_provider(): array {
        return [
            ['simplename', 'simplename'],
            ['snake_name', 'snake_name'],
            ['kebab-name', 'kebab-name'],
            ['spaced name', 'spaced_name'],
            ['Complex.name9!!', 'complex_name9_'],
            ['multiple..$% ^name', 'multiple_name'],
        ];
    }

    /**
     * Tests that setting name does not change alias if it is already set.
     *
     * @covers \tool_dataflows\step::set_name
     */
    public function test_set_name_not_effect_alias() {
        $alias = 'already_set';
        $step = new step();
        $step->set('alias', $alias);
        $step->set('name', 'some_name');
        $this->assertEquals($alias, $step->get('alias'));
    }
}
