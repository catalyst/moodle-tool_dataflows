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
class tool_dataflows_connector_curl_test extends \advanced_testcase {

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
            'method' => 'get',
            'outputs' => [
                'result' => '${{ fromJSON(response.result) }}',
                'httpcode' => '${{ response.info.http_code }}',
                'connecttime' => '${{ response.info.connect_time }}',
                'totaltime' => '${{ response.info.total_time }}',
                'sizeupload' => '${{ response.info.size_upload }}',
            ],
        ]);
        $stepdef->name = 'connector';
        $stepdef->type = 'tool_dataflows\local\step\connector_curl';
        $dataflow->add_step($stepdef);
        ob_start();
        $engine = new engine($dataflow, false, false);
        $engine->execute();
        ob_get_clean();
        $variables = $engine->get_variables()['steps']->connector;
        // Result can be anything but for readability decoded to see vars.
        $result = $variables->result;
        $this->assertEquals($result->uuid, '3d188fbf-d0b7-4d4e-ae4d-4b5548df824e');

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
            'rawpostdata' => 'data=moodletest',
            'outputs' => [
                'result' => '${{ response.result }}',
                'httpcode' => '${{ response.info.http_code }}',
                'connecttime' => '${{ response.info.connect_time }}',
                'totaltime' => '${{ response.info.total_time }}',
                'sizeupload' => '${{ response.info.size_upload }}',
            ],
        ]);
        $dataflow->add_step($stepdef);
        ob_start();
        $engine = new engine($dataflow, false, false);
        $engine->execute();
        ob_get_clean();
        $variables = $engine->get_variables()['steps']->connector;

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
            'rawpostdata' => 'data=moodletest',
            'outputs' => [
                'result' => '${{ response.result }}',
                'httpcode' => '${{ response.info.http_code }}',
                'connecttime' => '${{ response.info.connect_time }}',
                'totaltime' => '${{ response.info.total_time }}',
                'sizeupload' => '${{ response.info.size_upload }}',
            ],
        ]);
        $dataflow->add_step($stepdef);
        ob_start();
        $engine = new engine($dataflow, false, false);
        $engine->execute();
        ob_get_clean();
        $variables = $engine->get_variables()['steps']->connector;

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
            }',
            'outputs' => ['curlcmd' => '${{ dbgcommand }}'],
        ]);
        $dataflow->add_step($stepdef);
        ob_start();
        $engine = new engine($dataflow, true, false);
        $engine->execute();
        ob_get_clean();

        $variables = $engine->get_variables()['steps']->connector;
        $expected = "curl -X POST {$testurl} -d '{
                \"name\": \"morpheus\",
                \"job\": \"leader\"
            }'";
        // Use trim here because it seems that some versions of Yaml put a EOL when dumping, and others don't.
        $this->assertEquals($expected, trim($variables->curlcmd));
        $this->assertEquals($expected, trim($variables->dbgcommand)); // Should also exist.

        // Test file writting.
        $tofile = "test.html";
        $stepdef->config = Yaml::dump([
            'curl' => $testgeturl,
            'destination' => $tofile,
            'headers' => '',
            'method' => 'get',
            'outputs' => [
                'httpcode' => '${{ response.httpcode }}',
                'destination' => '${{ response.destination }}',
            ],
        ]);
        $dataflow->add_step($stepdef);
        ob_start();
        $engine = new engine($dataflow, false, false);
        $engine->execute();
        ob_get_clean();
        $variables = $engine->get_variables()['steps']->connector;
        $destination = $variables->destination;
        $httpcode = $variables->httpcode;
        $this->assertFileExists($destination);
        unlink($destination);

        $variables = $engine->get_variables();
        $expressionlanguage = new ExpressionLanguage();
        // Checks that it can properly be referenced for future steps.
        $expressedvalue = $expressionlanguage->evaluate('steps.connector.config.curl', $variables);
        $this->assertEquals($testgeturl, $expressedvalue);
        $expressedvalue = $expressionlanguage->evaluate('steps.connector.destination', $variables);
        $this->assertEquals($destination, $expressedvalue);
        $expressedvalue = $expressionlanguage->evaluate('steps.connector.httpcode', $variables);
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
            'method' => 'get',
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

    /**
     * Tests run validation.
     *
     * @covers \tool_dataflows\local\step\connector_curl::validate_for_run
     */
    public function test_validate_for_run() {
        $absolutepath = '/var/tmp.json';
        $errormsg = get_string('path_invalid', 'tool_dataflows', $absolutepath, true);
        $config = [
            'curl' => 'https://some.place',
            'destination' => 'tmp.json',
            'headers' => '',
            'method' => 'get',
            'outputs' => [
                'httpcode' => '${{ response.httpcode }}',
                'destination' => '${{ response.destination }}',
            ],
        ];

        $dataflow = new dataflow();
        $dataflow->enabled = true;
        $dataflow->name = 'dataflow';
        $step = new step();
        $step->name = 'name';
        $step->type = 'tool_dataflows\local\step\connector_curl';
        $step->config = Yaml::dump($config);
        $dataflow->add_step($step);
        $steptype = $step->steptype;

        set_config('permitted_dirs', '', 'tool_dataflows');
        $this->assertTrue($steptype->validate_for_run());

        $config['destination'] = $absolutepath;
        $step->config = Yaml::dump($config);
        $this->assertEquals(['config_destination' => $errormsg], $steptype->validate_for_run());

        set_config('permitted_dirs', '/var', 'tool_dataflows');
        $this->assertTrue($steptype->validate_for_run());
    }

    /**
     * Tests the curl connector reports side effects correctly.
     *
     * @dataProvider has_side_effect_provider
     * @covers \tool_dataflows\local\step\connector_curl::has_side_effect
     * @param string $destination
     * @param string $method
     * @param bool $hassideeffects
     * @param bool $expected
     */
    public function test_has_side_effect(string $destination, string $method, bool $hassideeffects, bool $expected) {
        $config = [
            'curl' => 'https://some.dest',
            'destination' => $destination,
            'headers' => '',
            'method' => $method,
            'sideeffects' => $hassideeffects,
            'rawpostdata' => 'raw data',
            'outputs' => [
                'httpcode' => '${{ response.httpcode }}',
                'destination' => '${{ response.destination }}',
            ],
        ];

        $dataflow = new dataflow();
        $dataflow->name = 'dataflow';
        $dataflow->enabled = true;
        $dataflow->save();

        $step = new step();
        $step->name = 'somename';
        $step->type = 'tool_dataflows\local\step\connector_curl';
        $step->config = Yaml::dump($config);
        $dataflow->add_step($step);
        $steptype = $step->steptype;
        $this->assertEquals($expected, $steptype->has_side_effect());
    }

    /**
     * Provider function for test_has_side_effect().
     *
     * @return array[]
     */
    public function has_side_effect_provider(): array {
        return [
            ['my/in.txt', 'get', false, false],
            ['my/in.txt', 'head', false, false],
            ['file:///my/in.txt', 'get', false, true],
            ['my/in.txt', 'put', false, true],
            ['my/in.txt', 'patch', false, true],
            ['my/in.txt', 'post', false, true],
            ['my/in.txt', 'get', true, true],
            ['file:///my/in.txt', 'post', true, true],
        ];
    }

    /**
     * Extra tests for side effects.
     *
     * @covers \tool_dataflows\local\step\connector_curl::has_side_effect
     */
    public function test_has_side_effect_extra() {
        $config = [
            'curl' => 'https://some.dest',
            'destination' => 'my.in.txt',
            'headers' => '',
            'method' => 'get',
            'outputs' => [
                'httpcode' => '${{ response.httpcode }}',
                'destination' => '${{ response.destination }}',
            ],
        ];

        // Test for no configuration.
        $steptype = new connector_curl();
        $this->assertTrue($steptype->has_side_effect());

        $dataflow = new dataflow();
        $dataflow->name = 'dataflow';
        $dataflow->enabled = true;
        $dataflow->save();

        // Test for configuration with sideeffects unset.
        $step = new step();
        $step->name = 'somename';
        $step->type = 'tool_dataflows\local\step\connector_curl';
        $step->config = Yaml::dump($config);
        $dataflow->add_step($step);
        $steptype = $step->steptype;
        $this->assertFalse($steptype->has_side_effect());
    }
}
