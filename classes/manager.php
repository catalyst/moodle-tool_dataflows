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

namespace tool_dataflows;

/**
 * Dataflows Manager
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /**
     * Return all dataflow steps available from other plugins and by default
     *
     * This is defined in lib.php for each plugin, and returned via a dataflow_step_types function
     *
     * @return array of step objects
     */
    public static function get_steps_types(): array {
        $steps = tool_dataflows_step_types();
        $moresteps = get_plugins_with_function('dataflow_step_types', 'lib.php');
        foreach ($moresteps as $plugintype => $plugins) {
            foreach ($plugins as $plugin => $pluginfunction) {
                $result = $pluginfunction();
                foreach ($result as $step) {
                    $step->set_component($plugintype . '_' . $plugin);
                    $steps[] = $step;
                }
            }
        }
        return $steps;
    }

    /**
     * Return all dataflow encoders available from other plugins and by default
     *
     * This is defined in lib.php for each plugin, and returned via a dataflow_steps function
     *
     * @return array of step objects
     */
    public static function get_encoders(): array {
        $encoders = tool_dataflows_encoders();
        $moreencoders = get_plugins_with_function('dataflows_encoders', 'lib.php');
        array_merge($encoders, $moreencoders);
        return $encoders;
    }

    /**
     * Whether or not dataflows is readonly.
     *
     * By default, all settings in dataflows are editable (readonly = false).
     * Implemented to ensure if the setting were to flip to readonly, it would
     * at least apply all the other settings sent in the SAME REQUEST without
     * failing validation.
     *
     * @return     bool true if readonly, false otherwise
     */
    public static function is_dataflows_readonly(): bool {
        static $readonly = null;
        if ($readonly === null) {
            $readonly = get_config('tool_dataflows', 'readonly');
        }
        return (bool) $readonly;
    }
}
