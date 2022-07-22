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

        // Show a list of other steps as options for depends on.
        $dataflow = new dataflow($dataflowid);
        $persistent = $this->get_persistent();
        $steps = $dataflow->steps;
        $options = [];
        foreach ($steps as $step) {
            $options[$step->id] = $step->name;
        }
        unset($options[$persistent->id]); // We never want a step to depend on itself.

        $select = $mform->addElement(
            'select',
            'dependson',
            get_string('field_dependson', 'tool_dataflows'),
            $options,
            [
                'class' => empty($options) ? 'hidden' : '', // Hidden if there are no options to select from.
                'size' => count($options),
            ]
        );
        $select->setMultiple(true);

        // List all the available fields available for configuration, in dot syntax.
        $variables = $this->get_available_references();
        $mform->addElement('html', $this->prepare_available_fields($variables));

        // Check and set custom form inputs if required. Defaulting to a
        // textarea config input for those not yet configured.
        if (isset($persistent->steptype) || (isset($type) && class_exists($type))) {
            $steptype = $persistent->steptype ?? new $type();
            $steptype->form_setup($mform);
        }

        // Configuration - YAML format.
        $mform->addElement(
            'textarea',
            'config_outputs',
            get_string('field_outputs', 'tool_dataflows'),
            ['cols' => 50, 'rows' => 7, 'placeholder' => "alias: \${{ <expression> }}\nanother: \${{ <expression> }}"]
        );
        $mform->setType("config_outputs", PARAM_TEXT);
        $exampleconfig = trim(Yaml::dump(['icon' => '${{ response.deeply.nested.data[0].icon }}']));
        $outputsexample['config'] = \html_writer::tag('code', $exampleconfig);
        $alias = $persistent->alias ?? 'alias';
        $outputsexample['reference'] = \html_writer::tag('code', '${{ steps.' . $alias . '.icon }}');
        $mform->addElement('static', 'outputs_help', '', get_string('field_outputs_help', 'tool_dataflows', $outputsexample));

        $this->add_action_buttons();
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
        $allfields = [];
        $groupcreated = [];
        $upperfieldcreated = [];
        foreach ($fields as $key => $value) {
            $group = explode('.', $key)[0];
            if (!isset($groupcreated[$group])) {
                $groupcreated[$group] = count($groupcreated);
                $allfields[$groupcreated[$group]]['name'] = $group;
            }
            $upperfield = explode('.', $key);
            if (is_array($upperfield) && count($upperfield) > 2) {
                $upperfieldname = $upperfield[0] . '.' . $upperfield[1];
                unset($upperfield[0], $upperfield[1]);
                $innerfieldname = implode('.', $upperfield);
                if (!isset($upperfieldcreated[$upperfieldname])) {
                    $upperfieldcreated[$upperfieldname] = count($upperfieldcreated);
                }
                // Points in ids are not working hence the hyphen change.
                $allfields[$groupcreated[$group]]['fields'][$upperfieldcreated[$upperfieldname]]['collapsibleid'] = str_replace('.', '-', $upperfieldname);
                $allfields[$groupcreated[$group]]['fields'][$upperfieldcreated[$upperfieldname]]['upperfieldname'] = $upperfieldname;
                $allfields[$groupcreated[$group]]['fields'][$upperfieldcreated[$upperfieldname]]['subfields'][] = [
                    'text' => $innerfieldname,
                ];
            } else {
                $allfields[$groupcreated[$group]]['fields'][] = ['text' => $key, 'title' => $value];
            }
        }
        $fieldhtml = $OUTPUT->render_from_template('tool_dataflows/available-fields', ['groups' => $allfields]);
        return $fieldhtml;
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

            // This is a list of user defined output mappings. This will display their expression / value set.
            $userdefinedoutputs = (array) $step->get_raw_config()->outputs;
            $outputs = array_merge($outputs, $userdefinedoutputs);
            foreach ($outputs as $field => $description) {
                $variables['steps']->{$alias}->{$field} = $description;
            }

            // Remove *.config.outputs from the available references.
            // Since this is just mapping data and doesn't contain the actual
            // output values, this shouldn't be exposed. The user most likely
            // wants to reference the output itself not the configuration.
            unset($variables['steps']->{$alias}->config->outputs);
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

        // If the config field has been provided, ensure it is in valid YAML.
        if (isset($data->config)) {
            $parser = new parser;
            $validation = $parser->validate_yaml($data->config);
            if ($validation !== true) {
                $errors['config'] = $validation;
            }
        }

        // Check the outputs field to ensure it's in the correct form.
        if (isset($data->config) && empty($errors['config'])) {
            $config = Yaml::parse($data->config, Yaml::PARSE_OBJECT_FOR_MAP);
            if (isset($config->outputs) && is_string($config->outputs)) {
                // The outputs should always be an object / hash-map. If not, it
                // contains the error message as to why this is the case.
                $errors['config_outputs'] = $config->outputs;
            }
        }

        return array_merge($errors, $newerrors);
    }
}
