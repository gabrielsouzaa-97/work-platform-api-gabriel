<?php

declare(strict_types=1);

namespace App\Modules\Dns\Actions;

use App\Models\Customer;
use App\Modules\Dns\Services\PdnsClient;
use App\Modules\Mail\Services\MailApiClient;

final class PublishDkimRecordAction
{
    private const string META_KEY = 'dkim_published';

    public function __construct(
        private readonly MailApiClient $mailApiClient,
        private readonly PdnsClient $pdnsClient,
    ) {}

    public function execute(Customer $customer): void
    {
        $domain = (string) $customer->domain;

        if ($domain === '' || $this->isPublished($customer)) {
            return;
        }

        $dkim = $this->mailApiClient->fetchDkim($domain);
        $recordName = (string) ($dkim['record_name'] ?? '');
        $publicKey = (string) ($dkim['public_key'] ?? '');

        if ($recordName === '' || $publicKey === '') {
            return;
        }

        $this->pdnsClient->upsertRecord($domain, $recordName, 'TXT', $publicKey);
        $this->markPublished($customer);
    }

    private function isPublished(Customer $customer): bool
    {
        $meta = $customer->branding_meta ?? [];

        return ($meta[self::META_KEY] ?? false) === true;
    }

    private function markPublished(Customer $customer): void
    {
        $meta = $customer->branding_meta ?? [];
        $meta[self::META_KEY] = true;

        $customer->update(['branding_meta' => $meta]);
    }
}
