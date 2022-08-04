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
 * Admin setting for a list of values, one per line. Supoprts the inclusion of blank lines and comments.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class admin_setting_list extends \admin_setting_configtextarea {
    /**
     * Validate the setting data as a list of filepaths.
     *
     * @param string $data Data to be validated.
     * @return bool|mixed|string true for success or string:error on failure
     */
    public function validate($data) {
        // Strip /*..*/ comments.
        $data = preg_replace('!/\*.*?\*/!s', '', $data);

        if (empty(trim($data))) {
            return true;
        }

        $lines = explode(PHP_EOL, $data);

        $errors = [];
        foreach ($lines as $line) {

            // Strip # comments, and trim.
            $line = trim(preg_replace('/#.*$/', '', $line));

            // Ignore empty lines.
            if ($line == '') {
                continue;
            }

            $result = $this->validate_line($line);
            if ($result !== true) {
                $errors[] = $result;
            }
        }
        if (count($errors)) {
            return implode(' ', $errors);
        }
        return true;
    }

    /**
     * Validates a single line of the submitted data.
     *
     * @param string $line
     * @return true|string True if the line validates, or a string containing an explanation.
     */
    abstract protected function validate_line(string $line);
}
