<?php

declare(strict_types=1);

namespace App\Modules\Agents\Services;

use App\Models\AgentCommand;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\FarmAgent;
use App\Models\Job;
use App\Modules\Dns\Actions\ProvisionDnsZoneAction;
use App\Modules\Dns\Actions\PublishDkimRecordAction;
use App\Modules\Dns\Exceptions\PdnsException;
use App\Modules\Mail\Actions\ProvisionTenantMailAction;
use App\Modules\Mail\Exceptions\MailApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class AgentEventHandler
{
    private const string MAIL_PASSWORD_META_KEY = 'mail_admin_password_encrypted';

    private const string MAIL_PROVISIONED_META_KEY = 'mail_provisioned';

    public function __construct(
        private readonly AgentCommandQueue $commandQueue,
        private readonly ProvisionTenantMailAction $provisionTenantMailAction,
        private readonly ProvisionDnsZoneAction $provisionDnsZoneAction,
        private readonly PublishDkimRecordAction $publishDkimRecordAction,
    ) {}

    /**
     * @param  array<string, mixed>  $event
     */
    public function handle(FarmAgent $agent, array $event): void
    {
        $agent->update(['last_seen_at' => now()]);

        $operationId = isset($event['operation_id']) ? (string) $event['operation_id'] : '';
        $state = isset($event['state']) ? (string) $event['state'] : '';

        if ($operationId !== '' && $state !== '' && $this->commandBelongsToAgent($operationId, $agent)) {
            $this->commandQueue->ack($agent, $operationId, $state);
            $this->storeOperationResult($operationId, $event, $state);
            $this->maybeProvisionMailAfterContainersUp($agent, $event, $operationId);
        }

        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'actor_id' => null,
            'action' => 'farm_agent.event',
            'resource_type' => 'farm_agent',
            'resource_id' => $agent->id,
            'payload' => [
                'farm_id' => $agent->farm_id,
                'event_type' => $event['event_type'] ?? 'progress',
                'state' => $state,
                'step' => $event['step'] ?? null,
                'operation_id' => $operationId !== '' ? $operationId : null,
            ],
        ]);
    }

    private function commandBelongsToAgent(string $operationId, FarmAgent $agent): bool
    {
        return AgentCommand::query()
            ->where('operation_id', $operationId)
            ->where('farm_agent_id', $agent->id)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function storeOperationResult(string $operationId, array $event, string $state): void
    {
        $data = $event['data'] ?? null;

        if ($state === 'failed') {
            $error = $this->resolveFailureMessage($event, $data);
            if ($error !== null) {
                Cache::put(
                    AgentOperationResultCache::key($operationId),
                    ['error' => $error],
                    120,
                );

                return;
            }
        }

        if (is_array($data) && $this->storeJobIdIfPresent($operationId, $data)) {
            return;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function storeJobIdIfPresent(string $operationId, array $data): bool
    {
        if (! isset($data['job_id']) || ! is_string($data['job_id']) || $data['job_id'] === '') {
            return false;
        }

        Cache::put(
            AgentOperationResultCache::key($operationId),
            ['job_id' => $data['job_id']],
            120,
        );

        return true;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function resolveFailureMessage(array $event, mixed $data): ?string
    {
        if (is_array($data) && array_key_exists('error', $data)) {
            return $this->formatAgentError($data['error']);
        }

        if (isset($event['message']) && is_string($event['message']) && $event['message'] !== '') {
            return $event['message'];
        }

        return null;
    }

    private function formatAgentError(mixed $error): string
    {
        if (is_string($error) && $error !== '') {
            return $error;
        }

        if (is_array($error)) {
            $messages = array_values(array_filter($error, static fn (mixed $item): bool => is_string($item) && $item !== ''));

            if ($messages !== []) {
                return implode('; ', $messages);
            }
        }

        return 'Agent operation failed';
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function maybeProvisionMailAfterContainersUp(FarmAgent $agent, array $event, string $operationId): void
    {
        if (($event['step'] ?? '') !== 'containers_up') {
            return;
        }

        $command = $this->findTenantCreateCommand($agent, $operationId);
        if ($command === null) {
            return;
        }

        $customer = $this->resolveCustomerFromEvent($event);
        if ($customer === null) {
            return;
        }

        $mailPayload = $this->resolveMailPayload($customer, $command);
        if ($mailPayload === null) {
            return;
        }

        $mailbox = $mailPayload['default_mailbox'] ?? null;
        if (! is_string($mailbox) || $mailbox === '') {
            return;
        }

        $password = $this->resolveAndPersistMailPassword($customer, $mailPayload);
        if ($password === null) {
            return;
        }

        try {
            $this->provisionTenantMailAction->execute($customer, $mailbox, $password);
            try {
                $this->provisionDnsAfterMail($customer);
            } catch (PdnsException $exception) {
                $this->logDnsProvisionFailure($customer, $exception);
            }
        } catch (MailApiException $exception) {
            $this->logMailProvisionFailure($customer, $exception);
        }
    }

    private function findTenantCreateCommand(FarmAgent $agent, string $operationId): ?AgentCommand
    {
        $command = AgentCommand::query()
            ->where('operation_id', $operationId)
            ->where('farm_agent_id', $agent->id)
            ->first();

        if ($command === null || $command->operation !== 'tenant.create') {
            return null;
        }

        return $command;
    }

    /**
     * @param  array<string, mixed>  $mailPayload
     */
    private function resolveAndPersistMailPassword(Customer $customer, array $mailPayload): ?string
    {
        if ($this->isMailProvisioned($customer)) {
            $encrypted = ($customer->branding_meta ?? [])[self::MAIL_PASSWORD_META_KEY] ?? null;
            if (is_string($encrypted) && $encrypted !== '') {
                return decrypt($encrypted);
            }

            return null;
        }

        $provided = $mailPayload['admin_password'] ?? null;
        $password = is_string($provided) && $provided !== ''
            ? $provided
            : Str::password(16);

        $this->persistEncryptedMailPassword($customer, $password);

        return $password;
    }

    private function isMailProvisioned(Customer $customer): bool
    {
        $meta = $customer->branding_meta ?? [];
        if (($meta[self::MAIL_PROVISIONED_META_KEY] ?? false) === true) {
            return true;
        }

        return AuditLog::query()
            ->where('action', 'mail_provisioned')
            ->where('resource_type', 'customer')
            ->where('resource_id', $customer->slug)
            ->exists();
    }

    private function persistEncryptedMailPassword(Customer $customer, string $password): void
    {
        $meta = $customer->branding_meta ?? [];
        $meta[self::MAIL_PASSWORD_META_KEY] = encrypt($password);

        $customer->update(['branding_meta' => $meta]);
        $customer->refresh();
    }

    private function provisionDnsAfterMail(Customer $customer): void
    {
        if ((string) $customer->domain === '') {
            return;
        }

        $this->provisionDnsZoneAction->execute($customer);
        $this->publishDkimRecordAction->execute($customer);
    }

    private function logMailProvisionFailure(Customer $customer, MailApiException $exception): void
    {
        Log::error('Mail provisioning failed after containers_up', [
            'customer_slug' => $customer->slug,
            'message' => $exception->getMessage(),
        ]);

        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'actor_id' => null,
            'action' => 'mail_provision_failed',
            'resource_type' => 'customer',
            'resource_id' => $customer->slug,
            'payload' => [
                'error' => $exception->getMessage(),
            ],
            'cluster_server_id' => $customer->cluster_server_id,
            'job_id' => null,
            'ip' => null,
        ]);
    }

    private function logDnsProvisionFailure(Customer $customer, PdnsException $exception): void
    {
        Log::error('DNS provisioning failed after containers_up', [
            'customer_slug' => $customer->slug,
            'message' => $exception->getMessage(),
        ]);

        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'actor_id' => null,
            'action' => 'dns_provision_failed',
            'resource_type' => 'customer',
            'resource_id' => $customer->slug,
            'payload' => [
                'error' => $exception->getMessage(),
            ],
            'cluster_server_id' => $customer->cluster_server_id,
            'job_id' => null,
            'ip' => null,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveMailPayload(Customer $customer, AgentCommand $command): ?array
    {
        $payload = $customer->mail_provision_payload;
        if (! is_array($payload) || ($payload['provision_domain'] ?? false) !== true) {
            $payload = $command->payload['mail'] ?? null;
        }

        if (! is_array($payload) || ($payload['provision_domain'] ?? false) !== true) {
            return null;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function resolveCustomerFromEvent(array $event): ?Customer
    {
        $data = $event['data'] ?? null;
        if (! is_array($data)) {
            return null;
        }

        $jobId = $data['job_id'] ?? null;
        if (! is_string($jobId) || $jobId === '') {
            return null;
        }

        $job = Job::query()->where('job_id', $jobId)->first();
        if ($job === null || $job->customer_slug === null) {
            return null;
        }

        return Customer::find($job->customer_slug);
    }
}
