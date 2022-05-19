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
use Symfony\Component\Yaml\Yaml;

/**
 * Dataflow Step persistent class
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class step extends persistent {
    const TABLE = 'tool_dataflows_steps';

    /** @var array $dependson */
    private $dependson = [];

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'dataflowid' => ['type' => PARAM_INT],
            'internalid' => ['type' => PARAM_TEXT],
            'description' => ['type' => PARAM_TEXT, 'default' => ''],
            'type' => ['type' => PARAM_TEXT],
            'name' => ['type' => PARAM_TEXT],
            'config' => ['type' => PARAM_TEXT, 'default' => ''],
            'timecreated' => ['type' => PARAM_INT, 'default' => 0],
            'userid' => ['type' => PARAM_INT, 'default' => 0],
            'timemodified' => ['type' => PARAM_INT, 'default' => 0],
            'usermodified' => ['type' => PARAM_INT, 'default' => 0],
        ];
    }

    /**
     * Magic getter - which allows the user to get values directly instead of via ->get('name')
     *
     * @param      string name of the property to get
     * @return     mixed
     */
    public function __get($name) {
        return $this->get($name);
    }

    /**
     * Magic setter - which allows the user to set values directly instead of via ->set('name', $value)
     *
     * @param      string name of the property to update
     * @param      mixed new value of the property
     * @return     $this
     */
    public function __set($name, $value) {
        return $this->set($name, $value);
    }

    protected function set_name($value) {
        if (empty($this->internalid)) {
            $slug = str_replace(' ', '-', strtolower($value));
            $this->internalid = $slug;
        }
        return $this->raw_set('name', $value);
    }

    /**
     * Sets the dependencies for this step
     *
     * @param int[]|step[] $dependencies a collection of steps or step ids
     */
    public function depends_on(array $dependencies) {
        $this->dependson = $dependencies;
        return $this;
    }

    /**
     * Persists the dependencies (dependson) for this step into the database.
     */
    public function update_depends_on() {
        global $DB;

        $dependencies = $this->dependson;
        if (empty($dependencies)) {
            return;
        }

        // Update records in database.
        $dependencymap = [];
        foreach ($dependencies as $dependency) {
            // If the dependency is a string, then it is most likely referencing
            // the internalid. In this case, it should query the DB and populate
            // the expected id numeric value.
            $dependson = $dependency->id ?? $dependency;
            if (gettype($dependson) === 'string') {
                $step = $DB->get_record(
                    'tool_dataflows_steps',
                    ['internalid' => $dependency, 'dataflowid' => $this->dataflowid],
                    'id'
                );
                $dependson = $step->id;
                // TODO: Throw an exception if dependson is null/missing, as it should exist by this stage.
            }
            $dependencymap[] = ['stepid' => $this->id, 'dependson' => $dependson];
        }
        $DB->delete_records('tool_dataflows_step_depends', ['stepid' => $this->id]);
        $DB->insert_records('tool_dataflows_step_depends', $dependencymap);
    }

    /**
     * Returns a list of steps that this step depends on before it can run.
     *
     * @return     array step dependencies
     */
    public function dependencies() {
        global $DB;
        $sql = "SELECT step.id AS id,
                       step.name AS name
                  FROM {tool_dataflows_step_depends} sd
             LEFT JOIN {tool_dataflows_steps} step ON sd.dependson = step.id
                 WHERE sd.stepid = :stepid";

        $deps = $DB->get_records_sql($sql, [
            'stepid' => $this->id,
        ]);
        return $deps;
    }

    /**
     * Updates the persistent if the record exists, otherwise creates it
     *
     * @return  $this
     */
    public function upsert() {
        // Internally this is handled by the persistent class. But we want to apply a few more things here.
        $this->save();

        // Update the local dependencies to the database.
        $this->update_depends_on();

        return $this;
    }

    /**
     * Handling for importing an individual step based on the step relevant to the yml file.
     *
     * See dataflow->import for how this all strings together.
     *
     * @param      array $yaml full dataflow configuration as a php array
     */
    public function import($stepdata) {
        // Set the name of this step, the key will be used if a name is not provided.
        $this->name = $stepdata['name'] ?? $stepdata['id'];
        // Sets the type of this step, which should be a FQCN.
        $this->type = $stepdata['type'];
        // Sets the description for this step.
        $this->description = $stepdata['description'] ?? '';
        // Set the internalid of this step, the key will be used if the id is not provided.
        // TODO: See if there's a good reason to have an id field separate to simply using the key.
        $this->internalid = $stepdata['id'];

        // Set the config as a valid YAML string.
        $this->config = Yaml::dump($stepdata['config'] ?? '');

        // Set up the dependencies, connected to each other via their internal step ids.
        if (!empty($stepdata['depends_on'])) {
            $dependson = (array) $stepdata['depends_on'];
            $this->depends_on($dependson);
        }
    }
}
