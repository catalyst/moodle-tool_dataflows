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

namespace tool_dataflows\local\step;

use Symfony\Component\Yaml\Yaml;
use tool_dataflows\local\execution\engine;
use tool_dataflows\local\execution\engine_step;
use tool_dataflows\step;

/**
 * Base class for steps
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_step {

    /** @var engine_step */
    protected $enginestep;

    /**
     * This is autopopulated by the dataflows manager.
     *
     * @var string $component - The component / plugin this step belongs to.
     */
    protected $component = 'tool_dataflows';

    /* Input and Output flow and connectors, default all things to zero */

    /** @var int[] number of input flows (min, max) */
    protected $inputflows = [0, 0];

    /** @var int[] number of output flows (min, max) */
    protected $outputflows = [0, 0];

    /** @var int[] number of input connectors (min, max) */
    protected $inputconnectors = [0, 0];

    /** @var int[] number of output connectors (min, max) */
    protected $outputconnectors = [0, 0];

    /** @var bool whether or not this step type (potentially) contains a side effect or not */
    protected $hassideeffect = false;

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
        return $this->hassideeffect;
    }

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    protected static function form_define_fields(): array {
        return [
            'config' => ['type' => PARAM_TEXT, 'default' => ''],
        ];
    }

    /**
     * Get the frankenstyle component name
     *
     * @return string
     */
    public function get_component(): string {
        return $this->component;
    }

    /**
     * Get the frankenstyle component name
     *
     * @param string $component name
     */
    public function set_component(string $component) {
        $this->component = $component;
    }

    /**
     * Does this type define a flow step?
     *
     * @return bool
     */
    public function is_flow(): bool {
        return false;
    }

    /**
     * Get the step's id
     *
     * This defaults to the base name of the class which is ok in the most
     * cases but if you have a step which can have multiple instances then
     * you should override this to be unique.
     *
     * @return string must be unique within a component
     */
    public function get_id(): string {
        $class = get_class($this);
        $id = explode("\\", $class);
        return end($id);
    }

    /**
     * Get the step reference
     *
     * @return string must be globally unique
     */
    public function get_ref(): string {
        $ref = $this->get_component();
        if (!empty($ref)) {
            $ref .= '_';
        }
        $ref .= $this->get_id();
        return $ref;
    }

    /**
     * Get the short step name
     *
     * @return string
     */
    public function get_name(): string {
        $id = $this->get_id();
        return get_string("step_name_{$id}", $this->get_component());
    }

    /**
     * Returns the [min, max] number of input flows
     *
     * @return     int[] of 2 ints, min and max values for the stream
     */
    public function get_number_of_input_flows() {
        return $this->inputflows;
    }

    /**
     * Returns the [min, max] number of output flows
     *
     * @return     int[] of 2 ints, min and max values for the stream
     */
    public function get_number_of_output_flows() {
        return $this->outputflows;
    }

    /**
     * Returns the [min, max] number of input connectors
     *
     * @return     int[] of 2 ints, min and max values for the stream
     */
    public function get_number_of_input_connectors() {
        return $this->inputconnectors;
    }

    /**
     * Returns the [min, max] number of output connectors
     *
     * @return     int[] of 2 ints, min and max values for the stream
     */
    public function get_number_of_output_connectors() {
        return $this->outputconnectors;
    }

    /**
     * Validate the configuration settings.
     * Defaults to no check.
     *
     * @param object $config
     * @return true|\lang_string[] true if valid, an array of errors otherwise
     */
    public function validate_config($config) {
        return true;
    }

    /**
     * Allows each step type to determine a list of optional/required form
     * inputs for their configuration
     *
     * It's recommended you prefix the additional config related fields to avoid
     * conflicts with any existing fields.
     *
     * @param \MoodleQuickForm &$mform
     */
    public function form_add_custom_inputs(\MoodleQuickForm &$mform) {
        // Configuration - YAML format.
        $mform->addElement(
            'textarea',
            'config',
            get_string('field_config', 'tool_dataflows'),
            ['cols' => 50, 'rows' => 7]
        );
    }

    /**
     * Sets the default types for each input field defined
     *
     * This is mostly defined upfront and unlikely to change, and will show the following if not set:
     * "Did you remember to call setType() for __"
     *
     * @param \MoodleQuickForm &$mform
     */
    public function form_set_input_types(\MoodleQuickForm &$mform) {
        $fields = static::form_define_fields();
        foreach ($fields as $fieldname => $config) {
            if (isset($config['type'])) {
                $mform->setType("config_$fieldname", $config['type']);
            }
        }
    }

    /**
     * Extra validation.
     *
     * @param  stdClass $data Data to validate.
     * @param  array $files Array of files.
     * @param  array $errors Currently reported errors.
     * @return array of additional errors, or overridden errors.
     */
    public function form_extra_validation($data, $files, array &$errors) {
        $yaml = Yaml::parse($data->config, Yaml::PARSE_OBJECT_FOR_MAP);
        if (empty($yaml)) {
            $yaml = new \stdClass();
        }
        $validation = $this->validate_config($yaml);
        if ($validation === true) {
            return [];
        }

        return $validation;
    }

    /**
     * Get the default data.
     *
     * @return stdClass
     */
    public function form_get_default_data(&$data) {
        $yaml = Yaml::parse($data->config, Yaml::PARSE_OBJECT_FOR_MAP) ?? new \stdClass;
        foreach ($yaml as $key => $value) {
            $data->{"config_$key"} = $value;
        }
        return $data;
    }

    /**
     * Converts the config data if required, such that it is set as expected
     * under a single key 'config'
     *
     * @param      mixed &$data
     * @return     mixed &$data
     */
    public function form_convert_fields(&$data) {
        // Construct configuration array based on standard format.
        $fields = static::form_define_fields();
        $config = [];
        foreach ($fields as $fieldname => $unused) {
            $datafield = "config_$fieldname";
            if (!property_exists($data, $datafield)) {
                continue;
            }

            $config[$fieldname] = $data->{$datafield};
            unset($data->{$datafield});
        }

        if (!empty($config)) {
            // 4 levels of indentation before it starts to, JSONify / inline settings.
            $inline = 4;
            // 2 spaces per level of indentation.
            $indent = 2;
            $data->config = Yaml::dump($config, $inline, $indent);
        }

        return $data;
    }

    /**
     * Generates an engine step for this type.
     *
     * This should be sufficient for most cases. Override this function if needed.
     *
     * @param engine $engine
     * @param \tool_dataflows\step $stepdef
     * @return engine_step
     */
    abstract public function get_engine_step(engine $engine, \tool_dataflows\step $stepdef): engine_step;

    /**
     * Returns the group (string) this step type is categorised under.
     *
     * @return  string name of the group
     */
    abstract public function get_group(): string;

    /**
     * Returns an array with styles used to draw the dot graph
     *
     * @return  array containing the styles
     * @link    https://graphviz.org/doc/info/attrs.html for a list of available attributes
     */
    public function get_node_styles(): array {
        return self::get_base_node_styles();
    }

    /**
     * Returns an array with styles used to draw the dot graph
     *
     * @return  array containing the base styles
     */
    public static function get_base_node_styles(): array {
        $styles = [
            'color'     => 'none',
            'shape'     => 'record',
            'fillcolor' => '#ced4da',
            'fontsize'  => '10',
            'fontname'  => 'Arial',
            'style'     => 'filled',
        ];
        // TODO The border color is reserved so we can make it red for steps with errors.
        return $styles;
    }

    /**
     * Returns whether or not a field is defined in the form
     *
     * @param      string $fieldname name of the field
     * @return     bool whether or not the provided field is defined
     */
    public function is_field_valid(string $fieldname): bool {
        return isset(static::form_define_fields()[$fieldname]);
    }

    /**
     * Hook function that gets called when a step has been saved.
     *
     * @param step $stepdef
     */
    public function on_save(step $stepdef) {
    }

    /**
     * Hook function that gets called when a step is deleted.
     *
     * @param step $stepdef
     */
    public function on_delete(step $stepdef) {
    }

    /**
     * Hook function that gets called when an engine step has been finalised.
     */
    public function on_finalise() {
    }
}
