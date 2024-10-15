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

use Symfony\Component\Yaml\Yaml;
use tool_dataflows\local\execution\engine;
use tool_dataflows\local\step\connector_s3;

/**
 * Unit tests for connector s3.
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_connector_s3_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test path s3 matching is okay
     *
     * @covers \tool_dataflows\local\step\connector_s3
     * @dataProvider path_in_s3_data_provider
     * @param string $path
     * @param bool $isins3
     */
    public function test_path_is_in_s3_or_not(string $path, bool $isins3) {
        $steptype = new connector_s3;
        $this->assertEquals($isins3, $steptype->has_s3_path($path));
    }

    /**
     * Data provider for tests.
     *
     * @return array
     */
    public static function path_in_s3_data_provider(): array {
        return [
            ['s3://path/to/file', true],
            ['s3://path/to/', true],
            ['path/to/file', false],
            ['path', false],
        ];
    }

    /**
     * Dataflow creation helper function
     *
     * @return  array of the resulting dataflow and steps in the format [dataflow, steps]
     */
    public function create_dataflow() {
        $dataflow = new dataflow();
        $dataflow->name = 's3 copy';
        $dataflow->enabled = true;
        $dataflow->save();

        $steps = [];
        $reader = new step();
        $reader->name = 's3copy';
        $reader->type = connector_s3::class;
        $reader->config = Yaml::dump([
            'bucket' => 'bucket',
            'region' => 'region',
            'key' => 'SOMEKEY',
            'secret' => 'SOMESECRET',
            'source' => 's3://test/source.csv',
            'target' => 's3://test/target.csv',
            'sourceremote' => true,
        ]);
        $dataflow->add_step($reader);
        $steps[$reader->id] = $reader;

        return [$dataflow, $steps];
    }

    /**
     * Test path resolving is sound
     *
     * @covers \tool_dataflows\local\step\connector_s3
     * @dataProvider path_input_and_expected_data_provider
     * @param string $path
     * @param string $expected
     */
    public function test_path_is_resolved_as_expected(string $path, string $expected) {
        [$dataflow, $steps] = $this->create_dataflow();
        $isdryrun = true;

        ob_start();
        $engine = new engine($dataflow, $isdryrun);
        $engine->initialise(); // Scratch directory is currently created as part of engine init.
        $engine->set_status(engine::STATUS_FINISHED);
        $engine->finalise();
        ob_get_clean();

        // Get the step type instance (which is linked with the engine).
        $s3step = reset($steps)->steptype;

        // For paths not in s3, it is expected the path provided is prefixed with the scratch dir path.
        $isins3 = $s3step->has_s3_path($path);
        if (!$isins3) {
            $expected = "{$engine->scratchdir}/{$expected}";
        }

        $this->assertEquals($expected, $s3step->resolve_path($path, $isins3));
    }

    /**
     * Data provider for tests.
     *
     * @return array
     */
    public static function path_input_and_expected_data_provider(): array {
        return [
            ['s3://path/to/file', 'path/to/file'],
            ['s3://path/to/', 'path/to/'],
            ['s3://s3/nested/folder', 's3/nested/folder'],
            ['path/to/file', 'path/to/file'],
            ['path', 'path'],
        ];
    }

    /**
     * Tests run validation.
     *
     * @dataProvider validate_for_run_provider
     * @covers \tool_dataflows\local\step\connector_curl::validate_for_run
     * @param string $source
     * @param string $target
     * @param array|true $expected
     */
    public function test_validate_for_run(string $source, string $target, $expected) {
        $config = [
            'bucket' => 'bucket',
            'region' => 'region',
            'key' => 'SOMEKEY',
            'secret' => 'SOMESECRET',
            'source' => $source,
            'target' => $target,
            'sourceremote' => true,
        ];

        $dataflow = new dataflow();
        $dataflow->enabled = true;
        $dataflow->name = 'dataflow';
        $dataflow->save();
        $step = new step();
        $step->name = 'name';
        $step->type = 'tool_dataflows\local\step\connector_s3';
        $step->config = Yaml::dump($config);
        $dataflow->add_step($step);
        $steptype = $step->steptype;

        set_config('permitted_dirs', '', 'tool_dataflows');
        $this->assertEquals($expected, $steptype->validate_for_run());
    }

    /**
     * Provider method for test_validate_for_run().
     *
     * @return array[]
     */
    public static function validate_for_run_provider(): array {
        $s3file = 's3://test/source.csv';
        $relativefile = 'test/source.csv';
        $absolutefile = '/var/source.csv';
        $errormsg = get_string('path_invalid', 'tool_dataflows', $absolutefile, true);
        return [
            [$s3file, $s3file, true],
            [$s3file, $relativefile, true],
            [$relativefile, $s3file, true],
            [$absolutefile, $s3file, ['config_source' => $errormsg]],
            [$s3file, $absolutefile, ['config_target' => $errormsg]],
        ];
    }

    /**
     * Extra tests for run validation.
     *
     * @covers \tool_dataflows\local\step\connector_curl::validate_for_run
     */
    public function test_validate_for_run_extra() {
        $config = [
            'bucket' => 'bucket',
            'region' => 'region',
            'key' => 'SOMEKEY',
            'secret' => 'SOMESECRET',
            'source' => 's3://test/source.csv',
            'target' => '/var/source.csv',
            'sourceremote' => true,
        ];

        $dataflow = new dataflow();
        $dataflow->enabled = true;
        $dataflow->name = 'dataflow';
        $dataflow->save();
        $step = new step();
        $step->name = 'name';
        $step->type = connector_s3::class;
        $step->config = Yaml::dump($config);
        $dataflow->add_step($step);
        $steptype = $step->steptype;

        set_config('permitted_dirs', '/var', 'tool_dataflows');
        $this->assertTrue($steptype->validate_for_run());

        $config['source'] = '/var/source.csv';
        $config['target'] = 's3://test/source.csv';
        $step->config = Yaml::dump($config);
        $this->assertTrue($steptype->validate_for_run());
    }

    /**
     * Tests the default for has_side_effect().
     *
     * @covers \tool_dataflows\local\step\connector_s3::has_side_effect()
     */
    public function test_has_sideeffect_default() {
        $steptype = new connector_s3();
        $this->assertTrue($steptype->has_side_effect());
    }

    /**
     * Tests the s3 connector reports side effects correctly.
     *
     * @dataProvider has_side_effect_provider
     * @covers \tool_dataflows\local\step\connector_s3::has_side_effect
     * @param string $source
     * @param string $target
     * @param bool $expected
     */
    public function test_has_side_effect(string $source, string $target, bool $expected) {
        set_config(
            'global_vars',
            Yaml::dump([
                'abs' => '/test/target.csv',
                'rel' => 'test/target.csv',
            ]),
            'tool_dataflows'
        );

        $config = [
            'bucket' => 'bucket',
            'region' => 'region',
            'key' => 'SOMEKEY',
            'secret' => 'SOMESECRET',
            'source' => $source,
            'target' => $target,
            'sourceremote' => true,
        ];

        $dataflow = new dataflow();
        $dataflow->name = 'dataflow';
        $dataflow->enabled = true;
        $dataflow->save();

        $step = new step();
        $step->name = 'somename';
        $step->type = connector_s3::class;
        $step->config = Yaml::dump($config);
        $dataflow->add_step($step);
        $steptype = $step->steptype;

        $this->assertEquals($expected, $steptype->has_side_effect());
    }

    /**
     * Data provider for test_has_side_effect().
     *
     * @return array[]
     */
    public static function has_side_effect_provider(): array {
        return [
            ['s3://test/source.csv', 's3://test/target.csv', true],
            ['s3://test/source.csv', 'test/target.csv', false],
            ['test/source.csv', 's3://test/target.csv', true],
            ['s3://test/source.csv', '${{global.vars.abs}}', true],
            ['s3://test/source.csv', '${{global.vars.rel}}', false],
            ['test/source.csv', 's3://${{global.vars.rel}}', true],
        ];
    }
}
