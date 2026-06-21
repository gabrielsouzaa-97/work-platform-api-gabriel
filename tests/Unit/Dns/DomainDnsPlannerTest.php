<?php

declare(strict_types=1);

use App\Modules\Dns\Services\DomainDnsPlanner;

it('defaultDkimHost returns default selector host for domain', function (): void {
    $planner = new DomainDnsPlanner;

    expect($planner->defaultDkimHost('acme.example.com'))
        ->toBe('default._domainkey.acme.example.com');
});

it('dkimLookupHosts includes default and mail-api selector patterns', function (): void {
    $planner = new DomainDnsPlanner;

    expect($planner->dkimLookupHosts('acme.example.com'))->toBe([
        'default._domainkey.acme.example.com',
        'mail-api._domainkey.acme.example.com',
    ]);
});
