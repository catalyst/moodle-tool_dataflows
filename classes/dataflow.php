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
use tool_dataflows\local\execution\engine;
use tool_dataflows\local\step\flow_step;

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

    /** @var \stdClass of engine step states and timestamps */
    private $states;

    /** @var engine dataflow engine this dataflow is part of. Note: not always set. */
    private $engine;

    /** @var steps[] cache of steps connected to this dataflow */
    private $stepscache = [];

    /**
     * When initialising the persistent, ensure some internal fields have been set up.
     */
    public function __construct(...$args) {
        parent::__construct(...$args);
        $this->states = new \stdClass;
    }

    /**
     * Links the dataflow up to the relevant engine
     *
     * This is typically set when the engine is initialised, such that any
     * references made thereafter are directly connected to the engine's instance being used.
     *
     * @param  dataflow
     */
    public function set_engine(engine $engine) {
        $this->engine = $engine;
    }

    /**
     * Updates the timestamp for a particular state that is stored locally on the instance
     *
     * @param  int $state a status constant from the engine class
     * @param  float $timestamp typically from a microtime(true) call
     */
    public function set_state_timestamp(int $state, float $timestamp) {
        $label = engine::STATUS_LABELS[$state];
        $this->states->{$label} = $timestamp;
    }

    /**
     * Returns the states and their timestamps for this step
     *
     * @return  \stdClass
     */
    public function get_states() {
        return $this->states;
    }

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'name' => ['type' => PARAM_TEXT],
            'enabled' => ['type' => PARAM_BOOL, 'default' => false],
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
        $globalconfig = Yaml::parse(get_config('tool_dataflows', 'config'), Yaml::PARSE_OBJECT_FOR_MAP) ?: new \stdClass;

        // Prepare the list of variables available from each step.
        $steps = [];
        foreach ($this->steps as $key => $step) {
            $steps[$key] = $step->get_export_data();
            $steps[$key]['alias'] = $key;
            $steps[$key]['states'] = $step->states;
        }
        foreach ($steps as &$step) {
            foreach ($step as &$field) {
                if (is_array($field)) {
                    $field = (object) $field;
                }
            }
            $step = (object) $step;
            $step->config = $step->config ?? new \stdClass;
        }
        $steps = (object) $steps;
        $parser = new parser();

        $variables = ['steps' => $steps];

        $placeholder = '__PLACEHOLDER__';
        $max = 100;
        $foundexpression = true;
        $counter = [];
        while ($foundexpression && $max) {
            $max--;
            $foundexpression = false;
            foreach ($steps as &$step) {
                foreach ($step->config ?? [] as $key => &$field) {
                    if (!isset($field)) {
                        continue;
                    }

                    [$hasexpression] = $parser->has_expression($field);
                    if ($hasexpression) {
                        $foundexpression = true;
                        $fieldvalue = $field;
                        $field = $placeholder;
                        $resolved = $parser->evaluate($fieldvalue, $variables);
                        if ($resolved === $placeholder) {
                            $link = new \moodle_url('/admin/tool/dataflows/step.php', ['id' => $step->id]);
                            throw new \moodle_exception(
                                'recursiveexpressiondetected',
                                'tool_dataflows',
                                $link,
                                ['field' => $key, 'steptype' => $step->type]
                            );
                        }
                        if ($resolved !== $fieldvalue) {
                            [$hasexpression] = $parser->has_expression($resolved);
                            if ($hasexpression) {
                                $counter[$resolved] = ($counter[$resolved] ?? 0) + 1;
                            }
                        }
                        if (isset($resolved)) {
                            $field = $resolved;
                        } else {
                            $field = $fieldvalue;
                        }
                    }
                }
            }
        }

        // Prepare variable data for the dataflow key.
        $dataflow = (object) $this->get_export_data();
        unset($dataflow->steps);
        $dataflow->config = $this->get_config(false);
        $dataflow->states = $this->states;

        // Test reading a value directly.
        $variables = [
            'global' => $globalconfig,
            'env' => (object) [
                'DATAFLOW_ID' => $this->id,
                'DATAFLOW_RUN_ID' => $this->engine->run->id ?? null,
                'DATAFLOW_RUN_NAME' => $this->engine->run->name ?? null,
            ],
            'dataflow' => $dataflow,
            'steps' => $steps,
        ];
        return $variables;
    }

    /**
     * Return the configuration of the dataflow, parsed such that any
     * expressions are evaluated at this point in time.
     *
     * @param   bool $expressions whether or not to parse expressions when returning the config
     * @return  \stdClass configuration object
     */
    protected function get_config($expressions = true): \stdClass {
        $yaml = Yaml::parse($this->raw_get('config'), Yaml::PARSE_OBJECT_FOR_MAP);
        // If there is no config, return an empty object.
        if (empty($yaml)) {
            return new \stdClass();
        }

        // If no parsing is required, return the raw YAML object early.
        if (!$expressions) {
            return $yaml;
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
     * - the number of connections (input/output flows) are expected and correct.
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

        // Prepare to validate steps.
        $steps = $this->steps;

        // Prepare an array of steps by id, which makes local lookups instantaneous.
        $stepidhashmap = [];
        foreach ($steps as $step) {
            $stepidhashmap[$step->id] = $step;
        }
        $inputlist = [];
        $outputlist = [];
        foreach ($edges as $edge) {
            $step = $stepidhashmap[$edge[0]];
            $inputlist[$edge[1]][] = (object) [
                'id' => $step->id,
                'name' => $step->name,
                'alias' => $step->alias,
                'type' => $step->type,
            ];
            $step = $stepidhashmap[$edge[1]];
            $outputlist[$edge[0]][] = (object) [
                'id' => $step->id,
                'name' => $step->name,
                'alias' => $step->alias,
                'type' => $step->type,
            ];
        }

        $numtriggers = 0;

        // Check if each step is valid based on its own definition of valid (e.g. which could be based on configuration).
        foreach ($steps as $step) {
            if (isset($step->steptype) && $step->steptype->get_group() == 'triggers') {
                ++$numtriggers;
            }
            $stepvalidation = $step->validate_step();
            $prefix = \html_writer::tag('b', $step->name). ': ';
            if ($stepvalidation !== true) {
                // Additionally, prefix all step validation with something to
                // make it easier to identify from the dataflow details page.
                $prefixed = preg_filter('/^/', $prefix, $stepvalidation);
                $prefixed = array_unique($prefixed);
                $prefixed = array_combine(
                    array_map(function ($k) use ($step) {
                        return $step->id . $k;
                    }, array_keys($prefixed)),
                    $prefixed);

                $errors = array_merge($errors, $prefixed);

                // The following validation relies on a correctly defined type,
                // so skip the rest if this is missing.
                if (isset($stepvalidation['type'])) {
                    continue;
                }
            }

            $validate = $step->validate_inputs($inputlist[$step->id] ?? []);
            if ($validate !== true) {
                foreach ($validate as $error) {
                    $errors[] = $prefix . $error;
                }
            }

            $validate = $step->validate_outputs($outputlist[$step->id] ?? []);
            if ($validate !== true) {
                foreach ($validate as $error) {
                    $errors[] = $prefix . $error;
                }
            }
        }

        if ($numtriggers > 1) {
            $errors['toomanytriggers'] = get_string('toomanytriggers', 'tool_dataflows');
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Returns a list of runs
     *
     * @param   int $limit number of runs to fetch (most recent)
     * @return  array of run persistents
     */
    public function get_runs($limit): array {
        global $DB;
        $records = $DB->get_records('tool_dataflows_runs', ['dataflowid' => $this->id], 'id DESC', '*', 0, $limit);
        return array_reverse($records);
    }

    /**
     * Returns a list of step (persistent models)
     *
     * @return     \stdClass array of step models, keyed by their alias, ordered by their possible execution order
     */
    public function get_steps(): \stdClass {
        $stepsbyalias = [];
        foreach ($this->step_order as $stepid) {
            $this->stepscache[$stepid] = $this->stepscache[$stepid] ?? new step($stepid);
            $this->stepscache[$stepid]->set_dataflow($this);
            $steppersistent = $this->stepscache[$stepid];
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
        global $DB;

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

        // Insert any nodes that do not have any links to the start of the list.
        $attachednodes = array_keys($departure);

        // Fetch any step ids that weren't listed as part of an edge.
        $stepstoexclude = !empty($attachednodes) ? $attachednodes : [0]; // Assumption steps will NOT have an id of zero.
        list($stepidin, $inparams) = $DB->get_in_or_equal($stepstoexclude, SQL_PARAMS_NAMED);
        $params = array_merge(['dataflowid' => $this->id], $inparams);
        $sql = "SELECT id
                  FROM {tool_dataflows_steps}
                 WHERE dataflowid = :dataflowid
                   AND NOT id $stepidin";
        $detached = $DB->get_fieldset_sql($sql, $params);

        // Ensure detached nodes are listed first, then everything else in the order expected.
        $ordered = array_merge($detached, $attachednodes);
        return $ordered;
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
     * Returns a dotscript of the dataflow
     *
     * @return     string dotscript
     */
    public function get_dotscript(): string {
        // Fetch the dot script node from each step to construct them.
        $steps = $this->steps;
        $nodes = [];
        $contentonly = true;
        foreach ($steps as $step) {
            $nodes[] = $step->get_dotscript($contentonly);
        }
        $nodes = implode(';' . PHP_EOL, $nodes);

        // Loop through the edges and apply the appropriate styles for each connection.
        $connections = [];
        $edges = $this->edges;
        $baseconnectionstyles = [
            'color' => '#333333',
            'arrowsize' => '0.7',
            'penwidth' => 2,
        ];
        foreach ($edges as $edge) {
            [$srcid, $destid] = $edge;
            $srcstep = new step($srcid);
            $deststep = new step($destid);

            $localstyles = [];
            $typesvalid = class_exists($srcstep->type) && class_exists($deststep->type);
            if (!$typesvalid) {
                // If any dependency's type does NOT properly exist, draw a red
                // connection line, probably with an X between the connection if
                // possible.
                $localstyles['arrowhead'] = 'none';
                $localstyles['color'] = 'red';
            } else {
                $srcsteptype = new $srcstep->type();
                $deststeptype = new $deststep->type();
                if ($srcsteptype instanceof flow_step && $deststeptype instanceof flow_step) {
                    // If this is a flow to flow, show a dashed/dotted arrow indicating trickling data.
                    $localstyles['style'] = 'dashed';
                    $localstyles['color'] = '#008196';
                }
            }
            $finalstyles = array_merge($baseconnectionstyles, $localstyles);

            $styles = '';
            foreach ($finalstyles as $key => $value) {
                // TODO escape all attributes correctly.
                $styles .= "$key =\"$value\", ";
            }
            trim($styles);

            $connection = implode('" -> "', [$srcstep->name, $deststep->name]);
            $link = "\"{$connection}\" [$styles]";
            $connections[] = $link;
        }
        $connections = implode(';' . PHP_EOL, $connections);

        $dotscript = "digraph G {
                          rankdir=LR;
                          bgcolor=\"transparent\";
                          node [shape=record, height=.1];
                          {$connections}
                          {$nodes}
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
        $step->set_dataflow($this);
        $step->upsert();
        $this->stepscache[$step->id] = $step;
        return $this;
    }

    /**
     * Removes a step from the flow
     *
     * @param $step
     * @return $this
     */
    public function remove_step(step $step) {
        $step->delete();
        return $this;
    }

    /**
     * Remove all the steps first.
     */
    protected function before_delete() {
        foreach ($this->steps as $step) {
            $this->remove_step($step);
        }
    }

    /**
     * Imports a dataflow through a php array parsed from a yaml file
     *
     * @param      array $yaml full dataflow configuration as a php array
     */
    public function import(array $yaml) {
        $this->name = $yaml['name'] ?? '';
        $this->config = isset($yaml['config']) ? Yaml::dump($yaml['config'], 2, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK) : '';
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
     * Exports data for a dataflow
     *
     * @return  array $yaml data for the export
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

    /**
     * Updates the value stored in the dataflow's config
     *
     * @param  string name or path to name of field e.g. 'some.nested.fieldname'
     * @param  mixed value
     */
    public function set_var($name, $value) {
        // Grabs the current config.
        $config = $this->config;

        // Updates the field in question.
        $config->{$name} = $value;

        // Updates the stored config.
        $this->config = Yaml::dump((array) $config, 2, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }
}
