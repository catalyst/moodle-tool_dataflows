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

use tool_dataflows\local\execution\variables_step;
use tool_dataflows\local\step\base_step;
use core\persistent;
use moodle_exception;
use Symfony\Component\Yaml\Yaml;
use tool_dataflows\local\execution\engine;
use tool_dataflows\local\service\secret_service;

/**
 * Dataflow Step persistent class
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class step extends persistent {
    use exportable;

    /** The table name. */
    const TABLE = 'tool_dataflows_steps';

    /** Delimeter for the 'depends on' and 'position' values. */
    const DEPENDS_ON_POSITION_SPLITTER = ':';

    /** PARAM_ALPHANUMEXT regex as a constant. (Moodle does not define this constant). */
    const ALPHANUMEXT = '/^[a-zA-Z0-9_-]+$/';

    /** Characters to strip out when converting a name. Taken from PARAM_ALPHANUMEXT. */
    const NOT_ALPHANUMEXT = '/[^a-zA-Z0-9_-]+/';

    /** @var array */
    private $dependson = [];

    /** @var array array for lazy loading step dependents */
    private $dependants = null;

    /** @var array array for lazy loading step dependants */
    private $dependents = null;

    /**
     * When initialising the persistent, ensure some internal fields have been set up.
     *
     * @param mixed ...$args
     */
    public function __construct(...$args) {
        parent::__construct(...$args);
    }

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'dataflowid' => ['type' => PARAM_INT],
            'alias' => ['type' => PARAM_TEXT],
            'description' => ['type' => PARAM_TEXT, 'default' => ''],
            'type' => ['type' => PARAM_TEXT],
            'name' => ['type' => PARAM_TEXT],
            'config' => ['type' => PARAM_TEXT, 'default' => ''],
            'vars' => ['type' => PARAM_TEXT, 'default' => ''],
            'timecreated' => ['type' => PARAM_INT, 'default' => 0],
            'userid' => ['type' => PARAM_INT, 'default' => 0],
            'timemodified' => ['type' => PARAM_INT, 'default' => 0],
            'usermodified' => ['type' => PARAM_INT, 'default' => 0],
        ];
    }

    /**
     * Get the variables for this step.
     *
     * @return variables_step
     */
    public function get_variables(): variables_step {
        return $this->get_dataflow()->get_variables_root()->get_step_variables($this->alias);
    }

    /**
     * Magic getter - which allows the user to get values directly instead of via ->get('name')
     *
     * @param      string $name of the property to get
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
     * Magic setter - which allows the user to set values directly instead of via ->set('name', $value)
     *
     * @param      string $name of the property to update
     * @param      mixed $value of the property
     * @return     $this
     */
    public function __set($name, $value) {
        $methodname = 'set_' . $name;
        if (method_exists($this, $methodname)) {
            return $this->$methodname($value);
        }
        return $this->set($name, $value);
    }

    /**
     * Returns the user defined variables to be placed under the step's 'vars' subtree.
     *
     * @return \stdClass
     * @throws \coding_exception
     */
    protected function get_vars(): \stdClass {
        $vars = Yaml::parse($this->raw_get('vars'), Yaml::PARSE_OBJECT_FOR_MAP);

        if (empty($vars)) {
            return new \stdClass();
        }

        return $vars;
    }

    /**
     * Validate the 'vars' field.
     *
     * @param string $vars
     * @return true|\lang_string
     */
    protected function validate_vars(string $vars) {
        return parser::validate_yaml($vars);
    }

    /**
     * Returns the referenced dataflow of this step, otherwise initialises one
     *
     * @return dataflow
     */
    public function get_dataflow(): dataflow {
        if (!isset($this->dataflow)) {
            $dataflow = dataflow::get_dataflow($this->dataflowid);
            $this->set_dataflow($dataflow);
        }
        return $this->dataflow;
    }

    /**
     * Returns the step type associated with this step
     *
     * @return base_step|null a step type derived from the base step type, or null if the type configured was invalid.
     */
    public function get_steptype() {
        // This would generally be initiliased by the engine.
        if (property_exists($this, 'steptype') && isset($this->steptype)) {
            return $this->steptype;
        }

        // Falling back, this would initialise a new step type instance.
        $classname = $this->type;
        if (!empty($classname) && class_exists($classname)) {
            return new $classname($this);
        }

        // If the type configured specified was invalid, it would return null.
        return null;
    }

    /**
     * Sets the intialised step type instance for this step (def).
     *
     * This would generally be initiliased by the engine.
     *
     * @param   base_step $steptype
     */
    public function set_steptype(base_step $steptype) {
        $this->steptype = $steptype;
    }

    /**
     * Links the step up to the relevant dataflow
     *
     * This is typically set when the engine is initialised, such that any
     * references are directly connected to the engine's instance.
     *
     * @param  dataflow $dataflow
     */
    public function set_dataflow(dataflow $dataflow) {
        $this->dataflow = $dataflow;
    }

    /**
     * Return the configuration of the dataflow, parsed such that any
     * expressions are evaluated at this point in time.
     *
     * @return  \stdClass configuration object
     */
    protected function get_config(): \stdClass {
        $rawconfig = $this->raw_get('config');
        $yaml = $rawconfig;
        if (gettype($rawconfig) === 'string') {
            $yaml = Yaml::parse($rawconfig, Yaml::PARSE_OBJECT_FOR_MAP);
        }

        if (empty($yaml)) {
            return new \stdClass();
        }

        return $yaml;
    }

    public function get_redacted_config(): \stdClass {
        $config = $this->get_config();
        $steptype = $this->get_steptype();

        // Ensure the secret service gets involved to redact any information required.
        if (isset($steptype)) {
            $secretservice = new secret_service;
            $config = $secretservice->redact_fields($config, $steptype->get_secret_fields());
        }
        return $config;
    }

    /**
     * Sets the step's name
     *
     * Also sets the alias based on the new name, if the property is unset.
     *
     * @param      string $name new name of the step
     * @return     $this
     */
    protected function set_name(string $name): step {
        if (empty($this->get('alias'))) {
            // Don't use clean_param() because it does not do any replacing.
            $snake = preg_replace(self::NOT_ALPHANUMEXT, '_', strtolower($name));
            $this->alias = $snake;
        }
        return $this->raw_set('name', $name);
    }

    /**
     * Validates the name field
     *
     * @param      string $name provided
     * @return     true|\lang_string will return a lang_string if there was an error
     */
    protected function validate_name($name) {
        if (empty($name)) {
            return new \lang_string('missingname');
        }
        return true;
    }

    /**
     * Validates the alias field
     *
     * @param string $alias
     * @return true|\lang_string
     */
    protected function validate_alias(string $alias) {
        $cleaned = clean_param($alias, PARAM_ALPHANUMEXT);
        if ($cleaned != $alias) {
            return new \lang_string(
                'invalid_value_for_field',
                'tool_dataflows',
                ['value' => $alias, 'field' => get_string('field_alias', 'tool_dataflows')]
            );
        }
        return true;
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
     * Resolve the actual dependency to an ID and if required, a position.
     *
     * @param   string $dependson
     * @return  array the matching id/alias and position
     */
    private function get_alias_or_id_components(string $dependson): array {
        // Resolve the actual dependency to an ID and if required, a position.
        // Example: "id:position", "alias:position", "alias" or "id".
        $regex = '/(?<id>([^' . self::DEPENDS_ON_POSITION_SPLITTER . '\n])+)' .
            '(' . self::DEPENDS_ON_POSITION_SPLITTER . '(?<position>\d+))?/m';
        preg_match_all($regex, $dependson, $matches, PREG_SET_ORDER, 0);
        if (empty($matches)) {
            throw new moodle_exception('stepdependencydoesnotexist', 'tool_dataflows', '', $dependson);
        }

        [$match] = $matches;
        $idoralias = $match['id'];
        $position = $match['position'] ?? null;

        return [$idoralias, $position];
    }

    /**
     * Persists the dependencies (dependson) for this step into the database.
     */
    public function update_depends_on() {
        global $DB;

        // Uses the local dependson property, and defaults to an empty list if not set.
        $dependencies = $this->dependson ?? [];

        // Update records in database.
        $dependencymap = [];
        foreach ($dependencies as $dependency) {
            // If the dependency is a string, then it is most likely referencing
            // the alias. In this case, it should query the DB and populate
            // the expected id numeric value.
            $dependson = $dependency->id ?? $dependency;
            if (gettype($dependson) === 'string' && !is_number($dependson)) {
                [$idoralias, $position] = $this->get_alias_or_id_components($dependson);

                if (!is_number($idoralias)) {
                    // Get the id of the step.
                    $step = $DB->get_record(
                        'tool_dataflows_steps',
                        ['alias' => $idoralias, 'dataflowid' => $this->dataflowid],
                        'id'
                    );
                    if (empty($step->id)) {
                        throw new moodle_exception('stepdependencydoesnotexist', 'tool_dataflows', '', $dependson);
                    }
                    $idoralias = $step->id;
                }
                $dependson = $idoralias;
            }
            $dependencymap[] = ['stepid' => $this->id, 'dependson' => $dependson, 'position' => $position ?? null];
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
        $sql = "SELECT step.id,
                       step.name,
                       step.alias,
                       step.type,
                       sd.position
                  FROM {tool_dataflows_step_depends} sd
             LEFT JOIN {tool_dataflows_steps} step ON sd.dependson = step.id
                 WHERE sd.stepid = :stepid";

        $deps = $DB->get_records_sql($sql, [
            'stepid' => $this->id,
        ]);
        return $deps;
    }

    /**
     * Returns a list of other steps that depend on this step before they can run.
     *
     * @param bool $reload
     * @return  array|null step dependencies
     */
    public function dependants($reload = false): array {
        global $DB;
        $sql = "SELECT step.id,
                       step.name,
                       step.alias,
                       step.type,
                       sd.position
                  FROM {tool_dataflows_step_depends} sd
             LEFT JOIN {tool_dataflows_steps} step ON sd.stepid = step.id
                 WHERE sd.dependson = :dependson";

        if ($this->dependants === null || $reload) {
            $deps = $DB->get_records_sql($sql, [
                'dependson' => $this->id,
            ]);
            $this->dependants = $deps;
        }
        return  $this->dependants;
    }

    /**
     * Returns a list of steps that depend on this step.
     *
     * @param bool $reload
     * @return array
     * @throws \dml_exception
     */
    public function dependents($reload = false): array {
        global $DB;
        $sql = "SELECT step.id,
                       step.name,
                       step.alias,
                       step.type
                  FROM {tool_dataflows_step_depends} sd
             LEFT JOIN {tool_dataflows_steps} step ON sd.stepid = step.id
                 WHERE sd.dependson = :stepid";

        if ($this->dependents === null || $reload) {
            $deps = $DB->get_records_sql($sql, [
                'stepid' => $this->id,
            ]);
            $this->dependents = $deps;
        }
        return  $this->dependents;
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

        $this->steptype->on_save();
        $this->get_dataflow()->on_steps_save();

        return $this;
    }

    /**
     * Handling for importing an individual step based on the step relevant to the yml file.
     *
     * See dataflow->import for how this all strings together.
     *
     * @param  array $stepdata full dataflow configuration as a php array
     */
    public function import($stepdata) {
        // Set the name of this step, the key will be used if a name is not provided.
        $this->name = $stepdata['name'] ?? $stepdata['id'];
        // Sets the type of this step, which should be a FQCN.
        $this->type = $stepdata['type'];
        // Sets the description for this step.
        $this->description = $stepdata['description'] ?? '';
        // Set the alias of this step, the key will be used if the id is not provided.
        // TODO: See if there's a good reason to have an id field separate to simply using the key.
        $this->alias = $stepdata['id'];

        // Set the config as a valid YAML string.
        $this->config = isset($stepdata['config'])
            ? Yaml::dump(
                $stepdata['config'],
                helper::YAML_DUMP_INLINE_LEVEL,
                helper::YAML_DUMP_INDENT_LEVEL,
                Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK
            )
            : '';

        // Set the config as a valid YAML string.
        $this->vars = !empty($stepdata['vars'])
            ? Yaml::dump(
                $stepdata['vars'],
                helper::YAML_DUMP_INLINE_LEVEL,
                helper::YAML_DUMP_INDENT_LEVEL,
                Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK
            )
            : '';

        // Set up the dependencies, connected to each other via their step aliases.
        if (!empty($stepdata['depends_on'])) {
            $dependson = (array) $stepdata['depends_on'];
            $this->depends_on($dependson);
        }
    }

    /**
     * Returns a structured representation of the step and its configuration - ready to be exported
     *
     * @return     array
     */
    public function get_export_data(): array {
        // Set the base exported fields.
        $fields = ['name', 'description', 'type'];
        foreach ($fields as $field) {
            // Only set the field if it does not match the default value (e.g. if one exists).
            // Note the fallback should not match any dataflow field value.
            $default = $this->define_properties()[$field]['default'] ?? [];
            $value = $this->raw_get($field);
            if ($value !== $default) {
                $yaml[$field] = $value;
            }
        }

        $vars = $this->get_vars(false);
        if (!helper::obj_empty($vars)) {
            $yaml['vars'] = $vars;
        }

        // Conditionally export the configuration if it has content.
        $config = (array) $this->get_redacted_config();
        if (!empty($config)) {
            $yaml['config'] = $config;
        }

        // Conditionally export the dependencies (depends_on) if set.
        $dependencies = $this->get_dependencies_cleaned();
        if (!is_null($dependencies)) {
            $yaml['depends_on'] = $dependencies;
        }

        // Resort the order of exported fields for consistency.
        $ordered = [
            'name',
            'description',
            'depends_on',
            'type',
            'config',
            'vars',
        ];
        $commonkeys = array_intersect_key(array_flip($ordered), $yaml);
        $yaml = array_replace($commonkeys, $yaml);

        return $yaml;
    }

    /**
     * Get dependencies adjusted for dependency positions..
     *
     * @return array|false|mixed|string|string[]|null
     */
    public function get_dependencies_cleaned() {
        $dependencies = $this->dependencies();
        if (!empty($dependencies)) {
            // Since this field can be a single string or an array of aliases, it should be checked beforehand.
            $aliases = array_map(function ($dependency) {
                if (isset($dependency->position)) {
                    return $dependency->alias . self::DEPENDS_ON_POSITION_SPLITTER . $dependency->position;
                }
                return $dependency->alias;
            }, $dependencies);

            // Simplify into a single value if there is only a single entry.
            $aliases = isset($aliases[1]) ? $aliases : reset($aliases);

            return $aliases;
        }
        return null;
    }

    /**
     * Removes any dependencies before removing the step.
     */
    protected function before_delete() {
        global $DB;

        // Attempt to rewire/connect steps previously linked to this step.
        $dependents = $this->dependents();
        $dependencies = $this->dependencies();

        if (count($dependencies) === 1 && count($dependents) === 1) {
            // 1 input/output. Get new input and output and if valid rewire flow.
            $inputstep = new step(current($dependencies)->id);
            $outputstep = new step(current($dependents)->id);

            // Validate new output against new input step.
            $outputvalid = $inputstep->validate_outputs($dependents);

            // Validate new input against new output step.
            $inputvalid = $outputstep->validate_inputs($dependencies);

            if ($inputvalid === true && $outputvalid === true) {
                // New flow valid, update depends on.
                $outputstep->depends_on($dependencies);
                $outputstep->update_depends_on();
            }
        }

        // Remove dependencies other steps have on this step.
        $DB->delete_records('tool_dataflows_step_depends', ['stepid' => $this->id]);
        $DB->delete_records('tool_dataflows_step_depends', ['dependson' => $this->id]);

        $steptype = $this->steptype;
        if (isset($steptype)) {
            $steptype->on_delete();
        }

    }

    /**
     * Called after deleting.
     *
     * @param bool $result True if the delete was successful.
     */
    protected function after_delete($result) {
        if ($result) {
            $this->get_dataflow()->on_steps_save();
        }
    }

    /**
     * Sets the validated flag to false, such that validation can take place on
     * the persistent again.
     */
    public function invalidate_step() {
        // HACK to invalidate the persistent, such that it can go through it's
        // validation again instead of assuming the store settings are still
        // valid.
        $name = $this->raw_get('name');
        $this->raw_set('name', null);
        $this->raw_set('name', $name);
    }

    /**
     * Validates this step
     *
     * @return     true|array will return true or an array of errors
     */
    public function validate_step() {
        $this->invalidate_step();
        $stepvalidation = parent::validate();
        $errors = [];
        // If step validation fails, ensure the errors are appended to $errors.
        if ($stepvalidation !== true) {
            $errors = array_merge($errors, $stepvalidation);
        }

        $steptype = $this->get_steptype();
        if ($steptype) {
            $extravalidation = $steptype->validate_for_run();
            if ($extravalidation !== true) {
                $errors = array_merge($errors, $extravalidation);
            }
        }
        return empty($errors) ? true : $errors;
    }

    /**
     * This should drill down into the config and validate the configuration
     * using the step type configured.
     *
     * It is worth noting that values might be set as expressions, so step types
     * should be cautious of this when performing their own validation.
     *
     * @return true|\lang_string true if valid otherwise a lang_string object with the first error
     */
    protected function validate_config() {
        $typevalidation = $this->validate_type();
        if ($typevalidation !== true) {
            return $typevalidation;
        }

        // Ensure configuration is in valid YAML format.
        $yaml = Yaml::parse($this->raw_get('config'), Yaml::PARSE_OBJECT_FOR_MAP);
        if (isset($yaml) && gettype($yaml) !== 'object') {
            return new \lang_string('invalidyaml', 'tool_dataflows');
        }

        $validation = $this->steptype->validate_config($yaml);
        if ($validation !== true) {
            // NOTE: This will only return the first error as the persistent
            // class expects the return value to be an instance of lang_string.
            return reset($validation);
        }
        return true;
    }

    /**
     * Validates the number of links.
     *
     * @param int $count The number of links.
     * @param string $inputoutput 'input' or 'output'
     * @param string $flowconnector 'flow' or 'connector'
     * @return array|bool true if the validataion suceeded. An array or errors otherwise.
     * @throws \coding_exception
     */
    protected function validate_link_count(int $count, string $inputoutput, string $flowconnector) {
        $fn = "get_number_of_{$inputoutput}_{$flowconnector}s";
        $steptype = $this->steptype;
        [$min, $max] = $steptype->$fn();
        if ($inputoutput === 'output') {
            $min = max($min, count($steptype->get_output_labels()));
        }

        if ($count < $min || $count > $max) {
            return [
                "invalid_count_{$inputoutput}{$flowconnector}s_{$this->id}" => get_string(
                    "stepinvalid{$inputoutput}{$flowconnector}count",
                    'tool_dataflows',
                    $count
                ) . ' ' . visualiser::get_link_expectations($steptype, $inputoutput),
            ];
        }
        return true;
    }

    /**
     * Validates links, either input or output.
     *
     * @param array $deps List of database records for dependencies or dependents, depending on $inputoutput.
     * @param string $inputoutput 'input' or 'output'.
     * @return array|bool true if the validataion suceeded. An array or errors otherwise.
     * @throws \coding_exception
     */
    protected function validate_links(array $deps, string $inputoutput) {
        $count = count($deps);
        $errors = [];

        $steptype = $this->steptype;

        if ($count != 0) {
            $dep = array_shift($deps);
            $classname = $dep->type;
            // By default, if a step type doesn't exist, then it's flows cannot
            // be validated so default to true.
            if (!class_exists($classname)) {
                return true;
            }
            $type = new $classname();
            $isflow = $type->is_flow();

            // Deps list cannot have a mixture of connector steps and flow steps.
            foreach ($deps as $dep) {
                $classname = $dep->type;
                $type = new $classname();
                if ($type->is_flow() !== $isflow) {
                    $errors["{$inputoutput}s_cannot_mix_flow_and_connector_{$this->id}"] =
                        get_string("{$inputoutput}s_cannot_mix_flow_and_connectors", 'tool_dataflows');
                    break;
                }
            }

            $result = $this->validate_link_count($count, $inputoutput, $isflow ? 'flow' : 'connector');
            if ($result !== true) {
                $errors = array_merge($errors, $result);
            }
        } else {
            $fn1 = "get_number_of_{$inputoutput}_flows";
            $fn2 = "get_number_of_{$inputoutput}_connectors";
            if ($steptype->$fn1()[0] > 0 || $steptype->$fn2()[0] > 0) {
                $errors["must_have_{$inputoutput}s_{$this->id}"] =
                    get_string("must_have_{$inputoutput}s", 'tool_dataflows') . ' ' .
                    visualiser::get_link_expectations($steptype, $inputoutput);
            }
        }
        return empty($errors) ? true : $errors;
    }

    /**
     * Validates the input links.
     *
     * @param array|null $deps The dependencies. Set to avoid a database read.
     * @return array|bool true if the validataion suceeded. An array or errors otherwise.
     * @throws \coding_exception
     */
    public function validate_inputs(?array $deps = null) {
        if (is_null($deps)) {
            $deps = $this->dependencies();
        }
        return $this->validate_links($deps, 'input');
    }

    /**
     * Validate the output links.
     *
     * @param array|null $deps The dependents. Set to avoid a database read.
     * @return array|bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function validate_outputs(?array $deps = null) {
        if (is_null($deps)) {
            $deps = $this->dependents();
        }
        return $this->validate_links($deps, 'output');
    }

    /**
     * Ensures the type (which should be a class) exists and is referenceable
     *
     * @return true|\lang_string true if valid otherwise a lang_string object with the first error
     */
    protected function validate_type() {
        $classname = $this->type;
        if (!class_exists($classname)) {
            return new \lang_string('steptypedoesnotexist', 'tool_dataflows', $classname);
        }

        return true;
    }

    /**
     * Returns whether or not the step configured, has a side effect
     *
     * A side effect if it modifies some state variable value(s) outside its
     * local environment, which is to say if it has any observable effect other
     * than its primary effect of returning a value to the invoker of the
     * operation
     *
     * @return     bool whether or not this step has a side effect
     * @link https://en.wikipedia.org/wiki/Side_effect_(computer_science)
     */
    public function has_side_effect(): bool {
        $typevalidation = $this->validate_type();
        if ($typevalidation !== true) {
            return false;
        }
        return $this->steptype->has_side_effect();
    }

    /**
     * Returns an escaped dot script fragment for the node name
     *
     * By replacing whitespace with newlines we get chunkier balloons
     * so the overll graph is less wide for comlpex long flows.
     * @return string dotscript
     */
    public function get_dotscript_name(): string {
        $name = $this->name;
        $name = $this->get_dataflow()->escape_dot($name);
        $name = str_replace(' ', '\n', $name);
        return $name;
    }

    /**
     * Returns a dotscript of the step
     *
     * NOTE: this returns the fragment by default
     *
     * @param      bool $contentonly whether or not to return only the relevant contents vs a full dotscript
     * @return     string
     */
    public function get_dotscript($contentonly = false): string {
        $localstyles = ['tooltip' => $this->description ?: $this->name];
        $stepid = $this->id;
        if (!empty($stepid)) { // If ID is zero, then there is nothing to link to.
            $localstyles['URL'] = (new \moodle_url('/admin/tool/dataflows/step.php', ['id' => $stepid]))->out();
        }

        // If the class exists, use the styles from that step type.
        // Otherwise, add styles to indicate this is an invalidly configured step.
        $classname = $this->type;
        if (class_exists($classname)) {
            // Styles specific for this step type.
            $steptype = new $classname();
            $stepstyles = $steptype->get_node_styles();
        } else {
            // Invalid step type styles.
            $stepstyles = [
                'fillcolor' => '#ca3120',
                'fontcolor' => '#ffffff',
            ];
        }

        // TODO. Have a valid/not-valid state so this does not have to be repeated.
        if (!empty($stepid)) { // Do no try to validate a step that has not yet been created.
            if ($this->validate_step() !== true || $this->validate_inputs() !== true || $this->validate_outputs() !== true) {
                $stepstyles['color'] = '#ca3120';
                $stepstyles['class'] = 'dataflow_invalid_step';
                $stepstyles['style'] = isset($stepstyles['style']) ? $stepstyles['style'] . ',bold' : 'bold';
            }
        }

        // Apply styles in this order: base, step, local.
        $basestyles = base_step::get_base_node_styles();
        $rawstyles = array_merge($basestyles, $stepstyles, $localstyles);

        $styles = [];
        foreach ($rawstyles as $key => $value) {
            // Escape all attributes correctly.
            $value = $this->get_dataflow()->escape_dot($value);
            $styles[] = "$key =\"$value\"";
        }
        $styles = implode(', ', $styles);

        $name = $this->get_dotscript_name();
        $content = "\"{$name}\" [$styles]";
        if ($contentonly) {
            return $content;
        }

        $dotscript = "digraph G {
                          rankdir=LR;
                          bgcolor=\"transparent\";
                          node [shape=record, height=.1];
                          {$content}
                      }";

        return $dotscript;
    }

    /**
     * Updates the value stored in the step's config
     *
     * @param  string $name or path to name of field e.g. 'some.nested.fieldname'
     * @param  mixed $value
     */
    public function set_var($name, $value) {
        // TODO: rename this to 'set_custom_config'. 'set_var' is too confusing.
        // Grabs the current raw config.
        $config = $this->get_config(false);

        // Updates the field in question.
        $config->{$name} = $value;

        // Updates the stored config.
        $this->config = Yaml::dump(
            (array) $config,
            helper::YAML_DUMP_INLINE_LEVEL,
            helper::YAML_DUMP_INDENT_LEVEL,
            Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK
        );
    }
}
