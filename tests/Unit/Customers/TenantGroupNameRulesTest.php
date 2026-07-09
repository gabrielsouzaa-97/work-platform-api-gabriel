<?php

declare(strict_types=1);

use App\Modules\Customers\Support\TenantGroupNameRules;
use Tests\TestCase;

uses(TestCase::class);

it('TenantGroupNameRules class exists as shared validation source (CQ-F23-001)', function (): void {
    expect(class_exists(TenantGroupNameRules::class))->toBeTrue();
});

it('forAttribute returns non-empty rules for api name and panel groupName attributes', function (): void {
    $apiRules = TenantGroupNameRules::forAttribute('name');
    $panelRules = TenantGroupNameRules::forAttribute('groupName');

    expect($apiRules)->toBeArray()->not->toBeEmpty()
        ->and($panelRules)->toBeArray()->not->toBeEmpty();
});

it('forAttribute produces attribute-specific rule keys or messages for name vs groupName (CQ-F24-002)', function (): void {
    $nameRules = TenantGroupNameRules::forAttribute('name');
    $groupNameRules = TenantGroupNameRules::forAttribute('groupName');

    expect($nameRules)->not->toBe($groupNameRules);

    $namePayload = json_encode($nameRules, JSON_THROW_ON_ERROR);
    $groupNamePayload = json_encode($groupNameRules, JSON_THROW_ON_ERROR);

    expect($namePayload)->toContain('name')
        ->and($groupNamePayload)->toContain('groupName');
});

it('forAttribute rules reject reserved admin case-insensitively', function (string $candidate): void {
    $rules = TenantGroupNameRules::forAttribute('name');
    $validator = validator(['name' => $candidate], ['name' => $rules]);

    expect($validator->fails())->toBeTrue();
})->with(['admin', 'ADMIN', 'Admin']);

it('forAttribute rules accept valid group names', function (string $candidate): void {
    $rules = TenantGroupNameRules::forAttribute('name');
    $validator = validator(['name' => $candidate], ['name' => $rules]);

    expect($validator->fails())->toBeFalse();
})->with(['editors', 'financeiro', 'team.alpha', 'staff-1']);
