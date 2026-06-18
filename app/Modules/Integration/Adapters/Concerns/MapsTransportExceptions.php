<?php

declare(strict_types=1);

namespace App\Modules\Integration\Adapters\Concerns;

use App\Modules\Agents\Exceptions\AgentTransportException;
use App\Modules\Core\Ssh\Exceptions\SshClientException;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Integration\Exceptions\CapabilityBlockedException;
use App\Modules\Integration\Exceptions\PortIdempotencyConflictException;
use App\Modules\Integration\Exceptions\PortStateConflictException;
use App\Modules\Integration\Exceptions\UpstreamUnavailableException;

trait MapsTransportExceptions
{
    protected function mapTransportException(\Throwable $exception): never
    {
        if ($exception instanceof ClusterUnreachableException) {
            throw $exception;
        }

        if ($exception instanceof PortStateConflictException
            || $exception instanceof PortIdempotencyConflictException
            || $exception instanceof CapabilityBlockedException
            || $exception instanceof UpstreamUnavailableException) {
            throw $exception;
        }

        if ($exception instanceof AgentTransportException
            || $exception instanceof SshConnectionException
            || $exception instanceof SshTimeoutException) {
            throw new UpstreamUnavailableException($exception->getMessage(), 0, cause: $exception);
        }

        if ($exception instanceof SshRemoteException) {
            if ($exception->idempotencyConflict) {
                $existingJobId = $exception->parsedJson['existing_job_id'] ?? null;

                throw new PortIdempotencyConflictException(
                    is_string($existingJobId) ? $existingJobId : null,
                );
            }

            if ($exception->stateConflict) {
                throw new PortStateConflictException($exception->parsedJson['diff'] ?? []);
            }

            if ($exception->remoteExitCode === 16) {
                throw new CapabilityBlockedException($exception->getMessage(), $exception->remoteExitCode, $exception);
            }

            throw new UpstreamUnavailableException($exception->getMessage(), $exception->remoteExitCode, cause: $exception);
        }

        if ($exception instanceof SshClientException) {
            throw new UpstreamUnavailableException($exception->getMessage(), 0, cause: $exception);
        }

        throw $exception;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function withMappedTransport(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            $this->mapTransportException($e);
        }
    }
}
