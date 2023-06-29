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

namespace tool_dataflows\external;

use external_function_parameters;
use external_single_structure;
use external_value;
use tool_dataflows\dataflow;
use tool_dataflows\local\step\trigger_webservice;
use tool_dataflows\task\process_dataflow_ad_hoc;

/**
 * Trigger dataflow webservice function.
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2023
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class trigger_dataflow extends \external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'dataflow' => new external_single_structure([
                'id' => new external_value(PARAM_INT, 'dataflow record id'),
            ])
        ]);
    }

    /**
     * Returns description of method return values
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'dataflow record id'),
        ]);
    }

    /**
     * Trigger a dataflow run
     *
     * Currently it only supports asynchronous execution.
     *
     * @param array $dataflow array of dataflow run information.
     * @return array of newly created groups
     */
    public static function execute($dataflow): array {
        $params = self::validate_parameters(self::execute_parameters(), ['dataflow' => $dataflow]);

        $dataflow = new dataflow($params['dataflow']['id']);

        // Confirm the dataflow contains a webservice trigger step.
        if (!$dataflow->has_trigger_step(trigger_webservice::class)) {
            throw new \invalid_parameter_exception('The dataflow does not contain a webservice trigger step.');
        }

        process_dataflow_ad_hoc::queue_adhoctask($dataflow);

        if (empty($dataflow->get('id'))) {
            throw new \invalid_parameter_exception('Unable to load the dataflow requested.');
        }

        return ['id' => $dataflow->id];
    }
}
