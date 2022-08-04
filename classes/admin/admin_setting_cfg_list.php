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

/**
 * A list of $CFG settings.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_cfg_list extends admin_setting_list {
    /**
     * Validates a single line of the submitted data.
     *
     * @param string $line
     * @return true|string True if the line validates, or a string containing an explanation.
     */
    protected function validate_line(string $line) {
        global $CFG;

        if (!isset($CFG->{$line})) {
            return get_string('cfg_value_undefined', 'tool_dataflows', $line);
        }
        return true;
    }
}
