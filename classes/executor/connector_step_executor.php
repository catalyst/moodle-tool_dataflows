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

namespace tool_dataflows\executor;

use tool_dataflows\executor\iterators\iterator;

/**
 * Execution manager for connector steps.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class connector_step_executor extends step_executor {
    public function is_flow(): bool {
        return false;
    }

    public function start() {
    }

    public function abort() {
    }

    public function on_cancel(step_executor $step) {
    }

    /**
     * Signals that the step is ready to flow.
     *
     * @param step_executor $step
     * @param iterator $iterator
     */
    public function on_proceed(step_executor $step, iterator $iterator) {
    }

    /**
     * Singals that the step has finished.
     *
     * @param step_executor $step
     */
    public function on_finished(step_executor $step) {
    }
}
