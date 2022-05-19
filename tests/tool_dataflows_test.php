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

use Symfony\Component\Yaml\Yaml;

defined('MOODLE_INTERNAL') || die();

// Include lib.php functions that aren't included automatically for Moodle 37- and below.
require_once(dirname(__FILE__) . '/../lib.php');

/**
 * Units tests for tool_dataflows
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
     * Test custom step type based on the callback defined in the manager.
     *
     * @covers \tool_dataflows\dataflow\manager::get_steps_types
     */
    public function test_local_aws_custom_step() {
        // The test should only run if this condition is true.
        $localawsexampleclass = class_exists(\local_aws\step\example::class);
        if (!$localawsexampleclass) {
            $this->markTestSkipped('local_aws with custom step not located, skipping this specific test.');
            return;
        }

        $steptypes = manager::get_steps_types();
        $classnames = array_map(function ($class) {
            return get_class($class);
        }, $steptypes);
        $this->assertContains(\local_aws\step\example::class, $classnames);
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

    /**
     * Test the parsing and importing of a sample yaml file which could be used in an import.
     *
     * This test may likely expand to cover any specific cases used for dataflows.
     *
     * @covers \tool_dataflows\dataflow::import
     */
    public function test_dataflows_import() {
        $content = file_get_contents(dirname(__FILE__) . '/fixtures/sample.yml');
        $yaml = \Symfony\Component\Yaml\Yaml::parse($content);
        $dataflow = new dataflow();
        $dataflow->import($yaml);

        // Test the dataflow name.
        $this->assertEquals('Example Dataflow', $yaml['name']);
        $this->assertEquals($yaml['name'], $dataflow->name);

        // Test the dataflow imported steps.
        $steps = $dataflow->steps();
        // Pull out the steps from the dataflow and check their values.
        $read = array_shift($steps);
        $debugging = array_shift($steps);
        $write = array_shift($steps);
        // Test various yaml provided values to have confidence the import is working as expected.
        $this->assertEquals($yaml['steps']['read']['description'], $read->description);
        $this->assertEquals(Yaml::dump($yaml['steps']['write']['config']), $write->config);
        $this->assertEquals(Yaml::dump($yaml['steps']['write']['config']), $write->config);
        $this->assertEquals($yaml['steps']['debugging']['type'], $debugging->type);
        // Check dependencies (read -> debugging -> write).
        $deps = $debugging->dependencies();
        $this->assertArrayHasKey($read->id, $deps);
        $deps = $write->dependencies();
        $this->assertArrayHasKey($debugging->id, $deps);
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
