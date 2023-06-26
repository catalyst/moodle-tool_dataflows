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

use tool_dataflows\helper;

/**
 * A custom setting for the permitted directories that a dataflow can interact with.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_permitted_directories extends \admin_setting_configtextarea {
    use readonly_trait;

    /** File scheme string. */
    protected const FILE_SCHEME = 'file://';

    /**
     * Validate the setting data as a list of filepaths.
     *
     * @param string $data Data to be validated.
     * @return bool|mixed|string true for success or string:error on failure
     */
    public function validate($data) {
        global $CFG;

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
                $errors[] = get_string('path_invalid', 'tool_dataflows', $line);
            } else if (helper::path_is_relative($line)) {
                $errors[] = get_string('path_not_absolute', 'tool_dataflows', $line);
            }
        }
        if (count($errors)) {
            return implode(' ', $errors);
        }
        return true;
    }
}
