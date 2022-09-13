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
use tool_dataflows\helper;
use tool_dataflows\local\execution\engine;
use tool_dataflows\local\execution\engine_step;
use tool_dataflows\parser;
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

    /** @var engine_step Engine step made by this class for an execution. */
    protected $enginestep = null;

    /** @var step The step definition use to create the engine step.  */
    protected $stepdef = null;

    /** @var array of variables exposed for use from this step. */
    protected $variables = [];

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
     * Constructs the step type
     *
     * If used during execution this will link the execution engine which requires a stepdef to be defined.
     *
     * @param step|null $stepdef A stepdef of this type. Pass null if step type is to be used without a context.
     * @param engine|null $engine An engine that executes this step type, null if not in an execution.
     */
    public function __construct(?step $stepdef = null, ?engine $engine = null) {
        $this->stepdef = $stepdef;
        // Sets the engine if it has been provided.
        if (isset($engine)) {
            $this->set_engine($engine, $stepdef);
        }
    }

    /**
     * Links the engine to this step type, which requires a step def to be provided as well.
     *
     * @param   engine $engine
     * @param   step $stepdef
     * @throws  \moodle_exception
     */
    public function set_engine(engine $engine, step $stepdef) {
        if (is_null($stepdef)) {
            throw new \moodle_exception('must_have_a_step_def_defined', '', 'tool_dataflows');
        }
        $this->enginestep = $this->generate_engine_step($engine);
    }

    /**
     * A list of outputs and their descriptions
     *
     * These fields can be used as aliases in the custom output mapping
     *
     * @return  array of outputs
     */
    public function define_outputs(): array {
        return [];
    }

    /**
     * Resolves and sets the step outputs
     *
     * This effectively sets the outputs to the values they should be based on
     * the stored variables and output configuration set. This happens at the
     * end of each step.
     */
    public function prepare_outputs() {
        throw new \moodle_exception('prepare_outputs');
        // By default, it should make available all variables exposed by this step.
        $this->stepdef->set_output($this->variables);

        // Custom user defined output mapping.
        $config = $this->stepdef->get_raw_config();
        if (isset($config->outputs)) {
            $parser = new parser;
            $enginestep = $this->get_engine_step();
            if ($enginestep) {
                $variables = $enginestep->get_variables();
            } else {
                $variables = $this->stepdef->variables;
            }
            $allvariables = array_merge($variables, $this->variables);
            $outputs = $parser->evaluate_recursive($config->outputs, $allvariables);
            $this->stepdef->set_output($outputs);

            $yaml = Yaml::dump(
                (array) $outputs,
                helper::YAML_DUMP_INLINE_LEVEL,
                helper::YAML_DUMP_INDENT_LEVEL,
                Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK
            );
            $this->enginestep->log("Debug: Setting step defined output vars:\n" . trim($yaml));
        }
    }

    /**
     * Resolves and sets variables for the 'vars' subtree.
     */
    public function prepare_vars() {
        $vars = $this->stepdef->get_raw_vars();
        if (!helper::obj_empty($vars)) {
            $parser = new parser();
            $enginestep = $this->get_engine_step();
            if ($enginestep) {
                $variables = $enginestep->get_variables();
            } else {
                $variables = $this->stepdef->variables;
            }
            $vars = $parser->evaluate_recursive($vars, $variables);
            $this->stepdef->set_varsvariables($vars);

            $yaml = Yaml::dump(
                (array) $vars,
                helper::YAML_DUMP_INLINE_LEVEL,
                helper::YAML_DUMP_INDENT_LEVEL,
                Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK
            );
            $this->enginestep->log("Debug: Setting step defined output vars:\n" . trim($yaml));
        }
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
        return $this->hassideeffect;
    }

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    public static function form_define_fields(): array {
        return [];
    }

    /**
     * Get fields containing a 'secret'
     *
     * @return  array keys of fields containing secrets
     */
    public function get_secret_fields(): array {
        $fields = $this->form_define_fields();
        $secrets = [];
        foreach ($fields as $key => $config) {
            // The field must be set up as `secret => true`.
            if (!empty($config['secret'])) {
                $secrets[] = $key;
            }
        }
        return $secrets;
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
     * Can dataflows with this step be executed in parallel.
     *
     * @return string|true True if concurrency is supported, or a string giving a reason why it doesn't.
     */
    public function is_concurrency_supported() {
        return true;
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
     * Sets up the form fields and inputs.
     *
     * @param \MoodleQuickForm $mform
     */
    public function form_setup(\MoodleQuickForm &$mform) {
        // Add custom configuration fields under their own header, but only if there are custom fields to add.
        if (!empty(static::form_define_fields())) {
            $mform->addElement('header', 'stepheader', $this->get_name());
            $this->form_add_custom_inputs($mform);
            $this->form_set_input_types($mform);
            $this->form_set_input_rules($mform);
        }

        $mform->addElement('header', 'extraheader', get_string('stepextras', 'tool_dataflows'));
    }

    /**
     * Allows each step type to determine a list of optional/required form
     * inputs for their configuration
     *
     * It's recommended you prefix the additional config related fields to avoid
     * conflicts with any existing fields.
     *
     * @param \MoodleQuickForm $mform
     */
    public function form_add_custom_inputs(\MoodleQuickForm &$mform) {
    }

    /**
     * Sets the default types for each input field defined
     *
     * This is mostly defined upfront and unlikely to change, and will show the following if not set:
     * "Did you remember to call setType() for __"
     *
     * @param \MoodleQuickForm $mform
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
     * Sets rules for each input field defined if supported
     *
     * @param \MoodleQuickForm $mform
     */
    public function form_set_input_rules(\MoodleQuickForm &$mform) {
        $fields = static::form_define_fields();
        foreach ($fields as $fieldname => $config) {
            if (!empty($config['required'])) {
                $mform->addRule("config_$fieldname", get_string('required'), 'required', null, 'client');
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
        if (empty(static::form_define_fields())) {
            return [];
        }

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
     * @param   \stdClass $data from the persistent form class
     * @return  \stdClass
     */
    public function form_get_default_data(\stdClass $data): \stdClass {
        // Get the fields configuration array.
        $fields = static::form_define_fields();

        $yaml = Yaml::parse($data->config, Yaml::PARSE_OBJECT_FOR_MAP) ?? new \stdClass;
        foreach ($yaml as $key => $value) {
            $data->{"config_$key"} = $value;

            if ($key === 'outputs' || !empty($fields[$key]['yaml'])) {
                // Handling for "outputs", and fields marked as yaml-enabled.
                $data->{"config_$key"} = Yaml::dump(
                    $value,
                    helper::YAML_DUMP_INLINE_LEVEL,
                    helper::YAML_DUMP_INDENT_LEVEL,
                    Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | YAML::DUMP_OBJECT_AS_MAP
                );
            }
        }
        return $data;
    }

    /**
     * Converts the config data if required, such that it is set as expected
     * under a single key 'config'
     *
     * @param      mixed $data
     * @return     mixed $data
     */
    public function form_convert_fields(&$data) {
        // Construct configuration array based on standard format.
        $fields = static::form_define_fields();

        // Add in the outputs field.
        $fields += ['outputs' => ['yaml' => true]];

        $config = [];
        foreach ($fields as $fieldname => $fieldconfig) {
            $datafield = "config_$fieldname";
            if (!property_exists($data, $datafield)) {
                continue;
            }

            $config[$fieldname] = $data->{$datafield};
            // Use '\n' instead of '\r\n' for new lines to allow YAML multi-line literals to work.
            $config[$fieldname] = str_replace("\r\n", "\n", $config[$fieldname]);

            // Handle outputs (convert to a proper data structure).
            if (!empty($fieldconfig['yaml'])) {
                // Attempt to convert the data to the appropriate format. Due to
                // persistent handling, validation happens at a differnt point
                // in time from data conversion and so it is a bit disconnected.
                // We still want the data stored in the expected format though.
                try {
                    $config[$fieldname] = Yaml::parse($config[$fieldname], Yaml::PARSE_OBJECT);
                } catch (\Exception $e) { // phpcs:ignore
                    $config[$fieldname] = $e->getMessage();
                }

                // If the field is not set (null), then remove it since it does
                // not need to be stored.
                if (!isset($config[$fieldname])) {
                    unset($config[$fieldname]);
                }
            }

            unset($data->{$datafield});
        }

        if (!empty($config)) {
            $data->config = Yaml::dump(
                $config,
                helper::YAML_DUMP_INLINE_LEVEL,
                helper::YAML_DUMP_INDENT_LEVEL,
                Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | YAML::DUMP_OBJECT_AS_MAP
            );
        }

        return $data;
    }

    /**
     * Perform any extra validation that is required only for runs.

     * @return true|array Will return true or an array of errors.
     */
    public function validate_for_run() {
        return true;
    }

    /**
     * Generates an engine step for this type.
     *
     * This should be sufficient for most cases. Override this function if needed.
     *
     * @return engine_step
     */
    final public function get_engine_step(): ?engine_step {
        return $this->enginestep;
    }

    /**
     * Generate the engine step for the step type.
     *
     * @param engine $engine
     * @return engine_step
     */
    abstract protected function generate_engine_step(engine $engine): engine_step;

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
            'height'    => 0.5,
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
     * Return any miscellaneous, step type specific information that the user would be interested in.
     *
     * @return string
     */
    public function get_details(): string {
        return '';
    }

    /**
     * Hook function that gets called when a step has been saved.
     */
    public function on_save() {
    }

    /**
     * Hook function that gets called when a step is deleted.
     */
    public function on_delete() {
    }

    /**
     * Hook function that gets called when a step has been initialised.
     */
    public function on_initialise() {
    }

    /**
     * Hook function that gets called when an engine step has been finalised.
     */
    public function on_finalise() {
    }

    /**
     * Set variables available from this step
     *
     * Any variable you want "exposed" from this step (e.g. the response from a
     * cURL or web service call), should be set here. This will part of the
     * source of truth for user-defined output values (step.outputs.some-user-mapping)
     *
     * @param   string $name
     * @param   mixed $value
     */
    public function set_variables(string $name, $value) {
        $this->stepdef->set_rootvariables([$name => $value]);
    }

    /**
     * Returns a list of labels available for a given step
     *
     * By default, this would be the position / order of each connected output
     * (and show as a number). Each case can however based on its own
     * configuration handling, determine the label it chooses to set and display
     * for the output connection. This will only be used and called if there are
     * more than one expected output.
     *
     * @return  array of labels defined for this step type
     */
    public function get_output_labels(): array {
        return [];
    }

    /**
     * Returns the output (connection / link) label
     *
     * @param   int $position of the output connection
     * @return  string $label
     */
    public function get_output_label(int $position): string {
        $labels = $this->get_output_labels();
        $label = $labels[$position] ?? (string) $position;
        return $label;
    }

    /**
     * Get the step's (definition) current config.
     *
     * Helper method to reduce the complexity when authoring step types.
     *
     * @return  \stdClass configuration object
     */
    protected function get_config(): \stdClass {
        return $this->stepdef->config;
    }

    /**
     * Get the step's (definition) raw config
     *
     * Helper method to reduce the complexity when authoring step types.
     *
     * @return  \stdClass configuration object
     */
    protected function get_raw_config(): \stdClass {
        return $this->stepdef->get_raw_config();
    }

    /**
     * Returns whether the engine's run is dry
     *
     * Helper method to reduce the complexity when authoring step types.
     *
     * @return  bool mode is in dry run or not
     */
    protected function is_dry_run(): bool {
        return $this->enginestep->engine->isdryrun;
    }

    /**
     * Emits a log message
     *
     * Helper method to reduce the complexity when authoring step types.
     *
     * @param string $message
     */
    protected function log(string $message) {
        $this->enginestep->log($message);
    }
}
