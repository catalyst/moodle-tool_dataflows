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
use Symfony\Component\Yaml\Yaml;

/**
 * Dataflows persistent class
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dataflow extends persistent {
    use exportable;

    const TABLE = 'tool_dataflows';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'name' => ['type' => PARAM_TEXT],
            'config' => ['type' => PARAM_TEXT, 'default' => ''],
            'timecreated' => ['type' => PARAM_INT, 'default' => 0],
            'userid' => ['type' => PARAM_INT, 'default' => 0],
            'timemodified' => ['type' => PARAM_INT, 'default' => 0],
            'usermodified' => ['type' => PARAM_INT, 'default' => 0],
        ];
    }

    /**
     * Magic Getter
     *
     * This allows any get_$name methods to be called if they exist, before any
     * property exist checks.
     *
     * @param      string $name of the property
     * @return     mixed
     */
    public function __get($name) {
        $methodname = 'get_' . $name;
        if (method_exists($this, $methodname)) {
            return $this->$methodname();
        }
        return $this->get($name);
    }

    public function __set($name, $value) {
        return $this->set($name, $value);
    }

    /**
     * Returns the variables available for this object
     *
     * @return     array of variables typically passed to the expression parser
     */
    public function get_variables(): array {
        // Test reading a value directly.
        $variables = [
            'env' => (object) [
                'DATAFLOW_ID' => $this->id,
                'DATAFLOW_RUN_NUMBER' => 0,
            ],
            'dataflow' => $this,
            'steps' => $this->steps
        ];
        return $variables;
    }

    /**
     * Return the configuration of the dataflow, parsed such that any
     * expressions are evaluated at this point in time.
     *
     * @return     \stdClass configuration object
     */
    protected function get_config(): \stdClass {
        $yaml = Yaml::parse($this->raw_get('config'), Yaml::PARSE_OBJECT_FOR_MAP);
        // If there is no config, return an empty object.
        if (empty($yaml)) {
            return new \stdClass();
        }

        // Prepare this as a php object (stdClass), as it makes expressions easier to write.
        $parser = new parser();
        foreach ($yaml as &$string) {
            // TODO: Perhaps some $key should not be evaluated?

            // NOTE: This does not support nested expressions.
            $string = $parser->evaluate($string, $this->variables);
        }

        return $yaml;
    }

    /**
     * Returns all the edges with their dependencies
     *
     * @return     array $edges steps and their dependencies (by id)
     */
    public function get_edges() {
        global $DB;

        // Check flows are in valid DAG (no circular dependencies, self referencing, etc).
        $sql = "SELECT concat(sd.dependson, '|', sd.stepid) as id,
                       sd.dependson AS src,
                       sd.stepid AS dest
                  FROM {tool_dataflows_step_depends} sd
             LEFT JOIN {tool_dataflows_steps} step ON step.id = sd.stepid
                 WHERE step.dataflowid = :dataflowid";

        // Note that this works currently because all dependencies are set for each step.
        $edges = $DB->get_records_sql($sql, ['dataflowid' => $this->id]);

        // Change this to an array of edges (without the id, keys, etc).
        $edges = array_map(function ($edge) {
            return [$edge->src, $edge->dest];
        }, $edges);

        return $edges;
    }

    /**
     * Validates the steps in the dataflow (steps, step config, etc)
     *
     * This should:
     * - check if it's in a valid DAG format
     * - the number of connections (input/output streams) are expected and correct.
     *
     * @return true|array true if valid, an array of errors otherwise.
     */
    public function validate_steps() {
        $edges = $this->edges;
        $errors = [];

        $isdag = graph::is_dag($edges);

        if ($isdag === false) {
            $errors['dataflowisnotavaliddag'] = get_string('dataflowisnotavaliddag', 'tool_dataflows');
        }

        // Check steps have correct connections going in and out based on their step type.
        $adjacencylist = graph::to_adjacency_list($edges);
        $steps = $this->steps;
        foreach ($steps as $step) {
            $steptype = $step->type;
            $steptype = new $steptype();
            // Check inputs - ensure the item's occurance across the items in the adjacency list is within range.
            $srccount = 0;
            foreach ($adjacencylist as $destinations) {
                if (in_array($step->id, $destinations)) {
                    $srccount++;
                }
            }
            [$min, $max] = $steptype->get_number_of_input_streams();
            if ($srccount < $min || $srccount > $max) {
                $errors["invalid_count_inputstreams_{$step->id}"] = get_string(
                    'stepinvalidinputstreamcount',
                    'tool_dataflows',
                    (object) [
                        'name' => $step->name,
                        'found' => $srccount,
                        'min' => $min,
                        'max' => $max,
                    ]
                );
            }

            // Check outputs - for the item in the adjacency list, ensure the count of destinations is valid.
            [$min, $max] = $steptype->get_number_of_output_streams();
            $destcount = isset($adjacencylist[$step->id]) ? count($adjacencylist[$step->id]) : 0;
            if ($destcount < $min || $destcount > $max) {
                $errors["invalid_count_inputstreams_{$step->id}"] = get_string(
                    'stepinvalidoutputstreamcount',
                    'tool_dataflows',
                    (object) [
                        'name' => $step->name,
                        'found' => $destcount,
                        'min' => $min,
                        'max' => $max,
                    ]
                );
            }
        }

        // Check if each step is valid based on its own definition of valid (e.g. which could be based on configuration).
        // TODO: implement.

        return empty($errors) ? true : $errors;
    }

    /**
     * Returns a list of step (persistent models)
     *
     * @return     \stdClass array of step models, keyed by their alias, ordered by their possible execution order
     */
    public function get_steps(): \stdClass {
        $stepsbyalias = [];
        foreach ($this->step_order as $stepid) {
            $steppersistent = new step($stepid);
            $stepsbyalias[$steppersistent->alias] = $steppersistent;
        }

        return (object) $stepsbyalias;
    }

    /**
     * Returns a list of step ids in the order they should appear and would be executed.
     *
     * @return     array $stepids
     */
    public function get_step_order() {
        $departure = [];
        $discovered = [];
        $time = 0;

        $adjacencylist = graph::to_adjacency_list($this->edges);

        // Perform a depth first search and set and apply various states.
        foreach (array_keys($adjacencylist) as $src) {
            if (!isset($discovered[$src])) {
                graph::dfs($adjacencylist, $src, $discovered, $departure, $time);
            }
        }

        // Sort arrays in descending order, according to the value.
        arsort($departure);
        return array_keys($departure);
    }

    /**
     * Validate Dataflow (steps, dataflow config, etc.)
     *
     * Unlike the default validate() method which can not be overriden, this
     * will also validate any connected steps, configuration and anything
     * additionally linked with the dataflow.
     *
     * @return     true|array true if valid, an array of errors otherwise.
     */
    public function validate_dataflow() {
        $stepvalidation = $this->validate_steps();
        $dataflowvalidation = parent::validate();
        $errors = [];
        // If step validation fails, ensure the errors are appended to $errors.
        if ($stepvalidation !== true) {
            $errors = array_merge($errors, $stepvalidation);
        }
        // If dataflow validation fails, ensure the errors are appended to $errors.
        if ($dataflowvalidation !== true) {
            $errors = array_merge($errors, $dataflowvalidation);
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Returns a dotscript of the dataflow, false if no connections are available
     *
     * @return     string|false dotscript or false if not a valid flow
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

    /**
     * Imports a dataflow through a php array parsed from a yaml file
     *
     * @param      array $yaml full dataflow configuration as a php array
     */
    public function import(array $yaml) {
        $this->name = $yaml['name'] ?? '';
        $this->config = isset($yaml['config']) ? Yaml::dump($yaml['config']) : '';
        $this->save();

        // Import any provided steps.
        if (!empty($yaml['steps'])) {
            $steps = [];
            foreach ($yaml['steps'] as $key => $stepdata) {
                // Create the step and set the fields.
                $step = new \tool_dataflows\step();
                $step->dataflowid = $this->id;
                // Set a step id if one does not already exist, and use that as an alias/reference between steps.
                $stepdata['id'] = $stepdata['id'] ?? $key;

                $step->import($stepdata);
                // Save persistent and base fields.
                $step->save();

                // Append to the list of processed steps.
                $steps[] = $step;
            }
            // Wire up any dependencies for those steps, and then resync them.
            foreach ($steps as $step) {
                $step->update_depends_on();
            }
        }
    }

    /**
     * Exports a dataflow
     *
     * @return      string $contents of the exported yaml file
     */
    public function get_export_data() {
        // Exportable fields for dataflows.
        $yaml = [];
        $dataflowfields = ['name', 'config'];
        foreach ($dataflowfields as $field) {
            // Only set the field if it does not match the default value (e.g. if one exists).
            // Note the fallback should not match any dataflow field value.
            $default = $this->define_properties()[$field]['default'] ?? [];
            $value = $this->raw_get($field);
            if ($value !== $default) {
                $yaml[$field] = $value;
            }
        }
        $steps = $this->steps;
        foreach ($steps as $key => $step) {
            $yaml['steps'][$key] = $step->get_export_data();
        }

        return $yaml;
    }
}
