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
 * Dataflows persistent class
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dataflow extends persistent {
    const TABLE = 'tool_dataflows';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'name' => ['type' => PARAM_TEXT],
            'timecreated' => ['type' => PARAM_INT, 'default' => 0],
            'userid' => ['type' => PARAM_INT, 'default' => 0],
            'timemodified' => ['type' => PARAM_INT, 'default' => 0],
            'usermodified' => ['type' => PARAM_INT, 'default' => 0],
        ];
    }

    public function __get($name) {
        return $this->get($name);
    }

    public function __set($name, $value) {
        return $this->set($name, $value);
    }

    /**
     * Returns a dotscript of the dataflow, false if no connections are available
     *
     * @return     string|false dotscript or false if not a valid flow
     * @author     Kevin Pham <kevinpham@catalyst-au.net>
     * @copyright  Catalyst IT, 2022
     */
    public function get_dotscript() {
        global $DB;

        // Generate DOT script based on the configured dataflow.
        // First block - Lists all the step dependencies (connections) related to this workflow.
        // Second block - Ensures steps with no dependencies on prior steps (e.g. entry steps) will be listed.
        $sql = "SELECT concat(sd.stepid, sd.dependson) as id,
                       step.name AS stepname,
                       dependsonstep.name AS dependsonstepname
                  FROM {tool_dataflows_step_depends} sd
             LEFT JOIN {tool_dataflows_steps} step ON sd.stepid = step.id
             LEFT JOIN {tool_dataflows_steps} dependsonstep ON sd.dependson = dependsonstep.id
                 WHERE step.dataflowid = :dataflowid

                 UNION ALL

                SELECT concat(step.id) as id,
                       step.name AS stepname,
                       '' AS dependsonstepname
                  FROM {tool_dataflows_steps} step
                 WHERE step.dataflowid = :dataflowid2";

        $deps = $DB->get_records_sql($sql, [
            'dataflowid' => $this->id,
            'dataflowid2' => $this->id,
        ]);
        $connections = [];
        foreach ($deps as $dep) {
            $link = [];
            $link[] = $dep->dependsonstepname;
            $link[] = $dep->stepname;
            // TODO: Ensure quoted names will appear okay.
            $link = '"' . implode('" -> "', array_filter($link)) . '"';
            $connections[] = $link;
        }
        $connections = implode(';' . PHP_EOL, $connections);
        $dotscript = "digraph G {
                          rankdir=LR;
                          node [shape = record,height=.1];
                          {$connections}
                      }";

        return $dotscript;
    }

    /**
     * Return a list of steps (raw DB records)
     *
     * @return     array
     * @author     Kevin Pham <kevinpham@catalyst-au.net>
     * @copyright  Catalyst IT, 2022
     */
    public function raw_steps(): array {
        global $DB;
        $sql = "SELECT step.*
                  FROM {tool_dataflows_steps} step
                 WHERE step.dataflowid = :dataflowid";

        $steps = $DB->get_records_sql($sql, [
            'dataflowid' => $this->id,
        ]);
        return $steps;
    }


    /**
     * Method to link another step to this dataflow
     *
     * This will save the step if it has not been created in the database yet.
     *
     * @param $step
     * @return $this
     */
    public function add_step(step $step) {
        $step->dataflowid = $this->id;
        $step->upsert();
        return $this;
    }
}
