<?php
// This file is part of Moodle - https://moodle.org/
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
 * Gets the files in a directory.
 *
 * @package   tool_dataflows
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright Catalyst IT, 2023
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait sftp_directory_file_list_trait {
    // Utilises the functionality in both traits for a combined step.
    use sftp_trait {
        sftp_trait::form_define_fields as sftp_form_define_fields;
        sftp_trait::form_add_custom_inputs as sftp_form_add_custom_inputs;
        sftp_trait::validate_for_run as sftp_validate_for_run;
        sftp_trait::validate_config as sftp_validate_config;
    }
    use directory_file_list_trait {
        directory_file_list_trait::form_define_fields as directory_file_list_form_define_fields;
        directory_file_list_trait::form_add_custom_inputs as directory_file_list_form_add_custom_inputs;
        directory_file_list_trait::validate_for_run as directory_file_list_validate_for_run;
    }

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    public static function form_define_fields(): array {
        $fields = array_merge(
            self::sftp_form_define_fields('list'),
            self::directory_file_list_form_define_fields()
        );

        return $fields;
    }

    /**
     * Custom elements for editing the step.
     *
     * Headers added to split the sections based on context.
     *
     * @param \MoodleQuickForm $mform
     */
    public function form_add_custom_inputs(\MoodleQuickForm &$mform) {
        $mform->addElement('header', 'sftp_header', get_string('sftp:header', 'tool_dataflows'));
        $this->sftp_form_add_custom_inputs($mform, 'list');
        $mform->addElement('header', 'directory_file_list_header', get_string('directory_file_list:header', 'tool_dataflows'));
        $this->directory_file_list_form_add_custom_inputs($mform);
    }

    /**
     * Get the list of filenames.
     *
     * @return array
     */
    public function run() {
        $stepvars = $this->get_variables();
        $config = $stepvars->get('config');
        $path = $config->directory;

        // Connect to an SFTP server and list the files.
        $sftp = $this->init_sftp($config);
        $filelist = $this->list($sftp, $path);

        // Apply constraints to filter out irrelevant results.
        $filelist = $this->apply_list_constraints(
            $filelist,
            $config->returnvalue,
            $config->sort,
            $config->offset,
            $config->limit,
            isset($config->subdirectories),
            $path,
            $config->pattern
        );

        return $filelist;
    }

    /**
     * Perform any extra validation that is required only for runs.
     *
     * @return true|array Will return true or an array of errors.
     */
    public function validate_for_run() {
        $sftperrors = $this->sftp_validate_for_run('list');
        if ($sftperrors === true) {
            $sftperrors = [];
        }
        $directoryfilelisterrors = $this->directory_file_list_validate_for_run();
        if ($directoryfilelisterrors === true) {
            $directoryfilelisterrors = [];
        }

        // Merge the errors from both validation functions. Correctly handle the case where one has no errors (returns true).
        $errors = array_merge($sftperrors, $directoryfilelisterrors);
        return $errors ?: true;
    }

    /**
     * Validate the configuration settings.
     *
     * @param object $config
     * @return true|\lang_string[] true if valid, an array of errors otherwise
     */
    public function validate_config($config) {
        $errors = $this->sftp_validate_config($config, 'list');
        return $errors ?: true;
    }
}
