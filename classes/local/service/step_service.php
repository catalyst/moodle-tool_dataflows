<?php
// This file is part of Moodle - http://moodle.org/
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

namespace tool_dataflows\local\service;

use tool_dataflows\local\execution\engine_flow_cap;
use tool_dataflows\local\execution\engine_step;
use tool_dataflows\step;

/**
 * Step Service
 *
 * Handles some domain logic for how steps should interact with each other.
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class step_service {

    /**
     * Returns a list of upstream step ids part of the same flow given an engine step
     *
     * @param   engine_step $enginestep
     * @return  array of upstream step ids
     */
    public function get_upstream_step_ids_within_flow(engine_step $enginestep): array {
        $upstreams = [];
        // Should include itself.
        $upstreams[] = $enginestep->stepdef->id;
        // Go upstream and check if there are any parent steps in the same flow.
        $current = $enginestep;
        $notareader = $current->steptype->get_group() !== 'readers';
        while ($notareader) {
            $current = current($current->upstreams);
            if ($current === false) {
                break;
            }
            $upstreams[] = $current->stepdef->id;
            $notareader = $current->steptype->get_group() !== 'readers';
        }

        return $upstreams;
    }

    /**
     * Returns whether or not the given engine step, is part of the same flow group.
     *
     * @param   engine_step $enginestep1
     * @param   engine_step $enginestep2
     * @return  bool
     */
    public function is_part_of_same_flow_group(engine_step $enginestep1, engine_step $enginestep2): bool {
        // Find the nearest reader (since all flows start here).
        $upstreams1 = $this->get_upstream_step_ids_within_flow($enginestep1);
        $upstreams2 = $this->get_upstream_step_ids_within_flow($enginestep2);

        $commonancestors = array_intersect(
            $upstreams1,
            $upstreams2
        );
        return !empty($commonancestors);
    }

    /**
     * Merges the flow caps within the same flow group such that the flow group has a shared flow cap.
     *
     * @param  engine_flow_cap $flowcap
     * @param  array $upstreams
     */
    public function consolidate_flowcaps(engine_flow_cap $flowcap, array $upstreams) {
        foreach ($upstreams as $enginestep) {
            $enginestep->downstreams['puller'] = $flowcap;
            $flowcap->upstreams[$enginestep->id] = $enginestep;
        }
    }
}
