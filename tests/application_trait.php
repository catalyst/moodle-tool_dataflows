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
 * Helper unit test methods that are highly related to the application.
 *
 * This also includes methods that have been included to allow backwards compatibility.
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait application_trait {

    /**
     * Returns the path to the mock url to use
     *
     * @param   string $path
     * @return  string mock url to use
     */
    public function get_mock_url(string $path): string {
        return 'https://download.moodle.org/unittest'.$path;
    }

    // @codingStandardsIgnoreStart

    /**
     * Asserts the record was found in the database
     *
     * @param   string $table
     * @param   array $conditions
     * @return  $this
     */
    public function assertSeeInDatabase(string $table, array $conditions) {
        global $DB;
        $count = $DB->count_records($table, $conditions);

        $this->assertGreaterThan(0, $count, sprintf(
            'Unable to find row in database table [%s] that matched attributes [%s].', $table, json_encode($conditions)
        ));

        return $this;
    }

    /**
     * Asserts the record was not found in the database
     *
     * @param   string $table
     * @param   array $conditions
     * @return  $this
     */
    public function assertNotSeeInDatabase(string $table, array $conditions) {
        global $DB;
        $count = $DB->count_records($table, $conditions);

        $this->assertEquals(0, $count, sprintf(
            'Found unexpected records in database table [%s] that matched attributes [%s].', $table, json_encode($conditions)
        ));

        return $this;
    }

    // PHPUnit backwards compatible methods which handles the fallback to previous version calls.

    /**
     * Asserts whether the needle was found in the given haystack
     *
     * @param  string $needle
     * @param  string $haystack
     * @param  string $message
     */
    public function compatible_assertStringContainsString(string $needle, string $haystack, string $message = ''): void {
        if (method_exists($this, 'assertStringContainsString')) {
            $this->assertStringContainsString($needle, $haystack, $message);
        } else {
            $this->assertContains($needle, $haystack, $message);
        }
    }

    /**
     * Asserts that a string matches a given regular expression
     *
     * @param  string $pattern
     * @param  string $string
     * @param  string $message
     */
    public function compatible_assertMatchesRegularExpression(string $pattern, string $string, string $message = ''): void {
        if (method_exists($this, 'assertMatchesRegularExpression')) {
            $this->assertMatchesRegularExpression($pattern, $string, $message);
        } else {
            $this->assertRegExp($pattern, $string, $message);
        }
    }

    /**
     * Asserts that a string does not match a given regular expression.
     *
     * @param  string $pattern
     * @param  string $string
     * @param  string $message
     */
    public function compatible_assertDoesNotMatchRegularExpression(string $pattern, string $string, string $message = ''): void {
        if (method_exists($this, 'assertDoesNotMatchRegularExpression')) {
            $this->assertDoesNotMatchRegularExpression($pattern, $string, $message);
        } else {
            $this->assertNotRegExp($pattern, $string, $message);
        }
    }

    /**
     * Asserts that an error was expected
     */
    public function compatible_expectError(): void {
        if (method_exists($this, 'expectError')) {
            $this->expectError();
        } else {
            $this->expectException(\PHPUnit\Framework\Error\Error::class);
        }
    }
    // @codingStandardsIgnoreEnd
}
