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
use tool_dataflows\exportable;
use tool_dataflows\helper;
use tool_dataflows\local\service\step_service;
use tool_dataflows\local\step\flow_cap;
use tool_dataflows\run;

/**
 * Executes a dataflow.
 *
 * Once an engine has been created, it can be executed in one action, or stepped through.
 * Call execute() to run the engine completely through, or execute_step() to execute one
 * step.
 * Regardless of the method of execution, you will need to check for an aborted status.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class engine {
    use exportable;

    /**
     * Defines the execution status used by the engine and the engine steps.
     */
    /** @var int Just created. */
    const STATUS_NEW = 0;
    /** @var int Initialised. Ready to run. */
    const STATUS_INITIALISED = 1;
    /** @var int Connector cannot proceed, waiting on upstreams. */
    const STATUS_BLOCKED = 2;
    /** @var int Flow cannot proceed, waiting on upstreams. */
    const STATUS_WAITING = 3;
    /** @var int Connector is currently processing. */
    const STATUS_PROCESSING = 4;
    /** @var int Flow is currently active */
    const STATUS_FLOWING = 5;
    /** @var int Step has finished activity. Downstreams can proceed. */
    const STATUS_FINISHED = 6;
    /** @var int Step has been cancelled. Downstreams may also be cancelled. */
    const STATUS_CANCELLED = 7;
    /** @var int The dataflow execution has been aborted. Not able to finish */
    const STATUS_ABORTED = 8;
    /** @var int Step/dataflow has completely finished. */
    const STATUS_FINALISED = 9;

    /** @var string[] Maps statuses to string indexes. */
    const STATUS_LABELS = [
        self::STATUS_NEW => 'new',
        self::STATUS_INITIALISED => 'initialised',
        self::STATUS_BLOCKED => 'blocked',
        self::STATUS_WAITING => 'waiting',
        self::STATUS_PROCESSING => 'processing',
        self::STATUS_FLOWING => 'flowing',
        self::STATUS_FINISHED => 'finished',
        self::STATUS_CANCELLED => 'cancelled',
        self::STATUS_ABORTED => 'aborted',
        self::STATUS_FINALISED => 'finalised',
    ];

    /** @var int[] Statuses that represent the termination of a run. */
    const STATUS_TERMINATORS = [
        self::STATUS_ABORTED,
        self::STATUS_FINALISED,
        self::STATUS_CANCELLED,
    ];

    /** @var array The queue of steps to be given a run. */
    public $queue;

    /** @var dataflow The dataflow defined by the user. */
    protected $dataflow;

    /** @var run The run linked to this engine's instance. */
    protected $run;

    /** @var array The engine steps in the dataflow. */
    protected $enginesteps = [];

    /** @var array The steps that have no outputflows. */
    protected $sinks = [];

    /** @var array Caps for the flow blocks. */
    protected $flowcaps = [];

    /** @var int The status of the execution. */
    protected $status;

    /** @var \Throwable The exception generated when abort occurred. */
    protected $exception = null;

    /** @var bool True if executing a dry run. */
    protected $isdryrun = false;

    /** @var bool True if executing via automation. */
    protected $automated = true;

    /** @var string Scratch directory for temporary files. */
    protected $scratchdir = null;

    /** @var \core\lock\lock|false Lock for the dataflow. Sometimes, only one dataflow of each def should be running at a time. */
    protected $lock = false;

    /** @var \core\lock\lock_factory Factory to produce locks. */
    protected static $lockfactory = null;

    /** @var bool Has this engine been blocked by a lock. */
    protected $blocked = false;

    /**
     * Constructs the engine.
     *
     * @param dataflow $dataflow The dataflow to be executed, as defined in the editor.
     * @param bool $isdryrun global dryrun exection flag.
     * @param bool $automated Execution of this run was an automated trigger.
     */
    public function __construct(dataflow $dataflow, bool $isdryrun = false, $automated = true) {
        $this->dataflow = $dataflow;
        $this->dataflow->set_engine($this);
        $this->isdryrun = $isdryrun;
        $this->automated = $automated;

        $this->set_status(self::STATUS_NEW);

        // Refuse to run if dataflow is not enabled.
        if (!($dataflow->enabled || !$automated || $isdryrun)) {
            $this->abort(new \moodle_exception('running_disabled_dataflow', 'tool_dataflows'));
            return;
        }

        // Refuse to run if dataflow is invalid.
        if ($dataflow->validate_dataflow() !== true) {
            $this->abort(new \moodle_exception('running_invalid_dataflow', 'tool_dataflows'));
            return;
        }

        $this->scratchdir = make_request_directory();

        // Create engine steps for each step in the dataflow.
        foreach ($dataflow->steps as $stepdef) {
            $classname = $stepdef->type;
            $steptype = new $classname($stepdef, $this);
            $stepdef->steptype = $steptype;
            $this->enginesteps[$stepdef->id] = $steptype->get_engine_step();
        }

        // Create the links between engine step.
        foreach ($this->enginesteps as $id => $enginestep) {
            $deps = $enginestep->stepdef->dependencies();
            foreach ($deps as $dep) {
                $depstep = $this->enginesteps[$dep->id];
                $enginestep->upstreams[$dep->id] = $depstep;
                $depstep->downstreams[$id] = $enginestep;
            }
        }

        // Find the sinks.
        foreach ($this->enginesteps as $enginestep) {
            if (count($enginestep->downstreams) == 0) {
                $this->sinks[] = $enginestep;
            }
        }

        // Find the flow blocks.
        $this->create_flow_caps();

        $dataflow->rebuild_variables();
    }

    /**
     * Destructor function.
     * Releases any locks that are still held.
     */
    public function __destruct() {
        $this->release_lock();
    }

    /**
     * Tries to obtain a lock for this dataflow
     *
     * @param int $timeout
     * @return \core\lock\lock|false.
     */
    public function get_lock($timeout = 0) {
        global $DB;

        $lockfactory = \core\lock\lock_config::get_lock_factory('tool_dataflows_engine');
        $this->lock = $lockfactory->get_lock('tool_dataflows_engine_' . $this->dataflow->id, $timeout);
        if ($this->lock) {
            $DB->insert_record(
                'tool_dataflows_lock_metadata',
                [
                    'dataflowid' => $this->dataflow->id,
                    'timestamp' => time(),
                    'processid' => getmypid(),
                ]
            );
        }
        return $this->lock;
    }

    /**
     * Release any locks that have been previously obtained.
     *
     * @throws \dml_exception
     */
    public function release_lock() {
        global $DB;

        if ($this->lock) {
            $this->lock->release();
            $this->lock = false;
            $DB->delete_records('tool_dataflows_lock_metadata', ['dataflowid' => $this->dataflow->id]);
        }
    }

    /**
     * Gets the metadata associated with the dataflow lock.
     *
     * @param int $dataflowid
     * @return false|mixed|\stdClass
     * @throws \dml_exception
     */
    public static function get_lock_metadata(int $dataflowid) {
        global $DB;

        return $DB->get_record('tool_dataflows_lock_metadata', ['dataflowid' => $dataflowid]);
    }

    /**
     * Returns if the execution was unable to obtain a lock.
     *
     * @return object|false Lock metadata, for false is not blocked.
     */
    public function is_blocked() {
        if ($this->blocked) {
            return self::get_lock_metadata($this->dataflow->id);
        } else {
            return false;
        }
    }

    /**
     * Returns whether the run has been aborted.
     *
     * @return bool
     */
    public function is_aborted(): bool {
        return $this->status == self::STATUS_ABORTED;
    }

    /**
     * Initialises the dataflow.
     */
    public function initialise() {
        $this->status_check(self::STATUS_NEW);

        if (!$this->dataflow->is_concurrency_enabled()) {
            if (!$this->get_lock()) {
                $metadata = self::get_lock_metadata($this->dataflow->id);
                $this->log('Execution blocked by a lock. Time: ' . userdate($metadata->timestamp) .
                        ", Process ID: {$metadata->processid}");
                $this->blocked = true;
                return;
            }
        }
        try {
            $this->blocked = false;
            $this->status_check(self::STATUS_NEW);

            foreach ($this->enginesteps as $enginestep) {
                $enginestep->initialise();
            }

            // Add sinks to the execution queue.
            $this->queue = $this->sinks;
            $this->set_status(self::STATUS_INITIALISED);
        } catch (\Throwable $thrown) {
            $this->abort($thrown);
        }
        // Register shutdown handler - if request is ended by client, abort and finalise flow.
        \core_shutdown_manager::register_function(function (){
            // If the script has stopped and flow is not finalised then abort.
            if (!in_array($this->status, [self::STATUS_FINALISED, self::STATUS_ABORTED])) {
                $this->set_status(self::STATUS_ABORTED);
                $this->run->finalise($this->status, $this->export());
            }
        });
    }

    /**
     * Finds the steps that are sinks for their respective flow blocks and create flow caps for them.
     */
    protected function create_flow_caps() {
        // TODO Currently assumes flow blocks have no branches.
        $flowcapnumber = 0;
        $flowcaps = [];
        foreach ($this->enginesteps as $enginestep) {
            if ($enginestep->is_flow() && $this->count_flow_steps($enginestep->downstreams) == 0) {
                $step = new \tool_dataflows\step();
                $steptype = new flow_cap($step, $this);
                $flowcapnumber++;
                $step->name = "flowcap-{$flowcapnumber}";
                $flowcap = $steptype->get_engine_step();
                $flowcaps[] = $flowcap;
                $enginestep->downstreams['puller'] = $flowcap;
                $flowcap->upstreams[$enginestep->id] = $enginestep;
            }
        }

        // For all the flow caps created, see if they are part of the same flow
        // group, and if so, merge them. The flow cap will be in charge of
        // pulling from all the branches within the same flow. The logic for
        // flow caps would be similar to the one for the flow merge step. (just
        // this one is invisible).
        $stepservice = new step_service;
        foreach ($flowcaps as $key => $flowcap) {
            foreach ($flowcaps as $flowcap2) {
                // Skip matching flow cap.
                if ($flowcap->name === $flowcap2->name) {
                    continue;
                }

                $ispartofsameflow = $stepservice->is_part_of_same_flow_group(
                    current($flowcap->upstreams),
                    current($flowcap2->upstreams)
                );

                if ($ispartofsameflow) {
                    // Goes through the upstreams provided and sets the downstream puller, to the flowcap provided.
                    $stepservice->consolidate_flowcaps($flowcap, $flowcap2->upstreams);

                    // Remove the flow cap that was merged into the first one. No longer required.
                    unset($flowcap2);
                }
            }
        }

        $this->flowcaps = $flowcaps;
    }

    /**
     * Returns the number of flow steps in the step list.
     *
     * @param array $list
     * @return int
     */
    protected function count_flow_steps(array $list): int {
        $count = 0;
        foreach ($list as $item) {
            if ($item->is_flow()) {
                ++$count;
            }
        }
        return $count;
    }

    /**
     * Runs the data flow as a single action. This function initialises the dataflow,
     * runs the dataflow, and finalises it.
     */
    public function execute() {
        $this->initialise();
        if ($this->is_blocked() || $this->status == self::STATUS_ABORTED) {
            return;
        }

        $this->status_check(self::STATUS_INITIALISED);

        // Initalise a new run (only for non-dry runs). This should only be
        // created when the engine is executed.
        if (!$this->isdryrun) {
            $this->run = new run;
            $this->run->dataflowid = $this->dataflow->id;
            $this->run->initialise($this->status, $this->export());
        }

        while ($this->status != self::STATUS_FINISHED) {
            $this->execute_step();

            // A dataflow run should store the state of the run during
            // execution. By the end, the final state should be the same as (or
            // very very similar to) the current state of the run.
            if (isset($this->run)) {
                // Stores a dump of the current engine state.
                $this->run->snapshot($this->status, $this->export());
            }

            if ($this->status == self::STATUS_ABORTED) {
                if (isset($this->run)) {
                    $this->dataflow->save_config_version();
                    $this->run->finalise($this->status, $this->export());
                }
                return;
            }
        }

        $this->finalise();
    }

    /**
     * Adds an engine step to the queues for processing.
     *
     * @param engine_step $step
     */
    public function add_to_queue(engine_step $step) {
        if (!in_array($step, $this->enginesteps, true) && !in_array($step, $this->flowcaps, true)) {
            throw new \moodle_exception('engine:bad_step', 'tool_dataflows', $step->name);
        }
        $this->queue[] = $step;
    }

    /**
     * Executes a single step. Must be initialised prior to calling. Does not finalise.
     */
    public function execute_step() {
        if ($this->status === self::STATUS_INITIALISED) {
            $this->set_status(self::STATUS_PROCESSING);
        }
        if ($this->status !== self::STATUS_PROCESSING) {
            throw new \moodle_exception('bad_status', 'tool_dataflows');
        }
        if (count($this->queue) == 0) {
            $this->set_status(self::STATUS_FINISHED);
        } else {
            $currentstep = array_shift($this->queue);
            $currentstep->go();
        }
    }

    /**
     * Finalises the execution. Any remaining resources should be released.
     */
    public function finalise() {
        try {
            $this->status_check(self::STATUS_FINISHED);
            foreach ($this->enginesteps as $enginestep) {
                $enginestep->finalise();
            }

            // Stores a dump of the current engine state as the finalstate of the run.
            if (isset($this->run)) {
                $this->dataflow->save_config_version();
                $this->run->finalise($this->status, $this->export());
            }

            $this->set_status(self::STATUS_FINALISED);
            $this->release_lock();
        } catch (\Throwable $thrown) {
            $this->abort($thrown);
        }
    }

    /**
     * Stops execution immediately. Gracefully stops all processors and iterators.
     *
     * You should always call this function to abort an execution.
     *
     * @param \Throwable|null $reason
     * @throws \Throwable
     */
    public function abort(?\Throwable $reason = null) {
        if (isset($reason)) {
            $message = $reason->getMessage();
        } else {
            $message = '';
        }
        $this->log('Aborted: ' . $message);
        $this->exception = $reason;
        foreach ($this->enginesteps as $enginestep) {
            $enginestep->abort();
        }
        foreach ($this->flowcaps as $enginestep) {
            $enginestep->abort();
        }
        $this->queue = [];
        $this->set_status(self::STATUS_ABORTED);
        $this->release_lock();

        // TODO: We may want to make this the responsibility of the caller.
        if (isset($reason)) {
            throw $reason;
        }
    }

    /**
     * Emit a log message.
     *
     * @param string $message
     */
    public function log(string $message) {
        (new logging_context($this))->log($message);
    }

    /**
     * PHP getter.
     *
     * @param  string $parameter
     */
    public function __get($parameter) {
        switch ($parameter) {
            case 'dataflow':
            case 'exception':
            case 'isdryrun':
            case 'run':
            case 'status':
            case 'scratchdir':
                return $this->$parameter;
            case 'name':
                return $this->dataflow->name;
            default:
                throw new \moodle_exception(
                    'bad_parameter',
                    'tool_dataflows',
                    '',
                    ['parameter' => $parameter, 'classname' => self::class]);
        }
    }

    /**
     * Returns an array with all the variables available through the dataflow engine.
     *
     * Note: ideally, you could check a value set in another step via this
     * function, and returning the dataflow->variables might not always be the
     * correct choice, thus the need for this function should things be updated.
     *
     * @return  array
     */
    public function get_variables(): array {
        return $this->dataflow->variables;
    }

    /**
     * Sets a variable at the dataflow level
     *
     * Almost 'anything goes' here. Since the dataflow itself doesn't have any
     * particular restriction on config. Anything value can be set here and
     * referenced from other steps.
     *
     * TODO: add instance support.
     *
     * @param      string $name of the field
     * @param      mixed $value
     */
    public function set_dataflow_var($name, $value) {
        // Check if this field can be updated or not, e.g. if this was forced in config, it should not be updatable.
        // TODO: implement.

        $dataflow = $this->dataflow;
        $previous = $dataflow->vars->{$name} ?? '';
        $this->log("Setting dataflow '$name' to '$value' (from '{$previous}')");
        $this->dataflow->set_var($name, $value);

        // Persists the variable to the dataflow config.
        // NOTE: This is skipped during a dry-run. Variables 'should' still be accessible as per normal.
        if (!$this->isdryrun) {
            $this->dataflow->save();
        }
    }

    /**
     * Sets a variable at the global plugin level
     *
     * Values here are - similar to the dataflow and step scope - set against a
     * config field. This however is stored via set_config and there is no
     * instance only support.
     *
     * @param      string $name of the field
     * @param      mixed $value
     */
    public function set_global_var($name, $value) {
        // Grabs the current config.
        $vars = get_config('tool_dataflows', 'global_vars');
        $vars = Yaml::parse($vars, Yaml::PARSE_OBJECT_FOR_MAP) ?: new \stdClass;

        // Updates the field in question.
        $previous = $vars->{$name} ?? '';
        $vars->{$name} = $value;

        // Updates the stored config.
        $yaml = Yaml::dump(
            (array) $vars,
            helper::YAML_DUMP_INLINE_LEVEL,
            helper::YAML_DUMP_INDENT_LEVEL,
            Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK
        );
        $this->log("Setting global '$name' to '$value' (from '{$previous}')");
        set_config('global_vars', $yaml, 'tool_dataflows');
    }

    /**
     * Updates the status of this engine
     *
     * This also records some metadata in the relevant objects e.g. the dataflow's state.
     *
     * @param  int $status a status from the engine class
     */
    public function set_status(int $status) {
        if ($status === $this->status) {
            return;
        }

        // Engines are single use. Once it has concluded, you can no longer change it's state.
        if (in_array($this->status, self::STATUS_TERMINATORS)) {
            throw new \moodle_exception('change_state_after_concluded', 'tool_dataflows');
        }
        $this->status = $status;

        // Updates the run's state when the engine status changes.
        if (isset($this->run)) {
            $this->run->snapshot($this->status);
        }

        // Record the timestamp of the state change against the dataflow persistent,
        // which exposes this info through its variables.
        $this->dataflow->set_state_timestamp($status, microtime(true));

        if ($status === self::STATUS_INITIALISED) {
            $this->log('status: ' . self::STATUS_LABELS[$status] . ', config: ' . json_encode(['isdryrun' => $this->isdryrun]));
        } else if (in_array($status, self::STATUS_TERMINATORS, true)) {
            $this->log('status: ' . self::STATUS_LABELS[$status]);
            $this->log("dumping state..\n" . $this->export());
        } else {
            $this->log('status: ' . self::STATUS_LABELS[$status]);
        }
    }

    /**
     * Returns the data that should be included in an export
     *
     * @return  array
     */
    public function get_export_data(): array {
        $encoded = json_encode($this->get_variables(), defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \moodle_exception(json_last_error_msg());
        }
        $cleanvariables = json_decode($encoded, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \moodle_exception(json_last_error_msg());
        }
        return $cleanvariables;
    }

    /**
     * Checks the current status against the expected status and throws an exception if they do not match.
     *
     * @param int $expected
     * @throws \moodle_exception
     */
    protected function status_check(int $expected) {
        if ($this->status !== $expected) {
            throw new \moodle_exception('bad_status', 'tool_dataflows', '',
                [
                    'status' => get_string('engine_status:'.self::STATUS_LABELS[$this->status], 'tool_dataflows'),
                    'expected' => get_string('engine_status:'.self::STATUS_LABELS[$expected], 'tool_dataflows'),
                ]
            );
        }
    }

    /**
     * Resolves the full path name for the givne path. If the directory does nto exist, it will create it.
     *
     * @param  string $pathname
     * @return false|resource
     */
    public function resolve_path(string $pathname) {
        global $CFG;

        $fullpath = helper::path_get_absolute($pathname, $this->scratchdir);
        $dir = dirname($fullpath);
        if (!file_exists($dir)) {
            mkdir($dir, $CFG->directorypermissions, true);
        }

        return $fullpath;
    }

    /**
     * Creates a filename for a temporary file in the scratch directory.
     *
     * @param string $prefix
     * @return false|string
     */
    public function create_temporary_file($prefix = '____') {
        return tempnam($this->scratchdir, $prefix);
    }
}
