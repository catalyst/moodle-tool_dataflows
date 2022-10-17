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

namespace tool_dataflows\local\execution;

use tool_dataflows\local\execution\iterators\iterator;
use tool_dataflows\local\execution\iterators\dataflow_iterator;
use tool_dataflows\local\execution\flow_engine_step;
use tool_dataflows\local\step\flow_step;

/**
 * A step that calls a callback for each execution.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flow_callback_step extends flow_step {

    /** @var callable The callback. */
    public static $callback;

    /**
     * Execute the step.
     *
     * @param null $inputs
     * @return mixed|null
     */
    public function execute($inputs = null) {
        $callback = $this->stepdef->dodgyvars['callback'];
        call_user_func($callback, $inputs, $this);
        return $inputs;
    }
}
