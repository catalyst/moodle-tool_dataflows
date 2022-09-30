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

use tool_dataflows\parser;

/**
 * Manages the execution of a flow step.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flow_engine_step extends engine_step {

    /** @var iterator The iterator for this step. */
    protected $iterator = null;

    /**
     * True for flow steps, false for connector steps.
     *
     * @return bool
     */
    public function is_flow(): bool {
        return true;
    }

    /**
     * Aborts the step. Do not call this directly. Always call engine::abort() to abort a dataflow.
     */
    public function abort() {
        if (!is_null($this->iterator)) {
            $this->iterator->stop();
        }
        $this->set_status(engine::STATUS_ABORTED);
    }

    /**
     * Attempt to execute the step.
     *
     * @return int
     */
    public function go(): int {
        switch ($this->proceed_status()) {
            case self::PROCEED_GO:
                try {
                    $this->iterator = $this->steptype->get_iterator();
                    $this->set_status(engine::STATUS_FLOWING);
                } catch (\Throwable $thrown) {
                    $this->exception = $thrown;
                    $this->engine->abort($thrown);
                }
                break;
            case self::PROCEED_STOP:
                $this->set_status(engine::STATUS_CANCELLED);
                break;
            case self::PROCEED_WAIT:
                $this->set_status(engine::STATUS_WAITING);
                break;
        }
        return $this->status;
    }
}
