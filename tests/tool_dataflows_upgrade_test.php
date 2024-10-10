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
use Symfony\Component\Yaml\Yaml;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__.'/../db/upgrade.php');

/**
 * Unit test for the dataflows upgrade steps.
 *
 * @package   tool_dataflows
 * @author    Peter Burnett <peterburnett@catalyst-au.net>
 * @copyright 2024, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_upgrade_test extends \advanced_testcase {

    /**
     * Test config upgrade helper.
     *
     * @covers \tool_dataflows\local\step\trigger_event::validate_config
     */
    public function test_step_config_helper() {
        global $DB;
        $this->resetAfterTest();

        $dataflow = new dataflow();
        $dataflow->name = 'testupgrade';
        $dataflow->enabled = true;
        $dataflow->save();

        $step = new step();
        $step->name = 'cron';
        $step->type = 'tool_dataflows\local\step\trigger_cron';
        $config = [
            'minute' => '*',
            'hour' => '*',
            'day' => '*',
            'month' => '*',
            'dayofweek' => '*',
            'retryinterval' => '0',
            'retrycount' => '0',
        ];
        $step->config = Yaml::dump($config);
        $dataflow->add_step($step);

        // Regular Additional config.
        $existingstep = $DB->get_record('tool_dataflows_steps', ['type' => $step->type]);
        $this->assertEquals($config, Yaml::parse($existingstep->config));

        $config['newstep'] = 'upgrade';
        $newfields = ['newstep' => 'upgrade'];
        xmldb_tool_dataflows_step_config_helper($step->type, $newfields);
        $updatedrec = $DB->get_record('tool_dataflows_steps', ['type' => $step->type]);
        $this->assertEquals($config, Yaml::parse($updatedrec->config));
    }
}
