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

    /** Alternate icon to display if Graphviz is not available. */
    public const GRAPHVIZ_ALT_ICON = 'i/incorrect';

    /** Location of the dependency section of the readme. */
    public const README_DEPENDENCY_LINK = 'https://github.com/catalyst/moodle-tool_dataflows#dependencies';

    /** Regular expression for a valid HTTP header name. */
    public const HTTP_HEADER_REGEX = '/^[A-Za-z0-9!#$%&\'*+-.=^_`|]+$/'; // phpcs:ignore moodle.Strings.ForbiddenStrings.Found

    /** CFG and SITE settings to be included as variables. */
    public const CFG_VARS = [
        'fullname',
        'shortname',
        'supportemail',
        'supportname',
        'supportpage',
        'wwwroot',
    ];

    /** @var null|bool Is this Windows? */
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
     * Does the path string include a given scheme?
     *
     * @param string $path
     * @param string|null $scheme The scheme to test for. If null, then any scheme will match.
     * @return bool
     */
    public static function path_has_scheme(string $path, ?string $scheme = null): bool {
        $splitpath = explode('://', $path, 2);
        if (count($splitpath) !== 2 ) {
            return false;
        }
        if (is_null($scheme)) {
            return true;
        }
        return strtolower($splitpath[0]) === strtolower($scheme);
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
        $path = self::replace_dataroot_in_path($path);
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
            $dir = self::replace_dataroot_in_path($dir);
            $permitteddirs[] = $dir;
        }
        return $permitteddirs;
    }

    /**
     * Substitutes '[dataroot]' placeholder with the site's data root directory.
     *
     * @param  string $path
     * @return string $path
     */
    private static function replace_dataroot_in_path(string $path): string {
        global $CFG;
        // Replaces the placeholder with the dataroot path, if present.
        if (substr($path, 0, strlen(self::DATAROOT_PLACEHOLDER)) == self::DATAROOT_PLACEHOLDER) {
            $path = $CFG->dataroot . substr($path, strlen(self::DATAROOT_PLACEHOLDER));
        }
        return $path;
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
            if (strpos($path, ':') === 1 && preg_match('/[a-zA-Z]/', $path[0])) {
                // Strip the drive name.
                $localpath = substr($path, 2);
                // Match against valid Windows path syntax.
                return (preg_match('/^[^*?"<>|:]*$/', $localpath) === 1);
            }
            return false;
        }

        return false;
    }

    /**
     * Determines if the Graphviz dot program can be accessed.
     *
     * @return bool
     */
    public static function is_graphviz_dot_installed(): bool {
        global $CFG;
        if (!empty($CFG->pathtodot)) {
            return is_executable($CFG->pathtodot);
        }

        // Don't know where dot is, so look for it.
        return !empty(shell_exec('which dot'));
    }

    /**
     * Creates a list of variables from CFG and SITE settings.
     *
     * @return \stdClass
     */
    public static function get_cfg_vars(): \stdClass {
        global $CFG, $SITE;

        $configvars = new \stdClass();
        foreach (self::CFG_VARS as $name) {
            if (property_exists($CFG, $name)) {
                $configvars->{$name} = $CFG->{$name};
            } else if (property_exists($SITE, $name)) {
                $configvars->{$name} = $SITE->{$name};
            }
        }
        return $configvars;
    }

    /**
     * An efficient way to test if an object is empty.
     *
     * @param \stdClass $obj
     * @return bool
     */
    public static function obj_empty(\stdClass $obj): bool {
        foreach ($obj as $prop) {
            return false;
        }
        return true;
    }

    /**
     * Takes a string and turns it into an escaped bash string.
     *
     * @param string $content
     * @return string
     */
    public static function bash_escape(string $content): string {
        return "'" . str_replace("'", "'\''", $content) . "'";
    }

    /**
     * Extract headers from given content in accordance with RFC2616.
     *
     * See https://www.rfc-editor.org/rfc/rfc2616.html#section-4.2
     *
     * @param string $content
     * @return array|bool The array of header => value or false if the headers were invalid.
     */
    public static function extract_http_headers(string $content) {
        $content = trim($content);
        if ($content === '') {
            return [];
        }
        $lines = explode(PHP_EOL, $content);
        $headerlines = [];
        $current = null;

        // Look for multiline headers and contract them into a single line for each.
        foreach ($lines as $line) {
            if (trim($line) === '') {
                return false; // Cannot have empty lines.
            }
            if (ctype_space(ord($line))) {
                $current .= $line;
            } else {
                if (!is_null($current)) {
                    $headerlines[] = $current;
                }
                $current = $line;
            }
        }
        $headerlines[] = $current;

        $headers = [];
        // Check for validity.
        foreach ($headerlines as $line) {
            $pair = explode(':', $line, 2);
            // There must be a value, even if it is an empty string (No value indicates that the ':' was not present.
            if (!isset($pair[1])) {
                return false;
            }
            list($header, $value) = $pair;
            // Name must be of the correct syntax.
            if (preg_match(self::HTTP_HEADER_REGEX, $header) !== 1) {
                return false;
            }
            $headers[$header] = $value;
        }
        return $headers;
    }
}
