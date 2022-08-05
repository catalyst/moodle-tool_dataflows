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

use tool_dataflows\local\step\flow_cap;

/**
 * An environment for logging information about dataflow execution.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class logging_context {

    /** @var engine $engine The engine. */
    protected $engine = null;

    /** @var engine_step $enginestep The engine step. */
    protected $enginestep = null;

    /**
     * Create an instance of this class.
     *
     * @param  engine_step|engine $object
     * @throws \moodle_exception
     */
    public function __construct($object) {
        if ($object instanceof engine_step) {
            $this->enginestep = $object;
            $this->engine = $object->engine;
        } else if ($object instanceof engine) {
            $this->engine = $object;
        } else {
            throw new \moodle_exception('Unknown context object');
        }
    }

    /**
     * Handles logging
     *
     * @param   string $message
     */
    public function log($message) {
        // Do not log anything for flowcaps as they are virtual.
        if (isset($this->enginestep) && $this->enginestep->steptype instanceof flow_cap) {
            return;
        }

        $name = $this->engine->name;
        $logstr = "Engine '{$name}'";
        $strlen = min(20, strlen($name));
        $dots = str_repeat('.' , 25 - $strlen);
        if (!is_null($this->enginestep)) {
            $name = $this->enginestep->name;
            $logstr .= " step {$name}{$dots}";
        }
        $logstr .= ' ' . $message;
        mtrace($logstr);
    }
}
