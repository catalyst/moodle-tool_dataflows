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
use tool_dataflows\local\step\flow_step;
use tool_dataflows\local\variables\var_dataflow;
use tool_dataflows\local\variables\var_root;

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

    /** The table name. */
    const TABLE = 'tool_dataflows';

    /** @var steps[] cache of steps connected to this dataflow */
    private $stepscache = [];

    /** @var bool Set to true when the dataflow is in the process of deleting. */
    private $isdeleting = false;

    /** @var var_root The variables tree. */
    private $varroot = null;

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'name' => ['type' => PARAM_TEXT],
            'enabled' => ['type' => PARAM_BOOL, 'default' => false],
            'concurrencyenabled' => ['type' => PARAM_BOOL, 'default' => false],
            'timelaststarted' => ['type' => PARAM_INT, 'default' => 0],
            'vars' => ['type' => PARAM_TEXT, 'default' => ''],
            'timecreated' => ['type' => PARAM_INT, 'default' => 0],
            'userid' => ['type' => PARAM_INT, 'default' => 0],
            'timemodified' => ['type' => PARAM_INT, 'default' => 0],
            'usermodified' => ['type' => PARAM_INT, 'default' => 0],
            'confighash' => ['type' => PARAM_TEXT, 'default' => ''],
        ];
    }

    /**
     * Magic getter
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

    /**
     * Magic setter
     *
     * @param      string $name of the property
     * @param      mixed $value of the property
     * @return     $this
     */
    public function __set($name, $value) {
        return $this->set($name, $value);
    }

    /**
     * Return the vars of the dataflow.
     *
     * @return  \stdClass vars object
     */
    protected function get_vars(): \stdClass {
        $yaml = Yaml::parse($this->raw_get('vars'), Yaml::PARSE_OBJECT_FOR_MAP);
        // If there is no vars, return an empty object.
        if (empty($yaml)) {
            return new \stdClass();
        }

        return $yaml;
    }

    /**
     * Gets the root node of the variables tree.
     *
     * @return var_root
     */
    public function get_variables_root(): var_root {
        if (is_null($this->varroot)) {
            $this->varroot = new var_root($this);
        }
        return $this->varroot;
    }

    /**
     * Gets the dataflow node of the variable tree.
     *
     * @return var_dataflow
     */
    public function get_variables(): var_dataflow {
        return $this->get_variables_root()->get_dataflow_variables();
    }

    /**
     * Removes the variables.
     */
    public function clear_variables() {
        $this->varroot = null;
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
                 WHERE step.dataflowid = :dataflowid
              ORDER BY sd.position ASC";

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
            $steptype = $step->steptype;
            if (isset($steptype) && $steptype->get_group() == 'triggers') {
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
     * Returns whether concurrency is possible by the step configurations.
     *
     * @return array|true True if concurrency is supported, or an array of strings giving reasons why it isn't.
     */
    public function is_concurrency_supported() {
        $steps = $this->steps;
        $reasons = [];
        foreach ($steps as $step) {
            $steptype = $step->steptype;
            if (isset($steptype)) {
                $supported = $steptype->is_concurrency_supported();
                if ($supported !== true) {
                    $reasons[$step->name] = $supported;
                }
            }
        }
        return count($reasons) ? $reasons : true;
    }

    /**
     * Returns whether concurrent running is enabled and supported.
     *
     * @return bool True is concurrency is both supported and enabled by setting, False otherwise.
     */
    public function is_concurrency_enabled(): bool {
        return ($this->concurrencyenabled && ($this->is_concurrency_supported() === true));
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
        global $DB;

        // Fetch the dot script node from each step to construct them.
        $steps = $this->steps;

        $nodes = [];
        $contentonly = true;
        $steparray = [];
        foreach ($steps as $step) {
            $steparray[$step->id] = $step;
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
            $srcstep = $steparray[$srcid];
            $deststep = $steparray[$destid];

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

            // Output connection labels, if applicable.
            $connectionstyles = [];
            $dependency = $DB->get_record('tool_dataflows_step_depends', [
                'dependson' => $srcid,
                'stepid' => $destid,
            ]);
            if (isset($dependency->position)) {
                $outputlabel = $srcstep->steptype->get_output_label($dependency->position);

                $connectionstyles = [
                    'label' => $outputlabel,
                    'fontsize'  => '10',
                    'fontname'  => 'San Serif',
                ];
            }

            // Final styles.
            $finalstyles = array_merge($baseconnectionstyles, $localstyles, $connectionstyles);

            $styles = '';
            foreach ($finalstyles as $key => $value) {
                // Escape all attributes correctly.
                $value = $this->escape_dot($value);
                $styles .= "$key =\"$value\", ";
            }
            trim($styles);

            $srcname = $srcstep->get_dotscript_name();
            $destname = $deststep->get_dotscript_name();

            $connection = implode('" -> "', [$srcname, $destname]);
            $link = "\"{$connection}\" [$styles]";
            $connections[] = $link;
        }
        $connections = implode(';' . PHP_EOL, $connections);

        $dotscript = "digraph G {
                          rankdir=LR;
                          nodesep=0.3;
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
     * @param   step $step
     * @return  $this
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
     * @param   step $step
     * @return  $this
     */
    public function remove_step(step $step) {
        $step->delete();
        return $this;
    }

    /**
     * Called when steps are created, modified or removed.
     */
    public function on_steps_save() {
        if (!$this->isdeleting) {
            // Not needed if we are going to just delete the dataflow.
            $this->set('confighash', '');
            $this->save();
        }
    }

    /**
     * Remove all the steps first.
     */
    protected function before_delete() {
        $this->isdeleting = true;
        foreach ($this->steps as $step) {
            $this->remove_step($step);
        }
    }

    /**
     * Called after deletion
     *
     * @param bool $result
     */
    protected function after_delete($result) {
        $this->isdeleting = false;
    }

    /**
     * Set the dataflow vars
     *
     * @param array|null $vars
     */
    public function set_dataflow_vars($vars) {
        $this->vars = isset($vars) ? Yaml::dump(
            (array) $vars,
            helper::YAML_DUMP_INLINE_LEVEL,
            helper::YAML_DUMP_INDENT_LEVEL,
            Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK
        ) : '';
    }

    /**
     * Imports a dataflow through a php array parsed from a yaml file
     *
     * @param array $yaml full dataflow configuration as a php array
     */
    public function import(array $yaml) {
        global $DB;

        $this->name = $yaml['name'] ?? '';
        $this->set_dataflow_vars($yaml['vars'] ?? null);
        if (isset($yaml['config'])) {
            foreach ($yaml['config'] as $key => $field) {
                $this->$key = $field;
            }
        }
        try {
            $transaction = $DB->start_delegated_transaction();
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
            $transaction->allow_commit();
        } catch (\Exception $exception) {
            $transaction->rollback($exception);
            throw $exception;
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
        $dataflowfields = ['name'];
        foreach ($dataflowfields as $field) {
            // Only set the field if it does not match the default value (e.g. if one exists).
            // Note the fallback should not match any dataflow field value.
            $default = $this->define_properties()[$field]['default'] ?? [];
            $value = $this->raw_get($field);
            if ($value !== $default) {
                $yaml[$field] = $value;
            }
        }

        $vars = $this->get_vars();
        if (!helper::obj_empty($vars)) {
            $yaml['vars'] = $vars;
        }

        // Add settings (except name) under 'config'.
        $configfields = ['enabled', 'concurrencyenabled'];
        $yaml['config'] = new \stdClass();
        foreach ($configfields as $field) {
            $yaml['config']->$field = $this->raw_get($field);
        }

        $steps = $this->steps;
        foreach ($steps as $key => $step) {
            $yaml['steps'][$key] = $step->get_export_data();
        }

        return $yaml;
    }

    /**
     * Saves the current config to the tool_dataflows_versions table
     */
    public function save_config_version() {
        global $DB;
        if (empty($this->confighash)) {
            $vars = $this->export();
            $configyaml = Yaml::dump(
                (array) $vars,
                helper::YAML_DUMP_INLINE_LEVEL,
                helper::YAML_DUMP_INDENT_LEVEL,
                Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK
            );
            $newconfighash = sha1($configyaml);

            // Inserts the stored config if it is a new config.
            if (!$DB->record_exists('tool_dataflows_versions', ['dataflowid' => $this->id, 'confighash' => $newconfighash])) {
                $DB->insert_record('tool_dataflows_versions',
                    (object) ['dataflowid' => $this->id, 'confighash' => $newconfighash, 'configyaml' => $configyaml]);
            }
            // Set the config hash in dataflows table.
            $DB->update_record('tool_dataflows', (object) ['id' => $this->id, 'confighash' => $newconfighash]);
        }
    }

    /**
     * Escape string to be passed to the dot cli
     *
     * @param   string $stringtoescape
     * @return  string
     */
    public function escape_dot(string $stringtoescape): string {
        return addslashes($stringtoescape);
    }
}
