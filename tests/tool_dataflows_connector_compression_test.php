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
use tool_dataflows\local\step\connector_compression;

/**
 * Unit test for the compression connector step.
 *
 * @package   tool_dataflows
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \tool_dataflows\local\step\connector_compression
 */
class tool_dataflows_connector_compression_test extends \advanced_testcase {
    /** @var string $basedir base test directory for files **/
    private $basedir;

    /**
     * Sets up tests
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->basedir = make_unique_writable_directory(make_temp_directory('tool_dataflows'));
        set_config('permitted_dirs', $this->basedir, 'tool_dataflows');
        set_config('gzip_exec_path', '/usr/bin/gzip', 'tool_dataflows');
    }

    protected function tearDown(): void {
        $this->basedir = null;
    }

    /**
     * Creates a test dataflow
     *
     * @param string $from from file
     * @param string $tocompressed the destination that $from gets compressed to
     * @param string $todecompressed the detination that $tocompressed gets decompressed to
     * @param string $method
     */
    private function create_test_dataflow(string $from, string $tocompressed, string $todecompressed, string $method) {
        $dataflow = new dataflow();
        $dataflow->name = 'compression-connector-test';
        $dataflow->save();

        $compress = new step();
        $compress->config = Yaml::dump([
            'from' => $from,
            'to' => $tocompressed,
            'method' => $method,
            'command' => 'compress'
        ]);

        $compress->name = 'compress';
        $compress->type = connector_compression::class;

        $dataflow->add_step($compress);

        $decompress = new step();
        $decompress->config = Yaml::dump([
            'from' => $tocompressed,
            'to' => $todecompressed,
            'method' => $method,
            'command' => 'decompress'
        ]);

        $decompress->depends_on([$compress]);
        $decompress->name = 'decompress';
        $decompress->type = connector_compression::class;

        $dataflow->add_step($decompress);
        return $dataflow;
    }

    /**
     * Test compression
     */
    public function test_gzip_compression_decompression() {
        // Ensure gzip is installed, otherwise we should skip the test.
        if (!is_executable(get_config('tool_dataflows', 'gzip_exec_path'))) {
            $this->markTestSkipped('gzip is not installed');
            return;
        }

        $from = $this->basedir . '/input.txt';
        $tocompressed = $this->basedir . '/output.txt.gz';
        $todecompressed = $this->basedir . '/output_data.txt';

        $datatowrite = 'testdata';
        file_put_contents($from, $datatowrite);

        // Input should exist (we just wrote to it), but the output should NOT exist yet.
        $this->assertTrue(is_file($from));
        $this->assertFalse(is_file($tocompressed));
        $this->assertFalse(is_file($todecompressed));

        $dataflow = $this->create_test_dataflow($from, $tocompressed, $todecompressed, 'gzip');

        ob_start();
        $engine = new engine($dataflow, false, false);
        $engine->execute();
        ob_get_clean();

        // Check that the compressed file exists and also that the original file was left intact.
        $this->assertTrue(is_file($from));
        $this->assertTrue(is_file($tocompressed));
        $this->assertTrue(is_file($todecompressed));

        // Check that the originally written data ended up the same in the decompressed file.
        $decompresseddata = file_get_contents($todecompressed);
        $this->assertEquals($datatowrite, $decompresseddata);

        $vars = $engine->get_variables_root()->get('steps.compress.vars');
        $this->assertTrue($vars->success);

        $vars = $engine->get_variables_root()->get('steps.decompress.vars');
        $this->assertTrue($vars->success);
    }

    /**
     * Tests gzip validation
     */
    public function test_gzip_validation() {
        // Ensure gzip is installed, otherwise we should skip the test.
        if (!is_executable(get_config('tool_dataflows', 'gzip_exec_path'))) {
            $this->markTestSkipped('gzip is not installed');
            return;
        }

        $from = $this->basedir . '/input.txt';
        $to = $this->basedir . '/output.txt.gz';
        $todecompressed = $this->basedir . '/output_new.txt';
        $dataflow = $this->create_test_dataflow($from, $to, $todecompressed, 'gzip');
        $step = $dataflow->get_steps()->compress;

        // Initially the default gzip should be executable.
        // Which means the step is ready to run.
        $this->assertTrue($step->steptype->validate_for_run());

        // Break the gzip config.
        set_config('gzip_exec_path', '/not/a/real/path', 'tool_dataflows');
        $validation = $step->steptype->validate_for_run();

        $this->assertTrue(is_array($validation));
        $this->assertTrue(!empty($validation['config_method']));
    }
}

