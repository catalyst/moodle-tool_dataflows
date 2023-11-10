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

/**
 * Find (a record from a collection)
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class connector_find extends connector_step {

    /**
     * Returns whether the step has a side effect.
     *
     * @return     bool whether or not this step has a side effect
     * @link https://en.wikipedia.org/wiki/Side_effect_(computer_science)
     */
    public function has_side_effect(): bool {
        return false;
    }

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    public static function form_define_fields(): array {
        return [
            'collection' => ['type' => PARAM_TEXT, 'required' => true],
            'condition' => ['type' => PARAM_TEXT, 'required' => true],
        ];
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
        $mform->addElement('text', 'config_collection', get_string('find:collection', 'tool_dataflows'));
        $mform->addElement('static', 'config_collection_help', '', get_string('find:collection_help', 'tool_dataflows'));
        $mform->addElement('text', 'config_condition', get_string('find:condition', 'tool_dataflows'));
        $mform->addElement('static', 'config_condition_help', '', get_string('find:condition_help', 'tool_dataflows'));
    }

    /**
     * Find based on the condition, and set the matching record given a record.
     *
     * If the message is empty, it will not log anything, which is useful for conditional logging.
     *
     * @param mixed|null $input
     * @return mixed
     */
    public function execute($input = null) {
        $variables = $this->get_variables();
        $config = $variables->get('config');
        $collection = $config->collection;

        foreach ($collection as $item) {
            $variables->set('record', $item);
            $condition = $variables->evaluate('${{'.$config->condition.'}}');
            if ($condition) {
                $input = $item;
                break;
            }
        }
        $variables->set('match', $input);

        return $input;
    }
}
