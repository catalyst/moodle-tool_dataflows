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
use tool_dataflows\local\step\connector_remove_file;

/**
 * Unit test for remove file.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_remove_file_test extends \advanced_testcase {

    /** Name of the file to test with */
    const FILE_NAME = 'test.txt';

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Tests the default for has_side_effect().
     *
     * @covers \tool_dataflows\local\step\connector_remove_file::has_side_effect()
     */
    public function test_has_sideeffect_default() {
        $steptype = new local\step\connector_remove_file();
        $this->assertTrue($steptype->has_side_effect());
    }

    /**
     * Tests has_side_effect() with various filenames.
     *
     * @dataProvider has_sideeffect_provider
     * @covers \tool_dataflows\local\step\connector_remove_file::has_side_effect()
     * @param string $file
     * @param bool $expected
     */
    public function test_has_sideeffect(string $file, bool $expected) {
        set_config(
            'global_vars',
            Yaml::dump([
                'dir1' => 'everywhere',
                'dir2' => '/anywhere',
                'dir3' => 'sftp://somewhere',
            ]),
            'tool_dataflows'
        );

        $dataflow = $this->make_dataflow($file);

        $this->assertEquals($expected, $dataflow->get_steps()->hoover->has_side_effect());
    }

    /**
     * Data provider for test_has_sideeffect().
     *
     * @return array[]
     */
    public static function has_sideeffect_provider(): array {
        return [
            ['test.txt', false],
            ['/test.txt', true],
            ['${{global.vars.dir1}}/test.txt', false],
            ['${{global.vars.dir2}}/test.txt', true],
            ['${{global.vars.dir3}}/test.txt', true],
        ];
    }

    /**
     * Tests file removal.
     *
     * @covers \tool_dataflows\local\step\connector_remove_file
     */
    public function test_remove_file() {
        $basedir = sys_get_temp_dir() . DIRECTORY_SEPARATOR;
        $file = $basedir . self::FILE_NAME;

        // Create the file if it does not exist. The contents are arbitrary.
        if (!file_exists($file)) {
            file_put_contents($file, 'x');
        }

        $dataflow = $this->make_dataflow($file);

        // Run it with the file present.
        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();

        $this->assertFalse(file_exists($file));

        // Run it again with the file absent. Should still work.
        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();

        $this->assertFalse(file_exists($file));
    }

    /**
     * Creates a dataflow to test.
     *
     * @param string $file
     * @return dataflow
     */
    private function make_dataflow(string $file): dataflow {
        $dataflow = new dataflow();
        $dataflow->enabled = true;
        $dataflow->name = 'dataflow';
        $dataflow->save();

        $step = new step();
        $step->name = 'hoover';
        $step->type = connector_remove_file::class;
        $step->config = Yaml::dump(['file' => $file]);
        $dataflow->add_step($step);

        return $dataflow;
    }
}
