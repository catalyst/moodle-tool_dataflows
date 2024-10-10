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

use Symfony\Bridge\Monolog\Logger;

/**
 * Log trait
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2023
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait log_trait {

    /**
     * Logging levels from syslog protocol defined in RFC 5424
     *
     * @var array Logging levels
     */
    protected static $levels = [
        Logger::DEBUG     => 'DEBUG',
        Logger::INFO      => 'INFO',
        Logger::NOTICE    => 'NOTICE',
        Logger::WARNING   => 'WARNING',
        Logger::ERROR     => 'ERROR',
        Logger::CRITICAL  => 'CRITICAL',
        Logger::ALERT     => 'ALERT',
        Logger::EMERGENCY => 'EMERGENCY',
    ];

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
            'level' => ['type' => PARAM_TEXT, 'required' => true],
            'message' => ['type' => PARAM_TEXT, 'required' => true],
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
        $options = self::$levels;
        $mform->addElement('select', 'config_level', get_string('log:level', 'tool_dataflows'), $options);
        $mform->addElement('static', 'config_level_help', '', get_string('log:level_help', 'tool_dataflows'));
        $mform->addElement('text', 'config_message', get_string('log:message', 'tool_dataflows'), ['style' => 'width: 100%']);
    }

    /**
     * Logs a message.
     *
     * If the message is empty, it will not log anything, which is useful for conditional logging.
     *
     * @param mixed|null $input
     * @return mixed
     */
    public function execute($input = null) {
        $variables = $this->get_variables();
        $config = $variables->get('config');
        $message = $variables->evaluate($config->message);

        // Call the log method with the relevant levels, if a message is supplied.
        if ($message !== '') {
            $this->log->log($config->level, $message, (array) $input);
        }

        return $input;
    }
}
