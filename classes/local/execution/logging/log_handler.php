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

namespace tool_dataflows\local\execution\logging;

/**
 * Log handler class
 *
 * For dataflow specific log handling.
 *
 * @package   tool_dataflows
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright Catalyst IT, 2023
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class log_handler {

    /** @var string */
    const BROWSER_CONSOLE = 'browser_console';

    /** @var string */
    const FILE_PER_DATAFLOW = 'file_per_dataflow';

    /** @var string */
    const FILE_PER_RUN = 'file_per_run';
}
