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

use MoodleQuickForm;
use tool_dataflows\helper;

/**
 * Gets the files in a directory.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait directory_file_list_trait {

    /**
     * @var string The default pattern for matching files.
     */
    static protected $patterndefault = '*';

    /**
     * @var int The default offset.
     */
    static protected $offsetdefault = 0;

    /**
     * @var int The default limit (effectively infinity).
     */
    static protected $lmiitdefault = 0;

    /**
     * @var string The format of the return value
     */
    static protected $returnvaluedefault = 'basename';

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    public static function form_define_fields(): array {
        return [
            'directory'   => ['type' => PARAM_TEXT],
            'pattern' => ['type' => PARAM_TEXT],
            'returnvalue' => ['type' => PARAM_TEXT],
            'sort' => ['type' => PARAM_TEXT],
            'subdirectories'   => ['type' => PARAM_BOOL],
            'offset'     => ['type' => PARAM_INT],
            'limit'     => ['type' => PARAM_INT],
        ];
    }

    /**
     * Custom elements for editing the connector.
     *
     * @param \MoodleQuickForm $mform
     */
    public function form_add_custom_inputs(\MoodleQuickForm &$mform) {
        // Directory.
        $mform->addElement('text', 'config_directory', get_string('directory'));

        // File pattern.
        $mform->addElement(
            'text',
            'config_pattern',
            get_string('directory_file_list:pattern', 'tool_dataflows'),
            ['placeholder' => self::$patterndefault]
        );

        // Include subdirectories.
        $mform->addElement('checkbox', 'config_subdirectories', get_string('directory_file_list:subdirectories', 'tool_dataflows'));

        // Return value.
        $mform->addElement(
            'select', 'config_returnvalue', get_string('directory_file_list:returnvalue', 'tool_dataflows'), [
            'basename' => get_string('directory_file_list:basename', 'tool_dataflows'),
            'relativepath' => get_string('directory_file_list:relativepath', 'tool_dataflows'),
            'absolutepath' => get_string('directory_file_list:absolutepath', 'tool_dataflows'),
            ]
        );

        // Sort.
        $mform->addElement(
            'select', 'config_sort', get_string('directory_file_list:sort', 'tool_dataflows'), [
            'alpha' => get_string('directory_file_list:alpha', 'tool_dataflows'),
            'alpha_reverse' => get_string('directory_file_list:alpha_reverse', 'tool_dataflows'),
            ]
        );

        // Offset.
        $mform->addElement(
            'text',
            'config_offset',
            get_string('directory_file_list:offset', 'tool_dataflows'),
            ['placeholder' => self::$offsetdefault]
        );

        // Limit.
        $mform->addElement(
            'text',
            'config_limit',
            get_string('directory_file_list:limit', 'tool_dataflows'),
            ['placeholder' => self::$lmiitdefault]
        );
    }

    /**
     * Get the list of filenames.
     *
     * @return array
     */
    public function run() {
        $variables = $this->get_variables();
        $config = $variables->get('config');

        $pattern  = $config->pattern ?: self::$patterndefault;
        $offset = $config->offset ?: self::$offsetdefault;
        $returnvalue = $config->returnvalue ?? self::$returnvaluedefault;
        $limit = $config->limit ?: null;
        $includedir = isset($config->subdirectories);

        $path = $this->get_engine()->resolve_path($config->directory);
        $error = helper::path_validate($config->directory);
        if ($error !== true) {
            $this->get_engine()->abort($error);
        }

        $pathpattern = $path . DIRECTORY_SEPARATOR . $pattern;
        $filelist = glob($pathpattern, GLOB_BRACE) || [];
        if ($filelist !== false) {
            $this->apply_list_constraints($filelist, $returnvalue, $config->sort, $offset, $limit, $includedir, $path);
        }

        return $filelist;
    }

    /**
     * Perform any extra validation that is required only for runs.
     *
     * @return true|array Will return true or an array of errors.
     */
    public function validate_for_run() {
        $config = $this->get_variables()->get('config');

        $errors = [];

        $error = helper::path_validate($config->directory);
        if ($error !== true) {
            $errors["config_directory"] = $error;
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Apply various constraints/transforms to the list of files
     *
     * @param  array<string> $filelist
     * @param  mixed         $returnvalue
     * @param  mixed         $offset
     * @param  mixed         $limit
     * @param  bool          $includedir
     * @param  string        $path
     * @return array Will return true or an array of errors.
     */
    public function apply_list_constraints(
        array $filelist,
        $returnvalue,
        string $sort,
        $offset,
        ?int $limit,
        bool $includedir,
        string $basepath,
        string $pattern = null
    ): array {
        // Apply filter for excluding directories/folders.
        // This is not related to recursive lookups in any way.
        $filelist = array_filter(
            $filelist, function ($pathname) use ($includedir, $pattern) {
                $filename = basename($pathname);
                if ($filename === '.' || $filename === '..') {
                    return false;
                }
                if (!$includedir && is_dir($pathname)) {
                    return false;
                }

                // Allows matching on a glob-like pattern.
                if ($pattern) {
                    return fnmatch($pattern, $pathname);
                }

                return true;
            }
        );

        // Convert the original to return the base file name.
        // For example, "/my/path/to/file" would return "file".
        if ($returnvalue === 'basename') {
            $filelist = array_map(
                function ($filename) {
                    return basename($filename);
                }, $filelist
            );
        }

        // Convert the original to return the relative file path.
        // For example, "/my/path/to/file" would return "to/file".
        if ($returnvalue === 'relativepath' && !empty($basepath)) {
            $filelist = array_map(
                function ($filename) use ($basepath) {
                    return str_replace($basepath . DIRECTORY_SEPARATOR, '', $filename);
                }, $filelist
            );
        }

        // Apply sorting.
        switch ($sort) {
            case 'alpha':
                $func = 'asort';
                break;
            case 'alpha_reverse':
                $func = 'arsort';
                break;
        }

        $func($filelist);

        // Apply the offset and limit.
        $filelist = array_slice($filelist, $offset, $limit);

        return $filelist;
    }
}
