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

namespace tool_dataflows\admin;

use \tool_dataflows\helper;

/**
 * A custom setting for the permitted directories that a dataflow can interact with.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_permitted_directories extends admin_setting_list {

    /** File scheme string. */
    protected const FILE_SCHEME = 'file://';

    /**
     * Validates a single line of the submitted data.
     *
     * @param string $line
     * @return true|string True if the line validates, or a string containing an explanation.
     */
    protected function validate_line(string $line) {
        global $CFG;

        // Substitute dataroot placeholder, if found.
        if (substr($line, 0, strlen(helper::DATAROOT_PLACEHOLDER)) === helper::DATAROOT_PLACEHOLDER) {
            $line = $CFG->dataroot . substr($line, strlen(helper::DATAROOT_PLACEHOLDER));
        }

        // Remove file scheme, if found.
        if (substr($line, 0, strlen(self::FILE_SCHEME)) === self::FILE_SCHEME) {
            $line = substr($line, strlen(self::FILE_SCHEME));
        }

        // Test for path validity. Must be valid and absolute.
        if (!helper::is_filepath($line)) {
            return get_string('path_invalid', 'tool_dataflows', $line);
        }
        if (helper::path_is_relative($line)) {
            return get_string('path_not_absolute', 'tool_dataflows', $line);
        }
        return true;
    }
}
