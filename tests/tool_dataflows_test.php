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

        $stepthree = new \tool_dataflows\step();
        $stepthree->name = 'step3';
        $stepthree->type = step\debugging::class;
        $stepthree->depends_on([$steptwo]);
        $dataflow->add_step($stepthree);

        $dotscript = $dataflow->get_dotscript();
        // PHPUnit backwards compatibility check.
        $this->compatible_assertStringContainsString($steptwo->name, $dotscript);
        $this->compatible_assertStringContainsString($stepone->name, $dotscript);

        // Ensure dependency chain exists.
        $this->compatible_assertMatchesRegularExpression("/{$stepone->name}.*->.*{$steptwo->name}/", $dotscript);
        $this->compatible_assertDoesNotMatchRegularExpression("/{$steptwo->name}.*->.*{$stepone->name}/", $dotscript);

        // Confirm that dataflow is valid.
        $validation = $dataflow->validate_dataflow();
        $this->assertTrue($validation);
    }

    /**
     * @covers \tool_dataflows\step
     * @covers \tool_dataflows\dataflow::validate_dataflow
     */
    public function test_dataflow_validation(): void {
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

        $stepthree = new \tool_dataflows\step();
        $stepthree->name = 'step3';
        $stepthree->type = step\debugging::class;
        $stepthree->depends_on([$steptwo]);
        $dataflow->add_step($stepthree);

        // Confirm that dataflow is valid.
        $validation = $dataflow->validate_dataflow();
        $this->assertTrue($validation);

        // Confirm the dataflow is now invalid (three->one->two->three - circular dependency).
        $stepone->depends_on([$stepthree])->upsert();
        $validation = $dataflow->validate_dataflow();
        $this->assertNotTrue($validation);
    }

    /**
     * @covers \tool_dataflows\graph::is_dag
     */
    public function test_dag_check() {
        $edges = [
            [0, 1], [0, 3],
            [1, 2], [1, 3],
            [3, 2], [3, 4],
            [5, 6],
            [6, 3],
        ];
        $isdag = \tool_dataflows\graph::is_dag($edges);
        $this->assertTrue($isdag);

        // Introduce a back-edge, which will make the graph an invalid DAG.
        $edges[] = [3, 0];
        $isdag = \tool_dataflows\graph::is_dag($edges);
        $this->assertNotTrue($isdag);

        // Text based edges for certainty it works also with named edges.
        $edges = [
            ['a', 'b'], ['a', 'd'],
            ['b', 'c'], ['b', 'd'],
            ['d', 'c'], ['d', 'e'],
            ['f', 'g'],
            ['g', 'd'],
        ];
        $isdag = \tool_dataflows\graph::is_dag($edges);
        $this->assertTrue($isdag);

        // Test the ok (but weird) case if there are disconnected graphs inside a flow.
        $edges = [
            ['a', 'b'], ['a', 'd'],
            ['f', 'g'],
        ];
        $isdag = \tool_dataflows\graph::is_dag($edges);
        $this->assertTrue($isdag);
    }

    // PHPUnit backwards compatible methods which handles the fallback to previous version calls.

    public function compatible_assertStringContainsString(...$args): void { // phpcs:ignore
        if (method_exists($this, 'assertStringContainsString')) {
            $this->assertStringContainsString(...$args);
        } else {
            $this->assertContains(...$args);
        }
    }

    public function compatible_assertMatchesRegularExpression(...$args): void { // phpcs:ignore
        if (method_exists($this, 'assertMatchesRegularExpression')) {
            $this->assertMatchesRegularExpression(...$args);
        } else {
            $this->assertRegExp(...$args);
        }
    }

    public function compatible_assertDoesNotMatchRegularExpression(...$args): void { // phpcs:ignore
        if (method_exists($this, 'assertDoesNotMatchRegularExpression')) {
            $this->assertDoesNotMatchRegularExpression(...$args);
        } else {
            $this->assertNotRegExp(...$args);
        }
    }
}
