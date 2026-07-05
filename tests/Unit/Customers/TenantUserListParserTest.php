<?php

declare(strict_types=1);

use App\Modules\Customers\Support\TenantUserListParser;

it('parseUpstreamList aceita lista direta de objetos', function (): void {
    $rows = TenantUserListParser::parseUpstreamList([
        [
            'username' => 'alice',
            'email' => 'alice@example.com',
            'quota' => '5 GB',
            'groups' => ['users'],
        ],
        [
            'username' => 'bob',
            'email' => 'bob@example.com',
            'quota' => 'none',
            'groups' => ['editors'],
        ],
    ]);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['username'])->toBe('alice')
        ->and($rows[0]['email'])->toBe('alice@example.com')
        ->and($rows[0]['quota'])->toBe('5 GB')
        ->and($rows[0]['groups'])->toBe(['users'])
        ->and($rows[1]['username'])->toBe('bob');
});

it('parseUpstreamList aceita envelope users', function (): void {
    $rows = TenantUserListParser::parseUpstreamList([
        'users' => [
            [
                'username' => 'carol',
                'email' => 'carol@example.com',
                'quota' => '10 GB',
                'groups' => ['admins'],
            ],
        ],
    ]);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['username'])->toBe('carol')
        ->and($rows[0]['email'])->toBe('carol@example.com');
});

it('parseUpstreamList resolve aliases uid mail e file_quota', function (): void {
    $rows = TenantUserListParser::parseUpstreamList([
        [
            'uid' => 'legacy-user',
            'mail' => 'legacy@example.com',
            'file_quota' => '2 GB',
            'groups' => ['staff'],
        ],
    ]);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['username'])->toBe('legacy-user')
        ->and($rows[0]['email'])->toBe('legacy@example.com')
        ->and($rows[0]['quota'])->toBe('2 GB')
        ->and($rows[0]['groups'])->toBe(['staff']);
});

it('parseUpstreamList normaliza groups null e array vazio para lista vazia', function (): void {
    $nullGroups = TenantUserListParser::parseUpstreamList([
        ['username' => 'no-groups', 'groups' => null],
    ]);
    $emptyGroups = TenantUserListParser::parseUpstreamList([
        ['username' => 'empty-groups', 'groups' => []],
    ]);

    expect($nullGroups[0]['groups'])->toBe([])
        ->and($emptyGroups[0]['groups'])->toBe([]);
});

it('parseUpstreamList ignora itens malformados sem lançar exception', function (): void {
    $rows = TenantUserListParser::parseUpstreamList([
        'orphan-string',
        ['mail' => 'no-username@example.com'],
        [
            'username' => 'valid',
            'email' => 'valid@example.com',
        ],
    ]);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['username'])->toBe('valid');
});

it('parseUpstreamList retorna lista vazia para payload vazio ou inválido', function (mixed $payload): void {
    expect(TenantUserListParser::parseUpstreamList($payload))->toBe([]);
})->with([
    'empty list' => [[]],
    'null' => [null],
    'associative without users key' => [['schema_version' => '1']],
]);
