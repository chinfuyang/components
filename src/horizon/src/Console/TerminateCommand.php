<?php

declare(strict_types=1);

namespace Hypervel\Horizon\Console;

use Hypervel\Cache\Contracts\Factory as CacheFactory;
use Hypervel\Console\Command;
use Hypervel\Horizon\Contracts\MasterSupervisorRepository;
use Hypervel\Horizon\MasterSupervisor;
use Hypervel\Support\Arr;
use Hypervel\Support\Str;
use Hypervel\Support\Traits\InteractsWithTime;

class TerminateCommand extends Command
{
    use InteractsWithTime;

    /**
     * The name and signature of the console command.
     */
    protected ?string $signature = 'horizon:terminate
                            {--wait : Wait for all workers to terminate}';

    /**
     * The console command description.
     */
    protected string $description = 'Terminate the master supervisor so it can be restarted';

    /**
     * Execute the console command.
     */
    public function handle(CacheFactory $cache, MasterSupervisorRepository $masters): void
    {
        if (config('horizon.fast_termination')) {
            /* @phpstan-ignore-next-line */
            $cache->forever(
                'horizon:terminate:wait',
                $this->option('wait')
            );
        }

        $masters = collect($masters->all())->filter(function ($master) {
            return Str::startsWith($master->name, MasterSupervisor::basename());
        })->all();

        collect(Arr::pluck($masters, 'pid'))
            ->whenNotEmpty(fn () => $this->components->info('Sending TERM signal to processes.'))
            ->whenEmpty(fn () => $this->components->info('No processes to terminate.'))
            ->each(function ($processId) {
                $result = true;

                $this->components->task("Process: {$processId}", function () use ($processId, &$result) {
                    return $result = posix_kill((int) $processId, SIGTERM);
                });

                if (! $result) {
                    $this->components->error("Failed to kill process: {$processId} (" . posix_strerror(posix_get_last_error()) . ')');
                }
            })->whenNotEmpty(fn () => $this->output->writeln(''));

        $this->app->get(CacheFactory::class)
            ->forever('illuminate:queue:restart', $this->currentTime());
    }
}
