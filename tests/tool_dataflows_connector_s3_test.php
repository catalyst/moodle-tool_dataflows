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
    public function path_in_s3_data_provider(): array {
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
    public function path_input_and_expected_data_provider(): array {
        return [
            ['s3://path/to/file', 'path/to/file'],
            ['s3://path/to/', 'path/to/'],
            ['s3://s3/nested/folder', 's3/nested/folder'],
            ['path/to/file', 'path/to/file'],
            ['path', 'path'],
        ];
    }
}
