<?php

declare(strict_types=1);

/**
 * Contract tests for typed farm-agent operation payloads (Sprint N20.5).
 *
 * Validates canonical examples from docs/PLATFORM-V2-PLAN.md §2.3–2.4 against
 * the JSON Schema under tests/Contract/Agent/.
 */

use Illuminate\Support\Facades\File;

function agentSchemaDefinitions(): array
{
    $path = base_path('tests/Contract/Agent/agent-operations-v1.schema.json');
    $decoded = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);

    return $decoded['definitions'] ?? [];
}

/**
 * @param  array<string, mixed>  $definition
 * @param  array<string, mixed>  $payload
 */
function assertPayloadMatchesSchema(array $definition, array $payload): void
{
    expect($definition['type'] ?? null)->toBe('object');

    foreach ($definition['required'] ?? [] as $requiredKey) {
        expect($payload)->toHaveKey($requiredKey);
    }

    foreach ($payload as $key => $value) {
        if (($definition['additionalProperties'] ?? true) === false) {
            expect(array_key_exists($key, $definition['properties'] ?? []))
                ->toBeTrue("unexpected property {$key}");
        }

        $prop = $definition['properties'][$key] ?? null;
        if ($prop === null) {
            continue;
        }

        if (($prop['type'] ?? null) === 'string') {
            expect($value)->toBeString();
            if (isset($prop['minLength'])) {
                expect(strlen((string) $value))->toBeGreaterThanOrEqual((int) $prop['minLength']);
            }
        }

        if (($prop['type'] ?? null) === 'boolean') {
            expect($value)->toBeBool();
        }
    }
}

it('tenant.create example payload matches schema', function (): void {
    $definitions = agentSchemaDefinitions();
    $payload = [
        'tenant_slug' => 'acme-corp',
        'domain' => 'acme.example.com',
        'apps' => ['mework360_memail', 'me360_theme'],
        'full_apps' => false,
        'idempotency_key' => '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
        'callback_url' => 'https://deployer.mework360.com.br/api/jobs/hook',
    ];

    assertPayloadMatchesSchema($definitions['tenant_create_payload'], $payload);
});

it('memail.configure example payload matches schema', function (): void {
    $definitions = agentSchemaDefinitions();
    $payload = [
        'tenant_slug' => 'acme-corp',
        'external_location' => 'https://mail.acme.example.com',
        'force_sso' => true,
        'email_address_choice' => 'primary',
        'disable_core_mail_app' => true,
    ];

    assertPayloadMatchesSchema($definitions['memail_configure_payload'], $payload);
});

it('operation envelope requires schema_version 1 and operation id', function (): void {
    $definitions = agentSchemaDefinitions();
    $envelope = [
        'schema_version' => 1,
        'operation_id' => '550e8400-e29b-41d4-a716-446655440000',
        'operation' => 'tenant.create',
        'farm_id' => 'farm-saas-prod-01',
        'payload' => [
            'tenant_slug' => 'acme-corp',
            'domain' => 'acme.example.com',
        ],
    ];

    assertPayloadMatchesSchema($definitions['operation_envelope'], $envelope);
    expect($envelope['schema_version'])->toBe(1);
});

it('rejects memail.configure payload missing external_location', function (): void {
    $definitions = agentSchemaDefinitions();
    $payload = [
        'tenant_slug' => 'acme-corp',
        'force_sso' => true,
    ];

    expect($definitions['memail_configure_payload']['required'] ?? [])
        ->toContain('external_location');

    expect(array_key_exists('external_location', $payload))->toBeFalse();
});
