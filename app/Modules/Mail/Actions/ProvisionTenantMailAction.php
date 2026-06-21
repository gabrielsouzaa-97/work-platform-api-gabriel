<?php

declare(strict_types=1);

namespace App\Modules\Mail\Actions;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Modules\Mail\Services\MailApiClient;
use Illuminate\Support\Str;

final class ProvisionTenantMailAction
{
    private const string META_KEY = 'mail_provisioned';

    public function __construct(
        private readonly MailApiClient $mailApiClient,
    ) {}

    public function execute(Customer $customer, string $adminEmail, string $adminPassword): void
    {
        if ($this->isProvisioned($customer)) {
            return;
        }

        $domain = $this->domainFromEmail($adminEmail);
        $localPart = $this->localPartFromEmail($adminEmail);

        $this->mailApiClient->createDomain($domain);
        $this->mailApiClient->createMailbox($domain, $localPart, $adminPassword);
        $this->markProvisioned($customer);

        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'actor_id' => null,
            'action' => 'mail_provisioned',
            'resource_type' => 'customer',
            'resource_id' => $customer->slug,
            'payload' => [
                'domain' => $domain,
                'mailbox' => $adminEmail,
            ],
            'cluster_server_id' => $customer->cluster_server_id,
            'job_id' => null,
            'ip' => null,
        ]);
    }

    private function domainFromEmail(string $email): string
    {
        $parts = explode('@', $email, 2);

        return $parts[1] ?? $email;
    }

    private function localPartFromEmail(string $email): string
    {
        $parts = explode('@', $email, 2);

        return $parts[0] !== '' ? $parts[0] : 'admin';
    }

    private function isProvisioned(Customer $customer): bool
    {
        $meta = $customer->branding_meta ?? [];
        if (($meta[self::META_KEY] ?? false) === true) {
            return true;
        }

        return AuditLog::query()
            ->where('action', 'mail_provisioned')
            ->where('resource_type', 'customer')
            ->where('resource_id', $customer->slug)
            ->exists();
    }

    private function markProvisioned(Customer $customer): void
    {
        $meta = $customer->branding_meta ?? [];
        $meta[self::META_KEY] = true;

        $customer->update(['branding_meta' => $meta]);
    }
}
