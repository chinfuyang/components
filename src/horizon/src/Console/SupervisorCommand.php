<?php

declare(strict_types=1);

namespace Hypervel\Horizon\Console;

use Exception;
use Hypervel\Console\Command;
use Hypervel\Horizon\Supervisor;
use Hypervel\Horizon\SupervisorFactory;
use Hypervel\Horizon\SupervisorOptions;

class SupervisorCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected ?string $signature = 'horizon:supervisor
                            {name : The name of supervisor}
                            {connection : The name of the connection to work}
                            {--balance= : The balancing strategy the supervisor should apply}
                            {--backoff=0 : The number of seconds to wait before retrying a job that encountered an uncaught exception}
                            {--max-jobs=0 : The number of jobs to process before stopping a child process}
                            {--max-time=0 : The maximum number of seconds a child process should run}
                            {--force : Force the worker to run even in maintenance mode}
                            {--max-processes=1 : The maximum number of total workers to start}
                            {--min-processes=1 : The minimum number of workers to assign per queue}
                            {--concurrency=1 : The number of jobs to process at once}
                            {--memory=128 : The memory limit in megabytes}
                            {--nice=0 : The process priority}
                            {--paused : Start the supervisor in a paused state}
                            {--queue= : The names of the queues to work}
                            {--sleep=3 : Number of seconds to sleep when no job is available}
                            {--timeout=60 : The number of seconds a child process can run}
                            {--tries=0 : Number of times to attempt a job before logging it failed}
                            {--auto-scaling-strategy=time : If supervisor should scale by jobs or time to complete}
                            {--balance-cooldown=3 : The number of seconds to wait in between auto-scaling attempts}
                            {--balance-max-shift=1 : The maximum number of processes to increase or decrease per one scaling}
                            {--workers-name=default : The name that should be assigned to the workers}
                            {--parent-id=0 : The parent process ID}
                            {--rest=0 : Number of seconds to rest between jobs}';

    /**
     * The console command description.
     */
    protected string $description = 'Start a new supervisor';

    /**
     * Indicates whether the command should be shown in the Artisan command list.
     */
    protected bool $hidden = true;

    /**
     * Execute the console command.
     */
    public function handle(SupervisorFactory $factory): ?int
    {
        $supervisor = $factory->make(
            $this->supervisorOptions()
        );

        try {
            $supervisor->ensureNoDuplicateSupervisors();
        } catch (Exception $e) {
            $this->components->error('A supervisor with this name is already running.');

            return 13;
        }

        $this->start($supervisor);

        return 0;
    }

    /**
     * Start the given supervisor.
     */
    protected function start(Supervisor $supervisor): void
    {
        if ($supervisor->options->nice) {
            proc_nice($supervisor->options->nice);
        }

        $supervisor->handleOutputUsing(function ($type, $line) {
            $this->output->write($line);
        });

        $supervisor->working = ! $this->option('paused');

        $minProcess = (int) $this->option('min-processes');
        $maxProcess = (int) $this->option('max-processes');
        $balancedWorkerCount = (int) floor(($minProcess + $maxProcess) / 2);
        $supervisor->scale(max(
            0,
            $balancedWorkerCount - $supervisor->totalSystemProcessCount()
        ));

        $supervisor->monitor();
    }

    /**
     * Get the supervisor options.
     */
    protected function supervisorOptions(): SupervisorOptions
    {
        $balance = $this->option('balance');

        $autoScalingStrategy = $balance === 'auto' ? $this->option('auto-scaling-strategy') : null;

        return new SupervisorOptions(
            $this->argument('name'),
            $this->argument('connection'),
            $this->getQueue($this->argument('connection')),
            $this->option('workers-name'),
            $balance,
            (int) $this->option('backoff'),
            (int) $this->option('max-time'),
            (int) $this->option('max-jobs'),
            (int) $this->option('max-processes'),
            (int) $this->option('min-processes'),
            (int) $this->option('concurrency'),
            (int) $this->option('memory'),
            (int) $this->option('timeout'),
            (int) $this->option('sleep'),
            (int) $this->option('tries'),
            $this->option('force'),
            (int) $this->option('nice'),
            (int) $this->option('balance-cooldown'),
            (int) $this->option('balance-max-shift'),
            (int) $this->option('parent-id'),
            (int) $this->option('rest'),
            $autoScalingStrategy,
        );
    }

    /**
     * Get the queue name for the worker.
     */
    protected function getQueue(string $connection): string
    {
        return $this->option('queue') ?: config(
            "queue.connections.{$connection}.queue",
            'default'
        );
    }
}
