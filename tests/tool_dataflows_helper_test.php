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
 * Unit tests for the helper class.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_helper_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Tests path_is_scheme().
     *
     * @dataProvider is_scheme_provider
     * @covers \tool_dataflows\helper::path_is_scheme
     * @param string $scheme
     * @param string $path
     * @param bool $expected
     */
    public function test_is_scheme(string $scheme, string $path, bool $expected) {
        $this->assertEquals($expected, helper::path_is_scheme($path, $scheme));
    }

    /**
     * Provider for test_is_scheme().
     *
     * @return array[]
     */
    public function is_scheme_provider(): array {
        return [
            ['file', 'one/path', false],
            ['file', '/one/path', false],
            ['sftp', 'sftp://one/path', true],
            ['sftp', 'file://one/path', false],
            ['file', 'file://one/path', true],
            ['file', 'sftp://one/path', false],
            ['sftp', 'sftp/files/download.txt', false],
            ['sftp', 'stuff/sftp://one/path', false],
        ];
    }

    /**
     * Tests the get_permitted_dirs() function.
     *
     * @dataProvider dir_provider
     * @covers \tool_dataflows\helper::get_permitted_dirs
     * @param string $data
     * @param array $expected
     */
    public function test_get_permitted_dirs(string $data, array $expected) {
        set_config('permitted_dirs', $data, 'tool_dataflows');
        $dirs = helper::get_permitted_dirs();
        $this->assertEquals($expected, $dirs);
    }

    /**
     * Provides raw permitted directories config values.
     *
     * @return array[]
     */
    public function dir_provider(): array {
        global $CFG;
        return [
            ['', []],
            [
                '/home/me/tmp ' . PHP_EOL . '  ' . PHP_EOL . '[dataroot]/tmp ',
                ['/home/me/tmp', $CFG->dataroot . '/tmp'],
            ],
            [
                '/* A comment' . PHP_EOL . ' over two lines */' . PHP_EOL . '/tmp' . PHP_EOL . '/var',
                ['/tmp', '/var'],
            ],
            [
                '# comment' . PHP_EOL . '/var/tmp   # more comments.',
                ['/var/tmp'],
            ],
            [
                '/var/[dataroot]/tmp',
                ['/var/[dataroot]/tmp'],
            ],
        ];
    }

    /**
     * Tests the bash_escape() function.
     *
     * @dataProvider bash_escape_provider
     * @covers \tool_dataflows\helper::bash_escape
     * @param string $value
     * @param string $expected
     */
    public function test_bash_escape(string $value, string $expected) {
        $this->assertEquals($expected, helper::bash_escape($value));
    }

    /**
     * Provider for test_bash_escape().
     *
     * @return \string[][]
     */
    public function bash_escape_provider(): array {
        return [
            ['', "''"],
            ['something with a space', "'something with a space'"],
            ['something with a \' quote', "'something with a '\'' quote'"],
        ];
    }

    /**
     * Tests extract_http_headers()
     *
     * @dataProvider extract_http_headers_provider
     * @covers \tool_dataflows\helper::extract_http_headers
     * @param string $content
     * @param array|bool $expected
     */
    public function test_extract_http_headers(string $content, $expected) {
        $this->assertEquals($expected, helper::extract_http_headers($content));
    }

    /**
     * Data provider for test_extract_http_headers().
     *
     * @return array[]
     */
    public function extract_http_headers_provider(): array {
        return [
            ['', []],
            ['0', false],
            ['X-one: one', ['X-one' => ' one']],
            ["X-one: one\n two", ['X-one' => ' one two']],
            ["X-one: one\n two\nX-two:two\nX-three:three", ['X-one' => ' one two', 'X-two' => 'two', 'X-three' => 'three']],
            ["X-one: one\n two\n \nX-two:two\nX-three:three", false],
            ["X-one: one\n two\nX-two\nX-three:three", false],
            ["X-@one: one\n two\nX-two:two\nX-three:three", false],
        ];
    }
}
