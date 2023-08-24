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

use tool_dataflows\helper;
use tool_dataflows\admin\admin_setting_permitted_directories;
use tool_dataflows\admin\admin_setting_yaml;
use tool_dataflows\admin\admin_setting_configcheckbox;
use tool_dataflows\admin\admin_setting_configtext;
use tool_dataflows\admin\admin_setting_configexecutable;
use tool_dataflows\admin\admin_setting_configmultiselect;
use tool_dataflows\local\execution\logging\log_handler;
use tool_dataflows\manager;

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('tools', new admin_category('tool_dataflows', get_string('pluginname', 'tool_dataflows')));

    $settings = new admin_settingpage('tool_dataflows_settings',
        get_string('pluginsettings', 'tool_dataflows'));

    $dataflowsettings = new admin_externalpage('tool_dataflows_overview',
        get_string('pluginmanage', 'tool_dataflows'),
        new moodle_url('/admin/tool/dataflows/index.php'));

    if ($ADMIN->fulltree) {
        if (manager::is_dataflows_readonly()) {
            $settings->add(new admin_setting_description(
                'tool_dataflows/readonly_active',
                '',
                html_writer::div(
                    get_string( 'readonly_active', 'tool_dataflows'),
                    'alert alert-warning'
                )
            ));
        }

        if (!helper::is_graphviz_dot_installed()) {
            $settings->add(new admin_setting_description(
                'tool_dataflows/nodot',
                '',
                html_writer::div(
                    get_string(
                        'no_dot_installed',
                        'tool_dataflows',
                        \html_writer::link(
                            helper::README_DEPENDENCY_LINK,
                            get_string('here', 'tool_dataflows')
                        )
                    ),
                    'alert alert-warning'
                )
            ));
        }

        $settings->add(new admin_setting_configcheckbox(
            'tool_dataflows/enabled',
            get_string('enabled', 'tool_dataflows'),
            get_string('enabled_help', 'tool_dataflows'),
            '0'
        ));

        $settings->add(new admin_setting_configcheckbox(
            'tool_dataflows/readonly',
            get_string('readonly', 'tool_dataflows'),
            get_string('readonly_help', 'tool_dataflows'),
            '0'
        ));

        $settings->add(new admin_setting_permitted_directories(
            'tool_dataflows/permitted_dirs',
            get_string('permitted_dirs', 'tool_dataflows'),
            get_string('permitted_dirs_desc', 'tool_dataflows', helper::DATAROOT_PLACEHOLDER),
            '',
            PARAM_RAW
        ));

        $settings->add(new admin_setting_yaml(
            'tool_dataflows/global_vars',
            get_string('global_vars', 'tool_dataflows'),
            get_string('global_vars_desc', 'tool_dataflows'),
            '',
            PARAM_RAW
        ));

        // Multi-select element used to enable different logging handlers.
        $settings->add(new admin_setting_configmultiselect(
            'tool_dataflows/log_handlers',
            get_string('log_handlers', 'tool_dataflows'),
            get_string('log_handlers_desc', 'tool_dataflows'),
            [],
            [
                log_handler::BROWSER_CONSOLE => get_string('log_handler_browser_console', 'tool_dataflows'),
                log_handler::FILE_PER_DATAFLOW => get_string('log_handler_file_per_dataflow', 'tool_dataflows'),
                log_handler::FILE_PER_RUN => get_string('log_handler_file_per_run', 'tool_dataflows'),
            ]
        ));

        $settings->add(
            new admin_setting_configexecutable(
                'tool_dataflows/gpg_exec_path',
                get_string('gpg_exec_path', 'tool_dataflows'),
                get_string('gpg_exec_path_desc', 'tool_dataflows'),
                '/usr/bin/gpg'
            )
        );

        $settings->add(
            new admin_setting_configexecutable(
                'tool_dataflows/gzip_exec_path',
                get_string('gzip_exec_path', 'tool_dataflows'),
                get_string('gzip_exec_path_desc', 'tool_dataflows'),
                '/usr/bin/gzip'
            )
        );

        $settings->add(
            new admin_setting_configtext(
                'tool_dataflows/gpg_key_dir',
                get_string('gpg_key_dir', 'tool_dataflows'),
                get_string('gpg_key_dir_desc', 'tool_dataflows'),
                '',
                PARAM_TEXT
            )
        );
    }

    $ADMIN->add('tool_dataflows', $settings);
    $ADMIN->add('tool_dataflows', $dataflowsettings);

    $settings = null;
}
