<?php

declare(strict_types=1);

namespace App\Modules\Mail\Actions;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Modules\Mail\Services\MailApiClient;
use Illuminate\Support\Str;

final class ProvisionTenantMailAction
{
    public function __construct(
        private readonly MailApiClient $mailApiClient,
    ) {}

    public function execute(Customer $customer, string $adminEmail, string $adminPassword): void
    {
        $domain = $this->domainFromEmail($adminEmail);
        $localPart = $this->localPartFromEmail($adminEmail);

        $this->mailApiClient->createDomain($domain);
        $this->mailApiClient->createMailbox($domain, $localPart, $adminPassword);

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
}
