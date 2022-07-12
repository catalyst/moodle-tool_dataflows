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

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Yaml\Yaml;
use tool_dataflows\local\step\connector_curl;
use tool_dataflows\local\execution\engine;
use tool_dataflows\step;
use tool_dataflows\dataflow;

/**
 * Unit test for the curl connector step.
 *
 * @package   tool_dataflows
 * @author    Ghaly Marc-Alexandre <marc-alexandreghaly@catalyst-ca.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_curl_connector_test extends \advanced_testcase {
    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Tests the execute() function.
     *
     * @covers \tool_dataflows\local\step\connector_curl::execute
     */
    public function test_execute() {
        global $CFG;

        $connectorcurl = new connector_curl();
        $testgeturl = $this->getExternalTestFileUrl('/h5puuid.json');

        $stepdef = new step();
        $dataflow = new dataflow();
        $dataflow->name = 'connector-step';
        $dataflow->enabled = true;
        $dataflow->save();
        // Tests get method.
        $stepdef->config = Yaml::dump([
            'curl' => $testgeturl,
            'destination' => '',
            'headers' => '',
            'method' => 'get'
        ]);
        $stepdef->name = 'connector';
        $stepdef->type = 'tool_dataflows\local\step\connector_curl';
        $dataflow->add_step($stepdef);
        ob_start();
        $engine = new engine($dataflow, false, false);
        $engine->execute();
        ob_get_clean();
        $variables = $engine->get_variables()['steps']->connector->config;
        // Result can be anything but for readability decoded to see vars.
        $result = json_decode($variables->result, true);;
        $this->assertEquals($result['uuid'], '3d188fbf-d0b7-4d4e-ae4d-4b5548df824e');

        $this->assertEquals($variables->httpcode, 200);
        $this->assertObjectHasAttribute('connecttime', $variables);
        $this->assertObjectHasAttribute('totaltime', $variables);
        $this->assertObjectHasAttribute('sizeupload', $variables);

        $testurl = $this->getExternalTestFileUrl('/test_post.php');

        // Tests post method.
        $stepdef->config = Yaml::dump([
            'curl' => $testurl,
            'destination' => '',
            'headers' => '',
            'method' => 'post',
            'rawpostdata' => 'data=moodletest'
        ]);
        $dataflow->add_step($stepdef);
        ob_start();
        $engine = new engine($dataflow, false, false);
        $engine->execute();
        ob_get_clean();
        $variables = $engine->get_variables()['steps']->connector->config;

        $this->assertEmpty($variables->result);
        $this->assertEquals($variables->httpcode, 200);
        $this->assertObjectHasAttribute('connecttime', $variables);
        $this->assertObjectHasAttribute('totaltime', $variables);
        $this->assertObjectHasAttribute('sizeupload', $variables);

        // Tests put method.
        $stepdef->config = Yaml::dump([
            'curl' => $testurl,
            'destination' => '',
            'headers' => '',
            'method' => 'put',
            'rawpostdata' => 'data=moodletest'
        ]);
        $dataflow->add_step($stepdef);
        ob_start();
        $engine = new engine($dataflow, false, false);
        $engine->execute();
        ob_get_clean();
        $variables = $engine->get_variables()['steps']->connector->config;

        $this->assertEmpty($variables->result);
        $this->assertEquals($variables->httpcode, 200);
        $this->assertObjectHasAttribute('connecttime', $variables);
        $this->assertObjectHasAttribute('totaltime', $variables);
        $this->assertObjectHasAttribute('sizeupload', $variables);

        // Tests debug command when dry run.
        $stepdef->config = Yaml::dump([
            'curl' => $testurl,
            'destination' => '',
            'headers' => '',
            'method' => 'post',
            'rawpostdata' => '{
                "name": "morpheus",
                "job": "leader"
            }'
        ]);
        $dataflow->add_step($stepdef);
        ob_start();
        $engine = new engine($dataflow, true, false);
        $engine->execute();
        ob_get_clean();
        $variables = $engine->get_variables()['steps']->connector->config;
        $this->assertObjectNotHasAttribute('result', $variables);
        $this->assertEquals($variables->dbgcommand, "curl -X POST {$testurl} -d '{
                \"name\": \"morpheus\",
                \"job\": \"leader\"
            }'\n");

        // Test file writting.
        $tofile = "test.html";
        $stepdef->config = Yaml::dump([
            'curl' => $testgeturl,
            'destination' => $tofile,
            'headers' => '',
            'method' => 'get'
        ]);
        $dataflow->add_step($stepdef);
        ob_start();
        $engine = new engine($dataflow, false, false);
        $engine->execute();
        ob_get_clean();
        $variables = $engine->get_variables()['steps']->connector->config;
        $destination = $variables->destination;
        $httpcode = $variables->httpcode;
        $this->assertFileExists($destination);
        unlink($destination);

        $variables = $engine->get_variables();
        $expressionlanguage = new ExpressionLanguage();
        // Checks that it can properly be referenced for future steps.
        $expressedvalue = $expressionlanguage->evaluate('steps.connector.config.curl', $variables);
        $this->assertEquals($testgeturl, $expressedvalue);
        $expressedvalue = $expressionlanguage->evaluate('steps.connector.config.destination', $variables);
        $this->assertEquals($destination, $expressedvalue);
        $expressedvalue = $expressionlanguage->evaluate('steps.connector.config.httpcode', $variables);
        $this->assertEquals($httpcode, $expressedvalue);
    }

    /**
     * Test validate_config().
     *
     * @covers \tool_dataflows\local\step\connector_curl::validate_config
     * @throws \coding_exception
     */
    public function test_validate_config() {
        // Test valid configuration.
        $config = (object) [
            'curl' => $this->getExternalTestFileUrl('/h5puuid.json'),
            'method' => 'get'
        ];
        $connectorcurl = new connector_curl();
        $this->assertTrue($connectorcurl->validate_config($config));

        // Test that whenever post/put is selected postdata exists.
        $config->method = 'post';
        $config->rawpostdata = '';
        $result = $connectorcurl->validate_config($config);
        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('config_rawpostdata', $result);

        // Test cURL always exists.
        unset($config->curl);
        $result = $connectorcurl->validate_config($config);
        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('config_curl', $result);
    }
}
