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
use tool_dataflows\local\step\connector_gpg;

/**
 * Unit test for GPG.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_gpg_test extends \advanced_testcase {

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
     * @covers \tool_dataflows\local\step\connector_gpg::has_side_effect()
     */
    public function test_has_sideeffect_default() {
        $steptype = new connector_gpg();
        $this->assertTrue($steptype->has_side_effect());
    }

    /**
     * Tests has_side_effect() with various filenames.
     *
     * @dataProvider has_sideeffect_provider
     * @covers \tool_dataflows\local\step\connector_gpg::has_side_effect()
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

        $this->assertEquals($expected, $dataflow->get_steps()->gpg->has_side_effect());
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
     * Creates a dataflow to test.
     *
     * @param string $to The destination file.
     * @return dataflow
     */
    private function make_dataflow(string $to): dataflow {
        $dataflow = new dataflow();
        $dataflow->enabled = true;
        $dataflow->name = 'dataflow';
        $dataflow->save();

        $step = new step();
        $step->name = 'gpg';
        $step->type = connector_gpg::class;
        $step->config = Yaml::dump([
            'from' => 'unusedsource.txt',
            'to' => $to,
        ]);
        $dataflow->add_step($step);

        return $dataflow;
    }
}
