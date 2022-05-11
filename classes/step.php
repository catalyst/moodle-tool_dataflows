<?php
// This file is part of Moodle - http://moodle.org/  <--change
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

use core\persistent;
use moodle_exception;

/**
 * Dataflow Step persistent class
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class step extends persistent {
    const TABLE = 'tool_dataflows_steps';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'dataflowid' => ['type' => PARAM_INT],
            'type' => ['type' => PARAM_TEXT],
            'name' => ['type' => PARAM_TEXT],
            'config' => ['type' => PARAM_TEXT, 'default' => ''],
            'timecreated' => ['type' => PARAM_INT, 'default' => 0],
            'userid' => ['type' => PARAM_INT, 'default' => 0],
            'timemodified' => ['type' => PARAM_INT, 'default' => 0],
            'usermodified' => ['type' => PARAM_INT, 'default' => 0],
        ];
    }

    public function __get($name) {
        // Check if it does not exist.
        if (!isset($this->define_properties()[$name])) {
            throw new moodle_exception('Undefined property: '.static::class."::\$$name", E_USER_NOTICE);
        }

        // Return the expected field.
        return $this->get($name);
    }

    public function __set($name, $value) {
        // Check if it does not exist.
        if (!isset($this->define_properties()[$name])) {
            throw new moodle_exception('Undefined property: '.static::class."::\$$name", E_USER_NOTICE);
        }

        // Return the expected field.
        return $this->set($name, $value);
    }
}
