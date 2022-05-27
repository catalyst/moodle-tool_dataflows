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
    // @codingStandardsIgnoreStart
    public function assertSeeInDatabase(string $table, array $conditions) {
        global $DB;
        $count = $DB->count_records($table, $conditions);

        $this->assertGreaterThan(0, $count, sprintf(
            'Unable to find row in database table [%s] that matched attributes [%s].', $table, json_encode($conditions)
        ));

        return $this;
    }

    public function assertNotSeeInDatabase(string $table, array $conditions) {
        global $DB;
        $count = $DB->count_records($table, $conditions);

        $this->assertEquals(0, $count, sprintf(
            'Found unexpected records in database table [%s] that matched attributes [%s].', $table, json_encode($conditions)
        ));

        return $this;
    }

    // PHPUnit backwards compatible methods which handles the fallback to previous version calls.

    public function compatible_assertStringContainsString(...$args): void {
        if (method_exists($this, 'assertStringContainsString')) {
            $this->assertStringContainsString(...$args);
        } else {
            $this->assertContains(...$args);
        }
    }

    public function compatible_assertMatchesRegularExpression(...$args): void {
        if (method_exists($this, 'assertMatchesRegularExpression')) {
            $this->assertMatchesRegularExpression(...$args);
        } else {
            $this->assertRegExp(...$args);
        }
    }

    public function compatible_assertDoesNotMatchRegularExpression(...$args): void {
        if (method_exists($this, 'assertDoesNotMatchRegularExpression')) {
            $this->assertDoesNotMatchRegularExpression(...$args);
        } else {
            $this->assertNotRegExp(...$args);
        }
    }
    // @codingStandardsIgnoreEnd
}
