<?php

declare(strict_types=1);

namespace Hypervel\Horizon;

class QueueCommandString
{
    /**
     * Get the additional option string for the worker command.
     */
    public static function toWorkerOptionsString(SupervisorOptions $options): string
    {
        return sprintf(
            '--name=%s --supervisor=%s %s',
            $options->workersName,
            $options->name,
            static::toOptionsString($options)
        );
    }

    /**
     * Get the additional option string for the supervisor command.
     */
    public static function toSupervisorOptionsString(SupervisorOptions $options): string
    {
        return sprintf(
            '--workers-name=%s --balance=%s --max-processes=%s --min-processes=%s --nice=%s --balance-cooldown=%s --balance-max-shift=%s --parent-id=%s --auto-scaling-strategy=%s %s',
            $options->workersName,
            $options->balance,
            $options->maxProcesses,
            $options->minProcesses,
            $options->nice,
            $options->balanceCooldown,
            $options->balanceMaxShift,
            $options->parentId,
            $options->autoScalingStrategy,
            static::toOptionsString($options)
        );
    }

    /**
     * Get the additional option string for the command.
     */
    public static function toOptionsString(SupervisorOptions $options, bool $paused = false): string
    {
        $string = sprintf(
            '--backoff=%s --max-time=%s --max-jobs=%s --memory=%s --queue="%s" --sleep=%s --timeout=%s --tries=%s --rest=%s --concurrency=%s',
            $options->backoff,
            $options->maxTime,
            $options->maxJobs,
            $options->memory,
            $options->queue,
            $options->sleep,
            $options->timeout,
            $options->maxTries,
            $options->rest,
            $options->concurrency,
        );

        if ($options->force) {
            $string .= ' --force';
        }

        if ($paused) {
            $string .= ' --paused';
        }

        return $string;
    }
}
