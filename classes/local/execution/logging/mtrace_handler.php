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

namespace tool_dataflows\local\execution\logging;

use Monolog\Handler\AbstractHandler;
use Monolog\Handler\AbstractProcessingHandler;

/**
 * An environment for logging information about dataflow execution.
 *
 * @package   tool_dataflows
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright Catalyst IT, 2023
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mtrace_handler extends AbstractProcessingHandler {

    /**
     * Default handler for Moodle.
     *
     * @param array $record the log record
     **/
    public function handle(array $record): bool {
        if ($this->isHandling($record)) {
            $record['formatted'] = trim($this->getFormatter()->format($record));
            $this->write($record);
            return true;
        }
        return $this->handler->handle($record);
    }

    /**
     * Writes the record down to the log of the implementing handler
     *
     * @param  array $record
     * @return void
     */
    protected function write(array $record): void {
        mtrace($record['formatted']);
    }
}
