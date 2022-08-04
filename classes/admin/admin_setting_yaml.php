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

use Symfony\Component\Yaml\Yaml;

/**
 * A setting textarea for YAML format values.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_yaml extends \admin_setting_configtextarea {

    /**
     * Validate the setting data as a YAML config block.
     *
     * @param string $data Data to be validated.
     * @return true|string true for success or string:error on failure
     */
    public function validate($data) {
        try {
            Yaml::parse($data);
            return true;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
}
