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

/**
 * Units tests for the manager class.
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * @covers \tool_dataflows\step\debugging
     */
    public function test_debugging_step(): void {
        $step = new step\debugging();
        $input = [1, 2, 3, 4, 5];
        $output = $step->execute($input);

        // No changes are expected.
        $this->assertEquals($input, $output);

        // Debugging was called with the expected format.
        $this->assertDebuggingCalled(json_encode($input));
    }

    /**
     * @covers \tool_dataflows\dataflow
     */
    public function test_creating_empty_dataflow(): void {
        $name = 'test dataflow';
        $persistent = new \tool_dataflows\dataflow();
        $persistent
            ->set('name', $name)
            ->create();

        $this->assertEquals($name, $persistent->get('name'));
        $this->assertEquals($name, $persistent->name);
        $persistent->read();
        $this->assertEquals($name, $persistent->get('name'));
        $this->assertEquals($name, $persistent->name);
        $this->assertTrue($persistent->is_valid());

        // Get the id to confirm the record is stored and will load as expected.
        $id = $persistent->get('id');

        $anotherflow = new \tool_dataflows\dataflow($id);
        $this->assertEquals($name, $anotherflow->name);
    }

    /**
     * @covers \tool_dataflows\step
     * @covers \tool_dataflows\dataflow::get_dotscript
     */
    public function test_dependent_steps_and_dot_script(): void {
        $name = 'test dataflow';
        $dataflow = new \tool_dataflows\dataflow();
        $dataflow
            ->set('name', $name)
            ->create();

        $stepone = new \tool_dataflows\step();
        $stepone->name = 'step1';
        $stepone->type = step\debugging::class;
        $dataflow->add_step($stepone);

        $steptwo = new \tool_dataflows\step();
        $steptwo->name = 'step2';
        $steptwo->type = step\debugging::class;
        $steptwo->depends_on([$stepone]);
        $dataflow->add_step($steptwo);

        $dotscript = $dataflow->get_dotscript();
        $this->assertStringContainsString($steptwo->name, $dotscript);
        $this->assertStringContainsString($stepone->name, $dotscript);
        // Ensure dependency chain exists.
        $this->assertMatchesRegularExpression("/{$stepone->name}.*->.*{$steptwo->name}/", $dotscript);
        $this->assertDoesNotMatchRegularExpression("/{$steptwo->name}.*->.*{$stepone->name}/", $dotscript);
    }
}
