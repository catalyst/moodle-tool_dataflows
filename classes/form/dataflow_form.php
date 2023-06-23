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

use moodle_exception;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use tool_dataflows\manager;

/**
 * Dataflow Form
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dataflow_form extends \core\form\persistent {

    /** @var string Persistent class name. */
    protected static $persistentclass = 'tool_dataflows\dataflow';

    /**
     * Define the form.
     */
    public function definition() {
        $mform = $this->_form;

        // User ID.
        $mform->addElement('hidden', 'userid');
        $mform->setConstant('userid', $this->_customdata['userid']);

        // Get returnurl if not coming from the edit page.
        if (get_local_referer() != new \moodle_url("/admin/tool/dataflows/edit.php")) {
            $mform->addElement('hidden', 'returnurl');
            $mform->setType('returnurl', PARAM_LOCALURL);
            $mform->setConstant('returnurl', new \moodle_url(get_local_referer(false)));
        }

        // Name of the dataflow.
        $mform->addElement('text', 'name', get_string('field_name', 'tool_dataflows'));

        // Description.
        $mform->addElement('textarea', 'description', get_string('description'), ['cols' => 20, 'rows' => 2]);

        // Enable/Disable control.
        $mform->addElement('advcheckbox', 'enabled', get_string('dataflow_enabled', 'tool_dataflows'));
        $mform->setDefault('enabled', 0);

        // Enable/Disable concurrency.
        $mform->addElement('advcheckbox', 'concurrencyenabled', get_string('concurrency_enabled', 'tool_dataflows'));
        $mform->setDefault('concurrencyenabled', 0);

        if ($this->_customdata['concurrencyallowed'] !== true) {
            $reasons = '';
            foreach ($this->_customdata['concurrencyallowed'] as $name => $reason) {
                $reasons .= \html_writer::tag('li', "$name: $reason");
            }
            $reasons = \html_writer::tag('ul', $reasons);

            $mform->addElement('static', 'concurrencyenableddisabled', '',
                    get_string('concurrency_enabled_disabled_desc', 'tool_dataflows') . $reasons);
        }

        // Configuration - YAML format.
        $mform->addElement(
            'textarea',
            'vars',
            get_string('field_vars', 'tool_dataflows'),
            ['cols' => 50, 'rows' => 7]
        );
        $outputsexample['reference'] = \html_writer::tag('code',  htmlentities('${{dataflow.vars.<name>}}'));
        $examplestring = <<<'EOT'
data:
  something: 12     # Accessed as ${{dataflow.vars.data.something}}
EOT;
        $outputsexample['example'] = \html_writer::tag('pre', $examplestring);
        $mform->addElement('static', 'vars_help', '', get_string('field_vars_help', 'tool_dataflows', $outputsexample));

        $this->add_action_buttons();
    }

    /**
     * Extra validation.
     *
     * @param  \stdClass $data Data to validate.
     * @param  array $files Array of files.
     * @param  array $errors Currently reported errors.
     * @return array of additional errors, or overridden errors.
     */
    protected function extra_validation($data, $files, array &$errors) {
        // Ensure no updates can be made for readonly mode.
        if (manager::is_dataflows_readonly()) {
            throw new moodle_exception('readonly_active', 'tool_dataflows');
        }

        $newerrors = [];

        // Vars must be a valid YAML object.
        if (!empty(trim($data->vars))) {
            try {
                $parsed = Yaml::parse($data->vars, Yaml::PARSE_OBJECT_FOR_MAP);
                if (!is_object($parsed)) {
                    $newerrors['vars'] = get_string('error:vars_not_object', 'tool_dataflows');
                }
            } catch (ParseException $e) {
                $newerrors['vars'] = get_string('error:invalid_yaml', 'tool_dataflows', $e->getMessage());
            }
        }

        return $newerrors;
    }
}
