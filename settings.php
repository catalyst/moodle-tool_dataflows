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

/**
 * Settings
 *
 * @package    tool_dataflows
 * @author     Jason den Dulk <jasondendulk@catalyst-au.net>
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('tools', new admin_category('tool_dataflows', get_string('pluginname', 'tool_dataflows')));

    $settings = new admin_settingpage('tool_dataflows_settings',
        get_string('pluginsettings', 'tool_dataflows'));

    $settings->add(new admin_setting_configcheckbox('tool_dataflows/enabled',
        get_string('enabled', 'tool_dataflows'),
        get_string('enabled_help', 'tool_dataflows'), '0'));

    $dataflowsettings = new admin_externalpage('tool_dataflows_dataflowsettings',
        get_string('pluginmanage', 'tool_dataflows'),
        new moodle_url('/admin/tool/dataflows/overview.php'));

    $ADMIN->add('tool_dataflows', $settings);
    $ADMIN->add('tool_dataflows', $dataflowsettings);

    $settings = null;
}
