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

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\BrowserConsoleHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\PsrLogMessageProcessor;
use Symfony\Bridge\Monolog\Logger;
use tool_dataflows\dataflow;
use tool_dataflows\exportable;
use tool_dataflows\helper;
use tool_dataflows\local\execution\logging\log_handler;
use tool_dataflows\local\execution\logging\mtrace_handler;
use tool_dataflows\local\service\step_service;
use tool_dataflows\local\step\flow_cap;
use tool_dataflows\local\variables\var_dataflow;
use tool_dataflows\local\variables\var_root;
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

    /** @var engine_step The current engine step running in the engine. */
    protected $currentstep;

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

    /** @var Logger Primary logger for the current engine instance. */
    protected $logger;

    /**
     * Constructs the engine.
     *
     * @param dataflow $dataflow The dataflow to be executed, as defined in the editor.
     * @param bool $isdryrun global dryrun exection flag.
     * @param bool $automated Execution of this run was an automated trigger.
     */
    public function __construct(dataflow $dataflow, bool $isdryrun = false, $automated = true) {
        $this->dataflow = $dataflow;
        $this->isdryrun = $isdryrun;
        $this->automated = $automated;
        $status = self::STATUS_NEW;

        if (!$this->isdryrun) {
            $this->run = new run;
            $this->run->dataflowid = $this->dataflow->id;
            $this->run->initialise($status, $this->export());
        }

        // Set up logging.
        $this->setup_logging();

        // Force the dataflow to create a fresh set of variables.
        $dataflow->clear_variables();
        $dataflow->get_variables_root();

        $this->set_status($status);

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

        // Make the runid available to the flow.
        if (!$this->isdryrun) {
            $variables = $this->get_variables();
            $variables->set('run.name', $this->run->name);
            $variables->set('run.id', $this->run->id);
        }
    }

    /**
     * Destructor function.
     * Releases any locks that are still held.
     */
    public function __destruct() {
        $this->release_lock();
    }

    /**
     * Gets the root node of the variables tree.
     *
     * @return var_root
     */
    public function get_variables_root(): var_root {
        return $this->dataflow->get_variables_root();
    }

    /**
     * Gets the dataflow node of the variable tree.
     *
     * @return var_dataflow
     */
    public function get_variables(): var_dataflow {
        return $this->get_variables_root()->get_dataflow_variables();
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

        // Register signal handler - if a signal caused the dataflow to stop.
        \core_shutdown_manager::register_signal_handler(function ($signo){
            $error = error_get_last();
            $this->logger->log(Logger::NOTICE, 'Engine: shutdown signal ({signo}) received', [
                'signo' => $signo,
                'lasterror' => $error,
            ]);
            return \core\local\cli\shutdown::signal_handler($signo);
        });

        // Register shutdown handler - if request is ended by client, abort and finalise flow.
        \core_shutdown_manager::register_function(function (){
            $this->logger->log(Logger::DEBUG, 'Engine: shutdown handler was called');

            // If the script has stopped and flow is not finalised then abort.
            if (!in_array($this->status, [self::STATUS_FINALISED, self::STATUS_ABORTED])) {
                $error = error_get_last();
                $this->logger->log(Logger::ERROR, 'Engine: shutdown happened abruptly', ['lasterror' => $error]);
                $this->set_status(self::STATUS_ABORTED);

                $notifyreason = new \Exception('Shutdown handler triggered abort. Last error: ' . $error);
                $this->notify_on_abort($notifyreason);

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
                $flowcapnumber++;
                $step->name = "flowcap-{$flowcapnumber}";
                $step->set('type', flow_cap::class);
                $step->set_dataflow($this->dataflow);
                $this->get_variables_root()->add_step($step);
                $steptype = new flow_cap($step, $this);
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

            // TODO: Persistance of dataflow and global vars has been temporarily removed.

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
        // If already aborted, do nothing.
        if ($this->status === self::STATUS_ABORTED) {
            return;
        }

        $message = '';
        if (isset($reason)) {
            $message .= $reason->getMessage();
            if (isset($reason->debuginfo)) {
                $message .= PHP_EOL . $reason->debuginfo;
            }
        }

        $this->set_current_step(null);
        $context = [];
        if (!empty($reason->debuginfo)) {
            $context = (array) json_decode($reason->debuginfo);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $context = [];
            }
        }
        $this->log('Engine: aborting steps', $message ? array_merge(['reason' => $message], $context) : [], Logger::NOTICE);
        $this->exception = $reason;
        foreach ($this->enginesteps as $enginestep) {
            $status = $enginestep->status;
            if ($status !== self::STATUS_FINISHED && !in_array($status, self::STATUS_TERMINATORS)) {
                $this->set_current_step($enginestep);
                $enginestep->abort();
            } else {
                // We need to signal to finished steps that the dataflow is aborted.
                // This may require handling seperate to the step abort.
                // This is done seperate to the finalise hook so that concerns are seperated for finalised vs aborted runs.
                $this->set_current_step($enginestep);
                $enginestep->dataflow_abort();
            }
        }
        foreach ($this->flowcaps as $enginestep) {
            $status = $enginestep->status;
            if ($status !== self::STATUS_FINISHED && !in_array($status, self::STATUS_TERMINATORS)) {
                $this->set_current_step($enginestep);
                $enginestep->abort();
            }
        }
        $this->set_current_step(null);
        $this->queue = [];
        $this->set_status(self::STATUS_ABORTED);
        $this->release_lock();

        // If configured to send email, attempt to notify of the abort reason.
        $this->notify_on_abort($reason);

        // TODO: We may want to make this the responsibility of the caller.
        if (isset($reason)) {
            throw $reason;
        }
    }

    /**
     * Emit a log message.
     *
     * @param string $message
     * @param mixed $context
     * @param mixed $level
     */
    public function log(string $message, $context = [], $level = Logger::INFO) {
        $this->logger->log($level, $message, $context);
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
            case 'logger':
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
            if ($status === self::STATUS_ABORTED) {
                // Don't crash if aborting, but make a note of it.
                $this->logger->info('Aborted within concluded state (' . self::STATUS_LABELS[$this->status] . ')');
            } else {
                throw new \moodle_exception(
                    'change_state_after_concluded',
                    'tool_dataflows',
                    '',
                    ['from' => self::STATUS_LABELS[$this->status], 'to' => self::STATUS_LABELS[$status]]
                );
            }
        }
        $this->status = $status;

        // Updates the run's state when the engine status changes.
        if (isset($this->run)) {
            $this->run->snapshot($this->status);
        }

        // Record the timestamp of the state change.
        $statusstring = self::STATUS_LABELS[$status];
        $this->get_variables()->set("states.$statusstring", microtime(true));

        $context = [
            'isdryrun' => $this->isdryrun,
            'status' => get_string('engine_status:'.self::STATUS_LABELS[$this->status], 'tool_dataflows'),
        ];

        $level = Logger::INFO;
        if (in_array($status, self::STATUS_TERMINATORS, true)) {
            $level = Logger::NOTICE;
            $context['export'] = $this->get_export_data();
        }
        $this->logger->log($level, "Engine: dataflow '{status}'", $context);
    }

    /**
     * Returns the data that should be included in an export
     *
     * @return  array
     */
    public function get_export_data(): array {
        $encoded = json_encode(
            $this->get_variables_root()->get(),
            defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0 // phpcs:ignore
        );
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

    /**
     * Sets the currently processing step for the engine.
     *
     * @param engine_step|null $step
     */
    public function set_current_step(?engine_step $step) {
        $this->currentstep = $step;
    }

    /**
     * Set up the logging for this engine.
     *
     * This will enable any adapters / handlers enabled from the dataflow configuration.
     * The preference for applying the rules will be from most specific to more
     * general settings.
     */
    private function setup_logging() {
        global $CFG;
        // Initalise a new run (only for non-dry runs). This should only be
        // created when the engine is executed.
        $channel = 'dataflow/' . $this->dataflow->id;
        if (isset($this->run->name)) {
            $channel .= '/' . $this->run->name;
        }

        // Set the starting time as 'now'.
        $now = microtime(true);
        [, $decimal] = explode('.', $now);
        $decimal = substr($decimal, 0, 3); // Only use the first 3 digits after the decimal point.
        $rundateformat = date("Ymd_His$decimal", (int) $now);

        // Each channel represents a specific way of writing log information.
        $log = new Logger($channel);

        // Add a custom formatter.
        $log->pushProcessor(new PsrLogMessageProcessor(null, true));

        // Ensure step names are used if supplied.
        $log->pushProcessor(function ($record) {
            if (isset($this->currentstep)) {
                $record['context']['step'] = $this->currentstep->name;
            }

            if (isset($record['context']['step'])) {
                $record['message'] = '{step}: ' . $record['message'];
            }
            return $record;
        });

        // Tweak the default datetime output to include microseconds.
        $lineformatter = new LineFormatter(null, 'Y-m-d H:i:s.u');

        // Log handler settings (prefer dataflow if set, otherwise site level settings).
        $loghandlers = array_flip(explode(',', get_config('tool_dataflows', 'log_handlers')));
        $dataflowloghandlers = array_flip(array_filter(explode(',', $this->dataflow->get('loghandlers'))));
        if (!empty($dataflowloghandlers)) {
            $loghandlers = $dataflowloghandlers;
        }

        // Minimum logging levels - will display this level and above.
        $minloglevel = $this->dataflow->get('minloglevel');

        // Default Moodle handler. Always on.
        $mtracehandler = new mtrace_handler($minloglevel);
        $mtracehandler->setFormatter($lineformatter);
        $log->pushHandler($mtracehandler);

        // Log to the browser's dev console for a manual run.
        if (isset($loghandlers[log_handler::BROWSER_CONSOLE])) {
            $log->pushHandler(new BrowserConsoleHandler($minloglevel));
        }

        // Dataflow run logger.
        // Type: FILE_PER_RUN
        // e.g. '[dataroot]/tool_dataflows/3/Ymd_His.uuu_21.log' as the path.
        if (isset($loghandlers[log_handler::FILE_PER_RUN])) {
            $dataflowrunlogpath = $CFG->dataroot . DIRECTORY_SEPARATOR .
                'tool_dataflows' . DIRECTORY_SEPARATOR .
                $this->dataflow->id . DIRECTORY_SEPARATOR .
                $rundateformat . '_' . $this->run->name . '.log';

            $streamhandler = new StreamHandler($dataflowrunlogpath, $minloglevel);
            $streamhandler->setFormatter($lineformatter);
            $log->pushHandler($streamhandler);
        }

        // General dataflow logger (rotates daily to prevent big single log file).
        // Type: FILE_PER_DATAFLOW
        // e.g. '[dataroot]/tool_dataflows/20060102-3.log' as the path.
        if (isset($loghandlers[log_handler::FILE_PER_DATAFLOW])) {
            $dataflowlogpath = $CFG->dataroot . DIRECTORY_SEPARATOR .
                'tool_dataflows' . DIRECTORY_SEPARATOR .
                $this->dataflow->id . '.log';

            $rotatingfilehandler = new RotatingFileHandler($dataflowlogpath, 0, $minloglevel);
            $dateformat = 'Ymd';
            $filenameformat = '{date}_{filename}';
            $rotatingfilehandler->setFilenameFormat($filenameformat, $dateformat);

            $rotatingfilehandler->setFormatter($lineformatter);
            $log->pushHandler($rotatingfilehandler);
        }

        $this->logger = $log;
    }

    /**
     * Send a notification email for abort if required.
     *
     * @param ?\Throwable $reason A throwable representing the reason for abort.
     */
    public function notify_on_abort(?\Throwable $reason) {
        // If configured to send email, attempt to notify of the abort reason.
        $notifyemails = $this->dataflow->get('notifyonabort');

        foreach (explode(',', $notifyemails) as $notifyemail) {
            $email = trim($notifyemail);

            if (empty($email)) {
                continue;
            }

            $this->log('Sending abort notification email.', [], Logger::NOTICE);
            $context = [
                'flowname' => $this->dataflow->get('name'),
                'run' => $this->run->get('id'),
                'reason' => isset($reason) ? $reason->getMessage() : '',
            ];
            $message = get_string('notifyonabort_message', 'tool_dataflows', $context);

            // First try to match the email with a Moodle user.
            $to = \core_user::get_user_by_email($email);

            // Otherwise send it with a dummy account.
            if (!$to) {
                $to = \core_user::get_noreply_user();
                $to->email = $email;
                $to->firstname = $this->dataflow->get('name');
                $to->emailstop = 0;
                $to->maildisplay = true;
                $to->mailformat = 1;
            }
            $from = \core_user::get_noreply_user();
            $subject = get_string('notifyonabort_subject', 'tool_dataflows', $this->dataflow->get('name'));

            email_to_user($to, $from, $subject, $message);
        }
    }
}
