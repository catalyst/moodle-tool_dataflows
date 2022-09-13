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

/**
 * Unit tests for the secret service
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_dataflows;

use Symfony\Component\Yaml\Yaml;
use tool_dataflows\application_trait;
use tool_dataflows\local\execution\engine;
use tool_dataflows\local\service\secret_service;
use tool_dataflows\local\step\connector_s3;
use tool_dataflows\step;

defined('MOODLE_INTERNAL') || die();

global $CFG;

// Include lib.php functions that aren't included automatically for Moodle 37- and below.
require_once(__DIR__ . '/application_trait.php');
require_once($CFG->libdir.'/tablelib.php');

/**
 * Child class of connector_s3 with enforced secrets used for testing
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tiny_connector_s3 extends connector_s3 {

    /**
     * Returns the name of this step.
     */
    public function get_name(): string {
        return 'stepname';
    }

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    public static function form_define_fields(): array {
        return [
            'bucket'            => ['type' => PARAM_TEXT],
            'region'            => ['type' => PARAM_TEXT],
            'key'               => ['type' => PARAM_TEXT],
            'secret'            => ['type' => PARAM_TEXT, 'secret' => true],
            'source'            => ['type' => PARAM_TEXT],
            'target'            => ['type' => PARAM_TEXT],
            'sourceremote'      => ['type' => PARAM_BOOL],
        ];
    }

    /**
     * This will not perform s3 operations and will do effectively nothing.
     *
     * @return bool Returns true if successful, false otherwise.
     */
    public function execute($input = null) {
        return true;
    }
}

/**
 * Secret service tests for tool_dataflows
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_secret_service_test extends \advanced_testcase {
    use application_trait;

    /** A secret that shouldn't be known */
    const SECRET = 'OHNOYOUWERENTSUPPOSEDTOSEEME';

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Dataflow creation helper function
     *
     * @return  array of the resulting dataflow and steps in the format [dataflow, steps]
     */
    public function create_dataflow() {
        $dataflow = new dataflow();
        $dataflow->name = 's3 copy';
        $dataflow->enabled = true;
        $dataflow->save();

        $steps = [];
        $reader = new step();
        $reader->name = 's3copy';
        $reader->type = tiny_connector_s3::class;
        $reader->config = Yaml::dump([
            'bucket' => 'bucket',
            'region' => 'region',
            'key' => 'SOMEKEY',
            'secret' => self::SECRET,
            'source' => 's3://test/source.csv',
            'target' => 's3://test/target.csv',
            'sourceremote' => true,
        ]);
        $dataflow->add_step($reader);
        $steps[$reader->id] = $reader;

        return [$dataflow, $steps];
    }

    /**
     * Tests generic redaction of values given a set of fields to redact
     *
     * @covers \tool_dataflows\local\service\secret_service::redact_fields
     */
    public function test_secrets_redaction_for_some_fields() {
        $secretservice = new secret_service;
        $fields = (object) [
            'name' => 'Bruce Banner',
            'secretkey' => 'iamalwaysangry',
        ];
        $keystoredact = ['secretkey'];
        $redactedfields = $secretservice->redact_fields($fields, $keystoredact);
        $this->assertEquals($fields->name, $redactedfields->name);
        $this->assertNotEquals($fields->secretkey, $redactedfields->secretkey);
        $this->assertEquals(secret_service::REDACTED_PLACEHOLDER, $redactedfields->secretkey);
    }

    /**
     * Ensures that dataflows with secrets do not log their secret value to the database (e.g. when persisting state)
     *
     * @covers \tool_dataflows\local\service\secret_service::redact_fields
     */
    public function test_secret_redaction_in_logs_and_during_run() {
        global $DB;

        [$dataflow] = $this->create_dataflow();

        // Execute.
        ob_start();
        $isdryrun = false;
        $engine = new engine($dataflow, $isdryrun);
        $engine->execute();
        $output = ob_get_clean();

        // Secret as a regex to check against.
        $regex = '/' . self::SECRET . '/';

        // Check run output.
        $this->compatible_assertDoesNotMatchRegularExpression($regex, $output);
        $this->compatible_assertStringContainsString(secret_service::REDACTED_PLACEHOLDER, $output);

        // Check recorded states.
        $records = $DB->get_records(run::TABLE, ['dataflowid' => $dataflow->id]);
        $this->assertCount(1, $records);
        $record = reset($records);
        $this->compatible_assertDoesNotMatchRegularExpression($regex, $record->startstate);
        $this->compatible_assertDoesNotMatchRegularExpression($regex, $record->currentstate);
        $this->compatible_assertDoesNotMatchRegularExpression($regex, $record->endstate);
        $this->compatible_assertStringContainsString(secret_service::REDACTED_PLACEHOLDER, $record->startstate);
        $this->compatible_assertStringContainsString(secret_service::REDACTED_PLACEHOLDER, $record->currentstate);
        $this->compatible_assertStringContainsString(secret_service::REDACTED_PLACEHOLDER, $record->endstate);
    }

    /**
     * Ensure export related functions do not export the secrets referenced in any way
     *
     * @covers \tool_dataflows\step::get_redacted_config
     * @covers \tool_dataflows\step::get_export_data
     * @covers \tool_dataflows\local\service\secret_service::redact_fields
     */
    public function test_secret_redaction_for_exports() {
        [$dataflow, $steps] = $this->create_dataflow();
        $exportdata = $dataflow->get_export_data();
        $this->assertNotEquals(self::SECRET, $exportdata['steps']['s3copy']['config']['secret']);
        $this->assertEquals(secret_service::REDACTED_PLACEHOLDER, $exportdata['steps']['s3copy']['config']['secret']);

        $stepexportdata = reset($steps)->get_export_data();
        $this->assertNotEquals(self::SECRET, $stepexportdata['config']['secret']);
        $this->assertEquals(secret_service::REDACTED_PLACEHOLDER, $stepexportdata['config']['secret']);
    }

    /**
     * Replicate the output of the table and ensure it doesn't contain the secret in any shape or form.
     *
     * @covers \tool_dataflows\steps_table::col_config
     * @covers \tool_dataflows\local\service\secret_service::redact_fields
     */
    public function test_secret_redaction_for_table_display() {
        [$dataflow, $steps] = $this->create_dataflow();

        // Set up table.
        $table = new steps_table('dataflows_table');
        $sqlfields = 'step.id,
                      usr.*,
                      step.*';
        $sqlfrom = '{tool_dataflows_steps} step
          LEFT JOIN {user} usr
                 ON usr.id = step.userid';
        $sqlwhere = 'dataflowid = :dataflowid';
        $sqlparams = ['dataflowid' => $dataflow->id];
        $table->set_sql($sqlfields, $sqlfrom, $sqlwhere, $sqlparams);
        $table->make_columns();
        $table->define_baseurl(new \moodle_url('/'));

        $columns = $table::COLUMNS;
        array_shift($columns); // Remove the name column as that uses the dot visual.
        $table->define_columns($columns);

        // Capture table output.
        ob_start();
        $table->set_page_size(1);
        $table->build();
        $output = ob_get_clean();
        $regex = '/' . self::SECRET . '/';

        // Ensure the table output contained the relevant step information
        // (which reassures the step data was indeed outputted).
        foreach ($steps as $step) {
            $this->compatible_assertStringContainsString('s3://test/source.csv', $output);
            $this->compatible_assertStringContainsString($step->alias, $output);
        }

        // Check if the output contains the secrets or not.
        $this->compatible_assertDoesNotMatchRegularExpression($regex, $output);
        $this->compatible_assertStringContainsString(secret_service::REDACTED_PLACEHOLDER, $output);
    }
}
