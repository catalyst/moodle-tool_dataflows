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
 * Iterator interface for use within a dataflow structure.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_dataflows\local\execution\iterators;

/**
 * Iterator interface for use within a dataflow structure.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface iterator {

    /**
     * True if the iterator has no more values to provide.
     *
     * @return bool
     */
    public function is_finished(): bool;

    /**
     * True if the iterator is capable (or allowed) of supplying a value.
     *
     * @return bool
     */
    public function is_ready(): bool;

    /**
     * Terminate the iterator immediately.
     */
    public function abort();

    /**
     * Next item in the stream.
     *
     * @param   object $caller The engine step that called this method, internally used to connect outputs.
     * @return  object|bool A JSON compatible object, or false if nothing returned.
     */
    public function next(object $caller);
}
