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
    use exportable;

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
            'alias' => ['type' => PARAM_TEXT],
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
        $methodname = 'get_' . $name;
        if (method_exists($this, $methodname)) {
            return $this->$methodname();
        }
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

    public function get_variables() {
        $dataflow = new dataflow($this->dataflowid);
        return $dataflow->variables;
    }

    /**
     * Return the configuration of the dataflow, parsed such that any
     * expressions are evaluated at this point in time.
     *
     * @return     \stdClass configuration object
     */
    protected function get_config(): \stdClass {
        $yaml = Yaml::parse($this->raw_get('config'), Yaml::PARSE_OBJECT_FOR_MAP);
        if (empty($yaml)) {
            return new \stdClass();
        }

        // Prepare this as a php object (stdClass), as it makes expressions easier to write.
        $parser = new parser();
        foreach ($yaml as $key => &$string) {
            // TODO: Perhaps some keys should not be evaluated?

            // NOTE: This does not support nested expressions.
            $string = $parser->evaluate($string, $this->variables);
        }

        return $yaml;
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
        if (empty($this->alias)) {
            $snake = str_replace(' ', '_', strtolower($name));
            $this->alias = $snake;
        }
        return $this->raw_set('name', $name);
    }

    /**
     * Validates the name field
     *
     * @param      $string name provided
     * @return     true|lang_string will return a lang_string if there was an error
     */
    protected function validate_name($name) {
        if (empty($name)) {
            return new \lang_string('missingname');
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
                $step = $DB->get_record(
                    'tool_dataflows_steps',
                    ['alias' => $dependency, 'dataflowid' => $this->dataflowid],
                    'id'
                );
                if (empty($step->id)) {
                    throw new moodle_exception('stepdependencydoesnotexist', 'tool_dataflows', '', $dependson);
                }
                $dependson = $step->id;
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
        $sql = "SELECT step.id,
                       step.name,
                       step.alias
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
        // Set the alias of this step, the key will be used if the id is not provided.
        // TODO: See if there's a good reason to have an id field separate to simply using the key.
        $this->alias = $stepdata['id'];

        // Set the config as a valid YAML string.
        $this->config = isset($stepdata['config']) ? Yaml::dump($stepdata['config']) : '';

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

        // Conditionally export the configuration if it has content.
        $config = (array) $this->config;
        if (!empty($config)) {
            $yaml['config'] = $config;
        }

        // Conditionally export the dependencies (depends_on) if set.
        $dependencies = $this->dependencies();
        if (!empty($dependencies)) {
            // Since this field can be a single string or an array of aliases, it should be checked beforehand.
            $aliases = array_column($dependencies, 'alias');

            // Simplify into a single value if there is only a single entry.
            $aliases = isset($aliases[1]) ? $aliases : reset($aliases);

            $yaml['depends_on'] = $aliases;
        }

        // Resort the order of exported fields for consistency.
        $ordered = [
            'name',
            'description',
            'depends_on',
            'type',
            'config',
        ];
        $commonkeys = array_intersect_key(array_flip($ordered), $yaml);
        $yaml = array_replace($commonkeys, $yaml);

        return $yaml;
    }

    /**
     * Removes any dependencies before removing the step.
     */
    protected function before_delete() {
        global $DB;

        // Remove dependencies other steps have on this step.
        // TODO: this should check and attempt to rewire/connect steps previously linked to this step.
        $DB->delete_records('tool_dataflows_step_depends', ['stepid' => $this->id]);
        $DB->delete_records('tool_dataflows_step_depends', ['dependson' => $this->id]);
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
        $classname = $this->type;
        $typevalidation = $this->validate_type();
        if ($typevalidation !== true) {
            return $typevalidation;
        }

        $steptype = new $classname();
        $validation = $steptype->validate_config($this->config);
        if ($validation !== true) {
            // NOTE: This will only return the first error as the persistent
            // class expects the return value to be an instance of lang_string.
            return reset($validation);
        }
        return true;
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
}
