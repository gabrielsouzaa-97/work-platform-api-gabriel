<?php

declare(strict_types=1);

use App\Modules\Customers\Support\CustomerLifecycleAction;
use App\Modules\Customers\Support\CustomerLifecycleMatrix;
use App\Modules\Customers\Support\CustomerLifecycleStatus;

it('matrix allows lifecycle_async, occ_panel and remove only for active tenants', function (): void {
    expect(CustomerLifecycleMatrix::allows(CustomerLifecycleStatus::ACTIVE, CustomerLifecycleAction::LifecycleAsync))->toBeTrue()
        ->and(CustomerLifecycleMatrix::allows(CustomerLifecycleStatus::ACTIVE, CustomerLifecycleAction::OccPanel))->toBeTrue()
        ->and(CustomerLifecycleMatrix::allows(CustomerLifecycleStatus::ACTIVE, CustomerLifecycleAction::Remove))->toBeTrue()
        ->and(CustomerLifecycleMatrix::allows(CustomerLifecycleStatus::ACTIVE, CustomerLifecycleAction::PromoteManual))->toBeFalse();
});

it('matrix allows only remove for provisioning tenants', function (): void {
    expect(CustomerLifecycleMatrix::allows(CustomerLifecycleStatus::PROVISIONING, CustomerLifecycleAction::LifecycleAsync))->toBeFalse()
        ->and(CustomerLifecycleMatrix::allows(CustomerLifecycleStatus::PROVISIONING, CustomerLifecycleAction::OccPanel))->toBeFalse()
        ->and(CustomerLifecycleMatrix::allows(CustomerLifecycleStatus::PROVISIONING, CustomerLifecycleAction::Remove))->toBeTrue()
        ->and(CustomerLifecycleMatrix::allows(CustomerLifecycleStatus::PROVISIONING, CustomerLifecycleAction::PromoteManual))->toBeFalse();
});

it('matrix allows remove and promote_manual only for provisioning_finishing tenants', function (): void {
    expect(CustomerLifecycleMatrix::allows(CustomerLifecycleStatus::PROVISIONING_FINISHING, CustomerLifecycleAction::LifecycleAsync))->toBeFalse()
        ->and(CustomerLifecycleMatrix::allows(CustomerLifecycleStatus::PROVISIONING_FINISHING, CustomerLifecycleAction::OccPanel))->toBeFalse()
        ->and(CustomerLifecycleMatrix::allows(CustomerLifecycleStatus::PROVISIONING_FINISHING, CustomerLifecycleAction::Remove))->toBeTrue()
        ->and(CustomerLifecycleMatrix::allows(CustomerLifecycleStatus::PROVISIONING_FINISHING, CustomerLifecycleAction::PromoteManual))->toBeTrue();
});

it('matrix allows only remove for failed tenants', function (): void {
    expect(CustomerLifecycleMatrix::allows(CustomerLifecycleStatus::FAILED, CustomerLifecycleAction::LifecycleAsync))->toBeFalse()
        ->and(CustomerLifecycleMatrix::allows(CustomerLifecycleStatus::FAILED, CustomerLifecycleAction::OccPanel))->toBeFalse()
        ->and(CustomerLifecycleMatrix::allows(CustomerLifecycleStatus::FAILED, CustomerLifecycleAction::Remove))->toBeTrue()
        ->and(CustomerLifecycleMatrix::allows(CustomerLifecycleStatus::FAILED, CustomerLifecycleAction::PromoteManual))->toBeFalse();
});

it('matrix blocks all mutable actions for removing tenants', function (): void {
    foreach (CustomerLifecycleAction::cases() as $action) {
        expect(CustomerLifecycleMatrix::allows(CustomerLifecycleStatus::REMOVING, $action))->toBeFalse();
    }
});

it('matrix blocks all mutable actions for removed tenants', function (): void {
    foreach (CustomerLifecycleAction::cases() as $action) {
        expect(CustomerLifecycleMatrix::allows(CustomerLifecycleStatus::REMOVED, $action))->toBeFalse();
    }
});

it('matrix blocks all mutable actions for error tenants', function (): void {
    foreach (CustomerLifecycleAction::cases() as $action) {
        expect(CustomerLifecycleMatrix::allows(CustomerLifecycleStatus::ERROR, $action))->toBeFalse();
    }
});

it('isActive returns true only for active status', function (): void {
    expect(CustomerLifecycleMatrix::isActive(CustomerLifecycleStatus::ACTIVE))->toBeTrue()
        ->and(CustomerLifecycleMatrix::isActive(CustomerLifecycleStatus::PROVISIONING))->toBeFalse()
        ->and(CustomerLifecycleMatrix::isActive(CustomerLifecycleStatus::PROVISIONING_FINISHING))->toBeFalse()
        ->and(CustomerLifecycleMatrix::isActive(CustomerLifecycleStatus::FAILED))->toBeFalse()
        ->and(CustomerLifecycleMatrix::isActive(CustomerLifecycleStatus::REMOVING))->toBeFalse()
        ->and(CustomerLifecycleMatrix::isActive(CustomerLifecycleStatus::REMOVED))->toBeFalse()
        ->and(CustomerLifecycleMatrix::isActive(CustomerLifecycleStatus::ERROR))->toBeFalse();
});
