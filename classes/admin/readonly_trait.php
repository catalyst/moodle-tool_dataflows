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


/**
 * Read only trait
 *
 * Checks and ensure no writes are performed when readonly is active.
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2023
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_dataflows\admin;

use tool_dataflows\manager;

trait readonly_trait {

    /**
     * Overwrite parent function to add in a readonly check.
     *
     * @param string $data
     * @return string Empty when no errors, results from parent's write_setting
     **/
    public function write_setting($data) {
        $readonly = manager::is_dataflows_readonly();
        if ($readonly) {
            return get_string('readonly_active', 'tool_dataflows');
        }

        return parent::write_setting($data);
    }
}
