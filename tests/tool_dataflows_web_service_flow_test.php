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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir.'/externallib.php');

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Yaml\Yaml;
use tool_dataflows\dataflow;
use tool_dataflows\local\execution\engine;
use tool_dataflows\local\step\flow_web_service;
use tool_dataflows\step;

/**
 * Unit test for the web service flow step.
 *
 * @package   tool_dataflows
 * @author    Ghaly Marc-Alexandre <marc-alexandreghaly@catalyst-ca.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_web_service_flow_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Tests the execute() function.
     *
     * @covers \tool_dataflows\local\step\flow_web_service::execute
     */
    public function test_execute() {
        global $DB;
        $originalusercount = $DB->count_records('user');

        $dataflow = new dataflow();
        $dataflow->name = 'flow-webservice-step';
        $dataflow->enabled = true;
        $dataflow->save();

        $curlstep = new step();
        // Curl step property setup.
        $curlstep->config = Yaml::dump([
            'curl' => $this->getExternalTestFileUrl('/h5puuid.json'),
            'destination' => 'test.html',
            'headers' => '',
            'method' => 'get',
        ]);
        $curlstep->name = 'connector';
        $curlstep->type = 'tool_dataflows\local\step\connector_curl';
        $dataflow->add_step($curlstep);

        // Json read.
        $jsonstep = new step();
        $jsonstep->name = 'reader';
        $jsonstep->type = 'tool_dataflows\local\step\reader_json';
        $jsonstep->config = Yaml::dump([
            'pathtojson' => '${{ steps.connector.config.destination }}',
            'arrayexpression' => '',
            'arraysortexpression' => '',
        ]);
        $jsonstep->depends_on([$curlstep]);
        $dataflow->add_step($jsonstep);

        // Webservice step properties setup.
        $wsflowstep = new step();
        $jsonexample = [
            'users' => [
                0 => [
                    'username' => 'john1234',
                    'firstname' => 'john',
                    'lastname' => 'doe',
                    'createpassword' => true,
                    'email' => 'john@doe.ca',
                    'firstnamephonetic' => '',
                    'lastnamephonetic' => '',
                    'middlename' => '',
                    'alternatename' => '',
                ],
            ],
        ];
        $wsflowstep->config = Yaml::dump([
            'webservice' => 'core_user_create_users',
            'user' => 'admin',
            'parameters' => json_encode($jsonexample),
            'failure' => 'abortflow',
        ]);
        $wsflowstep->name = 'webflow';
        $wsflowstep->type = 'tool_dataflows\local\step\flow_web_service';
        $wsflowstep->depends_on([$jsonstep]);
        $dataflow->add_step($wsflowstep);

        $writer = new step();
        $writer->name = 'writer';
        $writer->type = 'tool_dataflows\local\step\writer_stream';
        $writer->config = Yaml::dump(['format' => 'json', 'streamname' => 'test.txt']);
        $writer->depends_on([$wsflowstep]);
        $dataflow->add_step($writer);
        ob_start();
        $engine = new engine($dataflow, false, false);
        $engine->execute();
        ob_get_clean();

        // Ensure webservice has properly created another user calling the WS.
        $usercount = $DB->count_records('user');
        $this->assertEquals($originalusercount + 1, $usercount);

        $newdataflow = new dataflow();
        $newdataflow->name = 'flow-webservice-step';
        $newdataflow->enabled = true;
        $newdataflow->save();

        // Re-add curl step.
        $curlstep->config = Yaml::dump([
            'curl' => $this->getExternalTestFileUrl('/h5pcontenttypes.json'),
            'destination' => 'test1.html',
            'headers' => '',
            'method' => 'get',
        ]);
        $newdataflow->add_step($curlstep);

        // Json read step new config.
        $jsonstep = new step();
        $jsonstep->name = 'reader';
        $jsonstep->type = 'tool_dataflows\local\step\reader_json';
        $jsonstep->config = Yaml::dump([
            'pathtojson' => '${{ steps.connector.config.destination }}',
            'arrayexpression' => 'contentTypes',
            'arraysortexpression' => '',
        ]);

        $jsonstep->depends_on([$curlstep]);
        $newdataflow->add_step($jsonstep);
        // Update wsflow.
        $jsonexample = [
            'users' => [
                0 => [
                    'username' => 'john1234567',
                    'createpassword' => true,
                    'firstname' => '${{ data.owner }}',
                    'lastname' => 'doe',
                    'email' => 'john@dodoe.ca',
                    'firstnamephonetic' => '',
                    'lastnamephonetic' => '',
                    'middlename' => '',
                    'alternatename' => '',
                ],
            ],
        ];

        $wsflowstep->config = Yaml::dump([
            'webservice' => 'core_user_create_users',
            'user' => 'admin',
            'parameters' => json_encode($jsonexample),
            'failure' => 'abortflow',
        ]);
        $wsflowstep->depends_on([$jsonstep]);
        $newdataflow->add_step($wsflowstep);
        $writer->depends_on([$wsflowstep]);
        $newdataflow->add_step($writer);

        ob_start();
        $engine = new engine($newdataflow, false, false);
        $engine->execute();
        ob_get_clean();

        $lastcount = $DB->count_records('user');
        $this->assertEquals($originalusercount + 2, $lastcount);
        $dataowner = $DB->get_field('user', 'firstname', ['username' => 'john1234567']);
        $this->assertEquals('joubel', $dataowner);
    }

    /**
     * Test validate_config().
     *
     * @covers \tool_dataflows\local\step\flow_web_service::validate_config
     * @throws \coding_exception
     */
    public function test_validate_config() {
        $user = $this->getDataGenerator()->create_user(['deleted' => 1]);
        $this->setUser($user);

        // Test valid configuration.
        $config = (object) [
            'webservice' => 'core_course_get_courses_by_field',
            'user' => 'admin',
            'failure' => 'abortstep',
        ];
        $flowwebservice = new flow_web_service();
        $this->assertTrue($flowwebservice->validate_config($config));

        // Tests that deleted user cannot use WS.
        $config->user = $user->username;
        $result = $flowwebservice->validate_config($config);
        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('config_user', $result);
        $config->user = 'admin';

        // Test webservice is always passed.
        $config->webservice = '';
        $result = $flowwebservice->validate_config($config);
        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('config_webservice', $result);

        // Test user is always passed.
        $config->webservice = 'core_course_get_courses_by_field';
        $config->user = '';
        $result = $flowwebservice->validate_config($config);
        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('config_user', $result);
    }
}
