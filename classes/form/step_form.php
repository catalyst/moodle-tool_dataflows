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

namespace tool_dataflows\form;

use Symfony\Component\Yaml\Yaml;
use tool_dataflows\dataflow;
use tool_dataflows\parser;
use tool_dataflows\step;

/**
 * Dataflow Step Form
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class step_form extends \core\form\persistent {

    /** @var string Persistent class name. */
    protected static $persistentclass = 'tool_dataflows\step';

    /** @var array Fields to remove from the persistent validation. */
    protected static $foreignfields = ['dependson'];

    /**
     * Define the form.
     */
    public function definition() {
        $mform = $this->_form;
        $dataflowid = $this->_customdata['dataflowid'];
        $type = $this->_customdata['type'];

        // User ID.
        $mform->addElement('hidden', 'userid');
        $mform->setConstant('userid', $this->_customdata['userid']);

        // Dataflow Id.
        $mform->addElement('hidden', 'dataflowid');
        $mform->setConstant('dataflowid', $dataflowid);

        $mform->addElement('hidden', 'type');
        $mform->setConstant('type', $this->_customdata['type']);

        $mform->addElement('header', 'general', get_string('general'));

        // Name of the step.
        $mform->addElement('text', 'name', get_string('field_name', 'tool_dataflows'));
        $mform->addRule('name', get_string('missingfield', 'error', 'name'), 'required', null, 'client');

        // Description for the step which may include the purpose for its inclusion, more detail about what it does or how it works.
        $mform->addElement(
            'textarea',
            'description',
            get_string('field_description', 'tool_dataflows'),
            ['cols' => 20, 'rows' => 2]
        );

        // Alias for the step (e.g. id-like field of a yaml configured dataflow, if absent, the key for the step).
        $mform->addElement('text', 'alias', get_string('field_alias', 'tool_dataflows'));
        $mform->addElement('static', 'alias_help', '', get_string('field_alias_help', 'tool_dataflows'));
        $mform->addRule('alias', get_string('invalid_value', 'tool_dataflows'), 'regex', step::ALPHANUMEXT, 'client');

        // Depends on.
        $options = $this->get_dependson_options();
        $select = $mform->addElement(
            'select',
            'dependson',
            get_string('field_dependson', 'tool_dataflows'),
            [],
            [
                'class' => empty($options) ? 'hidden' : '', // Hidden if there are no options to select from.
                'size' => count($options),
            ]
        );
        foreach ($options as $opt) {
            $select->addOption($opt['text'], $opt['key'], $opt['attr']);
        }

        $select->setMultiple(true);

        // List all the available fields available for configuration, in dot syntax.
        $variables = $this->get_available_references();
        $mform->addElement('static', 'fields', get_string('available_fields', 'tool_dataflows'),
            $this->prepare_available_fields($variables));

        // Check and set custom form inputs if required. Defaulting to a
        // textarea config input for those not yet configured.
        $persistent = $this->get_persistent();
        if (isset($persistent->steptype) || (isset($type) && class_exists($type))) {
            $steptype = $persistent->steptype ?? new $type();
            $steptype->form_setup($mform);
        }

        // Configuration - YAML format.
        $mform->addElement(
            'textarea',
            'vars',
            get_string('field_vars', 'tool_dataflows'),
            ['cols' => 50, 'rows' => 7, 'placeholder' => "alias: \${{ <expression> }}\nanother: \${{ <expression> }}"]
        );
        $mform->setType('vars', PARAM_TEXT);
        $alias = $persistent->alias ?? '&lt;alias>';
        $a = [
            'reference' => \html_writer::tag('code', '${{ steps.' . $alias . '.vars.&lt;var> }}'),
            'example' => "icon: \${{ response.deeply.nested.data[0].icon }}  # Accessed as steps.$alias.vars.icon",
        ];
        $mform->addElement('static', 'vars_help', '', get_string('field_step_vars_help', 'tool_dataflows', $a));

        $this->add_action_buttons();
    }

    /**
     * Prepares and returns an array of dependson options
     *
     * @return  array of options this step could depend on
     */
    private function get_dependson_options(): array {
        // Show a list of other steps as options for depends on.
        $dataflowid = $this->_customdata['dataflowid'];
        $dataflow = new dataflow($dataflowid);
        $persistent = $this->get_persistent();
        $steps = $dataflow->steps;
        $options = [];

        // First lets find the 'depth' of each step in the DAG.
        $keys = array_keys((array) $steps);
        $depths = [];
        foreach ($keys as $key) {
            $depths[$key] = -1;
            $dependants = $steps->{$key}->dependencies();
            if (count($dependants) == 0) {
                $depths[$key] = 0;
            }
        }
        for ($c = 0; $c < count($depths); $c++) {
            foreach ($keys as $key) {
                if ($depths[$key] >= 0) {
                    continue;
                }
                $dependants = $steps->{$key}->dependencies();
                foreach ($dependants as $depends) {
                    $dep = $depends->alias;
                    if ($depths[$dep] >= 0) {
                        $depths[$key] = $depths[$dep] + 1;
                        break 2;
                    }
                }
            }
        }

        // Loop through the steps but only show steps where a connection can be
        // added (or if it's a current dependency).
        foreach ($steps as $step) {
            $leader = str_repeat('. ', $depths[$step->alias]);

            [, $maxoutputflows] = $step->steptype->get_number_of_output_flows();
            [, $maxoutputconnectors] = $step->steptype->get_number_of_output_connectors();
            $max = max($maxoutputflows, $maxoutputconnectors);

            // Get current step dependants, and their position to filter out the unavailable options.
            if ($max > 1) {
                $dependants = $step->dependants();

                // Check the step's defined outputs but unused outputs. For
                // instance, a case step with 10 expressions defined but only 4
                // are linked. It should show 6 possible options for the next
                // step that can get linked.
                $outputlabelscount = count($step->steptype->get_output_labels());
                $availablepositions = range(1, min($max, $outputlabelscount));

                // Check if the current step is a dependant, and if so, INCLUDE the option (and ensure it is selected).
                $currentstepid = $persistent->id;
                $selectedposition = null;
                if (!empty($currentstepid) && isset($dependants[$currentstepid]->position)) {
                    $selectedposition = $dependants[$currentstepid]->position;
                    $availablepositions[] = $selectedposition;
                    sort($availablepositions);
                }

                // New case position should always show the next available slot for a case step.
                // Updating a position should always show up to the current and any empty slots below.
                $maxposition = null;
                if ($selectedposition) {
                    $maxposition = $selectedposition;
                } else if (!empty($dependants)) {
                    $maxposition = max(array_column($dependants, 'position')) + 1;
                }

                // If there are no output connections defined yet, start
                // plucking positions from the top until there is a gap or the
                // current step position is hit. This ensures you won't see
                // positions you shouldn't be able to assign, and keeps things
                // in order.
                if (empty($outputlabelscount)) {
                    $reversed = array_reverse($availablepositions);
                    $current = reset($reversed) + 1;
                    $position = reset($reversed);
                    while (
                        // No gap.
                        $current - 1 === $position
                        // Max not reached.
                        && ($maxposition === null || $maxposition !== $position)
                        // Always keep at least one option.
                        && $position !== 1
                    ) {
                        $current = array_shift($reversed);
                        $position = reset($reversed);
                    }
                    $availablepositions = array_reverse($reversed);
                }

                // Prepare and set the (output) connection options for this step.
                foreach ($availablepositions as $position) {
                    // If the step has a defined key / label for this entry, then use that label instead.
                    // For example: 'case #14' could instead be 'case: even number detected'.
                    $outputlabel = $step->steptype->get_output_label($position);

                    $label = "{$step->name} → $outputlabel";
                    $key = $step->id . self::$persistentclass::DEPENDS_ON_POSITION_SPLITTER . $position;
                    $options[$key] = [
                        'key'  => $key,
                        'text' => $leader . $label,
                        'attr' => ($step->id === $persistent->id) ? ['disabled'] : [],
                    ];
                }
            } else {
                $key = $step->id;
                $options[$key] = [
                    'key'  => $key,
                    'text' => $leader . $step->name,
                    'attr' => ($step->id === $persistent->id) ? ['disabled'] : [],
                ]; // Will always set a new value.
            }
        }

        return $options;
    }

    /**
     * Return the HTML built for available references
     *
     * @param   array $variables
     * @return  string html of the prepared fields
     */
    private function prepare_available_fields($variables): string {
        global $OUTPUT;

        $ritit = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($variables));
        $fields = [];
        foreach ($ritit as $leaf) {
            $keys = [];
            foreach (range(0, $ritit->getDepth()) as $depth) {
                $keys[] = $ritit->getSubIterator($depth)->key();
            }
            $fields[join('.', $keys)] = $leaf;
        }

        // Annoyingly, will need to reconvert this into an array so it can be looped over in mustache.
        $allfields = $this->build_treetag($fields);
        $allfields = $this->add_recursive_keys($fields, $allfields);
        $fieldhtml = $OUTPUT->render_from_template('tool_dataflows/available-fields', [
            'groups' => $allfields,
            'help' => get_string('available_fields_help', 'tool_dataflows', '<code>${{ dataflow.name }}</code>'),
        ]);
        return $fieldhtml;
    }

    /**
     * Builds tree multidenmisionnal array from field keys
     *
     * @param array $fields unformatted array
     * @return array $allfields
     */
    public function build_treetag($fields): array {
        $allfields = [];
        $groupcreated = [];
        foreach ($fields as $key => $value) {
            $expression = $key;
            $groups = explode('.', $key);
            $last = array_pop($groups);
            $count = 0;
            $ref = &$allfields;
            foreach ($groups as $group) {
                $ref = &$ref[$group];
            }
            $ref[$expression] = $last;
        }
        return $allfields;
    }

    /**
     * Sets up properkeys for mustache templates
     *
     * @param array $values array of keys to values
     * @param array $allfields unformatted array
     * @param array $level depth of recursion
     * @return array $allfields
     */
    public function add_recursive_keys($values, $allfields, $level = 1): array {
        foreach ($allfields as $key => $value) {
            if (is_array($value)) {
                $fields = $this->add_recursive_keys($values, $value, $level + 1);
                $allfields[] = [
                    'name'   => $key,
                    'fields' => $fields,
                    'open'   => '',
                ];
                unset($allfields[$key]);
            }
            if (is_string($value)) {
                $tmp = $allfields[$key];
                unset($allfields[$key]);
                // Only inner fields have expression.
                $allfields[] = [
                    'name'       => $tmp,
                    'expression' => '${{' . $key . '}}',
                    'value'      => $values[$key],
                    'leaf'       => true,
                ];
                unset($tmp);
            }
        }
        return $allfields;
    }

    /**
     * Returns a list of possible references available in the dataflow
     *
     * The "values" are not as important. They could be real values,
     * expressions, or placeholder documentation.
     *
     * TODO: since this will currently list all references, even if it is in
     * "future steps" that might not be valid, it would be good to exclude
     * invalid options at some point.
     *
     * @return  array of all variables
     */
    private function get_available_references(): array {
        $dataflow = new dataflow($this->_customdata['dataflowid']);
        $variables = $dataflow->get_variables();

        // Prepare step outputs.
        foreach ($dataflow->steps as $alias => $step) {
            // This will only display documentation for step exposed outputs,
            // and not any real values since they are not available yet.
            $outputs = $step->steptype->define_outputs();
            foreach ($outputs as $field => $def) {
                $variables['steps']->{$alias}->{$field} = $def;
            }

            // This is a list of user defined output mappings. This will display their expression / value set.
            $vars = $step->vars;
            if (!empty($vars)) {
                if (!isset($variables['steps']->{$alias}->vars)) {
                    $variables['steps']->{$alias}->vars = new \stdClass();
                }
                foreach ($vars as $field => $def) {
                    $variables['steps']->{$alias}->vars->{$field} = $def;
                }
            }
        }

        return $variables;
    }

    /**
     * Convert fields.
     *
     * @param \stdClass $data The data.
     * @return \stdClass
     */
    protected static function convert_fields(\stdClass $data) {
        $data = parent::convert_fields($data);

        // Process and convert the received data back under the config field.
        $steptype = new $data->type();
        $steptype->form_convert_fields($data);

        return $data;
    }

    /**
     * Get the default data.
     *
     * @return stdClass
     */
    protected function get_default_data() {
        $data = parent::get_default_data();

        // Process and convert the received data back under the config field.
        $type = $this->_customdata['type'];
        if (!empty($type) && class_exists($type)) {
            $steptype = new $type();
            $data = $steptype->form_get_default_data($data);
        }

        // Automatically fill in the name for new steps.
        if (empty($data->id)) {
            $data->name = $steptype->get_name();
        }

        return $data;
    }

    /**
     * Extra validation.
     *
     * @param  stdClass $data Data to validate.
     * @param  array $files Array of files.
     * @param  array $errors Currently reported errors.
     * @return array of additional errors, or overridden errors.
     */
    protected function extra_validation($data, $files, array &$errors) {
        global $DB;

        $errors = [];

        // Process and convert the received data back under the config field.
        $steptype = new $data->type();
        $newerrors = $steptype->form_extra_validation($data, $files, $errors);

        // Fetch name and aliases for existing steps in this dataflow.
        $records = $DB->get_records(
            self::$persistentclass::TABLE,
            ['dataflowid' => $data->dataflowid],
            '',
            'id, name, alias'
        ) ?? [];

        // Existing step update, remove its name and alias from the blocklist.
        $persistent = $this->get_persistent();
        $id = $persistent->id;
        if (!empty($id)) {
            unset($records[$id]);
        }
        $names = array_column($records, 'name');
        $aliases = array_column($records, 'alias');

        // Check and ensure the name aren't reused in new or by other existing steps.
        if (in_array($data->name, $names)) {
            $errors['name'] = get_string('nametaken', 'tool_dataflows', $data->name);
        }

        // Check and ensure the aliases aren't reused in new or by other existing steps.
        if (in_array($data->alias, $aliases)) {
            $errors['alias'] = get_string('aliastaken', 'tool_dataflows', $data->alias);
        }

        // The 'vars' field must be valid YAML.
        if (isset($data->vars)) {
            $validation = parser::validate_yaml($data->vars);
            if ($validation !== true) {
                $errors['vars'] = $validation;
            }
        }

        // If the config field has been provided, ensure it is in valid YAML.
        if (isset($data->config)) {
            $validation = parser::validate_yaml($data->config);
            if ($validation !== true) {
                $errors['config'] = $validation;
            }
        }

        // Check the outputs field to ensure it's in the correct form.
        // This is required becasue validation only hits this after the config have been converted.
        if (isset($data->config) && empty($errors['config'])) {
            $config = Yaml::parse($data->config, Yaml::PARSE_OBJECT_FOR_MAP);
            if (isset($config->outputs) && is_string($config->outputs)) {
                // The outputs should always be an object / hash-map. If not, it
                // contains the error message as to why this is the case.
                $errors['config_outputs'] = $config->outputs;
            }
            $fields = $steptype::form_define_fields();
            foreach ($fields as $field => $fieldconfig) {
                // Check all yaml enabled fields, that they are in the correct expected format.
                if (!empty($fieldconfig['yaml']) && isset($config->{$field}) && is_string($config->{$field})) {
                    // The outputs should always be an object / hash-map. If not, it
                    // contains the error message as to why this is the case.
                    $errors["config_{$field}"] = $config->{$field};
                }
            }
        }

        return array_merge($errors, $newerrors);
    }
}
