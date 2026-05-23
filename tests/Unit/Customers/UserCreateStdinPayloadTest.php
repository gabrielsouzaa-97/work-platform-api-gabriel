<?php

declare(strict_types=1);

use App\Modules\Customers\Support\UserCreateStdinPayload;

it('builds upstream user create stdin payload with snake_case keys', function () {
    $payload = UserCreateStdinPayload::build(
        password: 'Secret123!',
        displayName: 'Ricardo Ramos',
        email: 'ricardo@example.com',
        quota: '5 GB',
        groups: ['admin'],
        subadminGroups: ['financeiro'],
    );

    expect($payload)->toBe([
        'password' => 'Secret123!',
        'display_name' => 'Ricardo Ramos',
        'email' => 'ricardo@example.com',
        'quota' => '5GB',
        'groups' => ['admin'],
        'subadmin_groups' => ['financeiro'],
    ]);
});

it('normalizeQuota preserves keyword values', function (string $input, string $expected) {
    expect(UserCreateStdinPayload::normalizeQuota($input))->toBe($expected);
})->with([
    ['none', 'none'],
    ['default', 'default'],
    ['unlimited', 'unlimited'],
    ['5 GB', '5GB'],
    ['10MB', '10MB'],
]);
