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

namespace tool_dataflows;

/**
 * Support functions for dataflows.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /** The level where you switch to inline YAML. */
    public const YAML_DUMP_INLINE_LEVEL = 5;

    /** The amount of spaces to use for indentation of nested nodes. */
    public const YAML_DUMP_INDENT_LEVEL = 2;

    /** String used to indicate the dataroot directory. */
    public const DATAROOT_PLACEHOLDER = '[dataroot]';

    /** @var null|bool Is this Windows?  */
    protected static $iswindows = null;

    /**
     * Get the scheme for a path string. will default to 'file' if none present.
     *
     * @param string $path
     * @return string
     */
    public static function path_get_scheme(string $path): string {
        $splitpath = explode('://', $path, 2);
        if (count($splitpath) !== 2) {
            return 'file';
        }
        return $splitpath[0];
    }

    /**
     * Does the path string include a scheme?
     *
     * @param string $path
     * @return bool
     */
    public static function path_has_scheme(string $path): bool {
        $splitpath = explode('://', $path, 2);
        return count($splitpath) === 2;
    }

    /**
     * Is the path a relative one?
     * Note: Windows paths are not yet supported.
     *
     * @param string $path
     * @return bool
     */
    public static function path_is_relative(string $path): bool {
        if (self::path_has_scheme($path)) {
            return false;
        }

        // Unix absolute path.
        return substr($path, 0, 1) !== '/';

        // TODO: Windows support.
    }

    /**
     * Makes a full path from a relative one using the given base dir.
     *
     * @param  string $path
     * @param  string $scratchdir
     * @return string
     */
    public static function path_get_absolute(string $path, string $scratchdir): string {
        if (self::path_is_relative($path)) {
            return $scratchdir . '/' . $path;
        }
        return $path;
    }

    /**
     * Validate a path.
     *
     * @param  string $path
     * @return \lang_string|true True if the path is valid. A lang_string otherwise.
     */
    public static function path_validate(string $path) {
        if (self::path_is_relative($path)) {
            return true;
        }

        if (self::path_get_scheme($path) !== 'file') {
            return true;
        }

        $permitteddirs = self::get_permitted_dirs();
        foreach ($permitteddirs as $dir) {
            if (substr($path, 0, strlen($dir)) === $dir) {
                return true;
            }
        }

        return get_string('path_invalid', 'tool_dataflows', $path, true);
    }

    /**
     * Gets the list of permitted directories that steps are allowed to interact with.
     * The [dataroot] placeholder will be substituted with the correct dir.
     *
     * @return array
     */
    public static function get_permitted_dirs(): array {
        global $CFG;

        $data = get_config('tool_dataflows', 'permitted_dirs');

        // Strip comments.
        $data = preg_replace('!/\*.*?\*/!s', '', $data);

        $dirs = explode(PHP_EOL, $data);

        $permitteddirs = [];
        foreach ($dirs as $dir) {

            // Strip # comments, and trim.
            $dir = trim(preg_replace('/#.*$/', '', $dir));

            if ($dir == '') {
                continue;
            }

            // Substitute '[dataroot]' placeholder with the site's data root directory.
            if (substr($dir, 0, strlen(self::DATAROOT_PLACEHOLDER)) == self::DATAROOT_PLACEHOLDER) {
                $dir = $CFG->dataroot . substr($dir, strlen(self::DATAROOT_PLACEHOLDER));
            }
            $permitteddirs[] = $dir;
        }
        return $permitteddirs;
    }

    /**
     * Determines if the given path is a valid filepath.
     * Derived from https://stackoverflow.com/a/12126772.
     *
     * @param string $path The path to check.
     * @return bool
     */
    public static function is_filepath(string $path): bool {
        // Match against valid path syntax.
        if (preg_match('/^[^*?"<>|:]*$/', $path)) {
            return true;
        }

        if (self::$iswindows === null) {
            $tmp = dirname(__FILE__);
            self::$iswindows = strpos($tmp, '/', 0) === false;
        }

        if (self::$iswindows) {
            // Look for a drive name (e.g. C:).
            if (strpos($path, ":") === 1 && preg_match('/[a-zA-Z]/', $path[0])) {
                // Strip the drive name.
                $localpath = substr($path, 2);
                // Match against valid Windows path syntax.
                return (preg_match('/^[^*?"<>|:]*$/', $localpath) === 1);
            }
            return false;
        }

        return false;
    }
}
