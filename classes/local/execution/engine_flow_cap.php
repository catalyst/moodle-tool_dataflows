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

/**
 * Engine flow step for flow caps.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright Catalyst IT, 2022
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class engine_flow_cap extends flow_engine_step {

    /**
     * Description of what this does
     *
     * @return  int
     */
    public function go_cap(): int {
        switch ($this->proceed_status()) {
            case self::PROCEED_GO:
                try {
                    $this->set_status(engine::STATUS_FLOWING);
                } catch (\Throwable $thrown) {
                    $this->exception = $thrown;
                    $this->set_status(engine::STATUS_ABORTED);
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

    /**
     * Attempt to execute the step. If flowing, will run the iterator.
     *
     * @return int
     */
    public function go(): int {
        $status = $this->go_cap();

        try {
            if ($status === engine::STATUS_FLOWING) {
                foreach ($this->upstreams as $upstream) {
                    $iterators[] = $this->steptype->get_upstream_iterator($upstream);
                }

                // Pull down (check) and see if things can flow through. If not, pull down on the next upstream.
                $this->iterator = current($iterators);
                while (!$this->iterator->is_finished()) {
                    foreach ($iterators as $iterator) {
                        $iterator->next($this);
                    }
                }

                if ($this->iterator->is_finished()) {
                    $this->set_status(engine::STATUS_FINISHED);
                }
            }
        } catch (\Throwable $e) {
            $this->set_status(engine::STATUS_ABORTED);
            $this->engine->log($e->getMessage());
            $this->exception = $e;
        }

        return $this->status;
    }
}
