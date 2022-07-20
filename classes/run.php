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

/**
 * (Dataflow) Run persistent class
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run extends persistent {

    /** The table name. */
    const TABLE = 'tool_dataflows_runs';

    /**
     * Return the definition of the properties of this model.
     *
     * @return  array
     */
    protected static function define_properties(): array {
        return [
            'dataflowid' => ['type' => PARAM_INT],
            'name' => ['type' => PARAM_TEXT],
            'userid' => ['type' => PARAM_INT],
            'status' => ['type' => PARAM_TEXT],
            'timestarted' => ['type' => PARAM_FLOAT, 'default' => 0],
            'timepaused' => ['type' => PARAM_FLOAT, 'default' => 0],
            'timefinished' => ['type' => PARAM_FLOAT, 'default' => 0],
            'startstate' => ['type' => PARAM_TEXT, 'default' => ''],
            'currentstate' => ['type' => PARAM_TEXT, 'default' => ''],
            'endstate' => ['type' => PARAM_TEXT, 'default' => ''],
        ];
    }

    /**
     * Magic Getter
     *
     * This allows any get_$name methods to be called if they exist, before any
     * property exist checks.
     *
     * @param   string $name of the property
     * @return  mixed
     */
    public function __get($name) {
        $methodname = 'get_' . $name;
        if (method_exists($this, $methodname)) {
            return $this->$methodname();
        }
        return $this->get($name);
    }

    /**
     * Magic Setter
     *
     * This allows any class properties to be set directly instead of going
     * through set(field, value) methods
     *
     * @param   string $name property name
     * @param   string $value property value
     * @return  $this
     */
    public function __set($name, $value) {
        return $this->set($name, $value);
    }

    /**
     * Generates the 'name' of the run, and persists it in the DB.
     *
     * @param  string $status the engine's status
     * @param  string $startstate the yaml string representation of the state of the dataflow
     */
    public function initialise(string $status, string $startstate) {
        global $DB, $USER;

        // Sets the time intialised.
        $this->timestarted = microtime(true);

        // If this run is based on current settings (default behaviour until
        // re-run support is added), always increment the run counter by 1 (as a
        // whole number).
        $table = self::TABLE;
        $sql = "SELECT count(*) FROM {{$table}} where dataflowid = :dataflowid";
        $currentcount = $DB->get_field_sql($sql, ['dataflowid' => $this->dataflowid]);
        $this->name = $currentcount + 1;
        $this->startstate = $startstate;

        // Sets the user to the current user (e.g. if manually run), and
        // defaults to the site admin user (e.g. if the run is triggered via
        // something like cron).
        $userid = $USER->id ?? get_admin()->id;
        $this->userid = $userid;

        // Set the engine's status.
        $this->status = $status;

        $this->save();
    }

    /**
     * Sets the snapshot of the current state of the run
     *
     * @param  string $status the engine's status
     * @param  string $currentstate the yaml string representation of the state of the dataflow
     */
    public function snapshot(string $status, ?string $currentstate = null) {
        $this->status = $status;

        // Updates the state of the current run if provided.
        if (isset($currentstate)) {
            $this->currentstate = $currentstate;
        }

        // TODO: Determine how often this is updated to the DB (per step by
        // default), or only on finalise / shutdown, as this is likely to be
        // updated VERY often.
        $this->save();
    }

    /**
     * Stores the endstate in the run record
     *
     * @param  string $status the engine's status
     * @param  string $endstate the yaml string representation of the state of the dataflow
     */
    public function finalise(string $status, string $endstate) {
        $this->timefinished = microtime(true);
        $this->endstate = $endstate;
        $this->status = $status;
        $this->save();
    }

    /**
     * Prepares a new run, with the same inputs as the given run.
     *
     * This would increment the run's name using semver notation. For example,
     * if this was run 15, then it would become 15.1, 15.2 ... 15.11, and so
     * forth.
     */
    public function rerun() {
        // TODO: implement later. Noting that this might be the responsibility
        // of or handled in the engine.
    }
}
