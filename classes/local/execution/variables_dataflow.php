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

namespace tool_dataflows\local\execution;

use Symfony\Component\Yaml\Yaml;
use tool_dataflows\dataflow;
use tool_dataflows\helper;
use tool_dataflows\parser;
use tool_dataflows\step;

/**
 * Class for storing and managing the variables tree for a dataflow.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class variables_dataflow extends variables_base {
    /** @var variables_root The variable root. */
    private $root;

    /**
     * Construct a variables object for a dataflow.
     *
     * @param dataflow $dataflow
     */
    public function __construct(dataflow $dataflow, variables_root $root) {
        parent::__construct();
        $this->root = $root;

        // Define the structure of the dataflow variables tree.
        $dataflowvars = $dataflow->get_export_data(false);
        unset($dataflowvars->steps);
        $this->sourcetree->name = $dataflow->name;
        $this->sourcetree->vars = $dataflow->get_raw_vars();
        $this->sourcetree->config = new \stdClass();
        $this->sourcetree->config->enabled = $dataflow->enabled;
        $this->sourcetree->config->concurrencyenabled = $dataflow->concurrencyenabled;
        $this->sourcetree->states = new \stdClass();
        // Initialise all the possible states.
        $this->sourcetree->states = (object) array_combine(
            // All the labels.
            engine::STATUS_LABELS,
            // Filled with null.
            array_fill(0, count(engine::STATUS_LABELS), null)
        );
    }

    /**
     * Sets a variable in the tree.
     *
     * @param string $name The name of the variable in dot format, relative to this tree's root (e.g. 'config.destination').
     * @param mixed $value.
     */
    public function set(string $name, $value) {
        parent::set($name, $value);
        $this->root->invalidate();
    }
}
