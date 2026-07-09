<?php

declare(strict_types=1);

use App\Modules\Customers\Contracts\ProvisioningReadinessContract;

it('legacyRequiredAppIds includes mework360_memail and me360_theme', function (): void {
    $contract = app(ProvisioningReadinessContract::class);

    expect($contract->legacyRequiredAppIds())
        ->toContain('mework360_memail', 'me360_theme');
});

it('isSatisfiedByImageMode policy passes when image mode is enabled', function (): void {
    $contract = app(ProvisioningReadinessContract::class);

    expect($contract->isSatisfiedByImageMode(true))->toBeTrue();
});

it('isSatisfiedByImageMode policy does not pass when image mode is disabled', function (): void {
    $contract = app(ProvisioningReadinessContract::class);

    expect($contract->isSatisfiedByImageMode(false))->toBeFalse();
});
