<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Operator;
use App\Modules\Customers\Support\CustomerLifecycleStatus;
use Illuminate\Support\Str;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }
});

function makeFailureReasonApiTenant(string $slug, ?string $failureReason = null): Customer
{
    return Customer::create([
        'slug' => $slug,
        'cluster_server_id' => ClusterServer::factory()->create(['status' => 'active'])->id,
        'domain' => "{$slug}.example.com",
        'status' => CustomerLifecycleStatus::FAILED,
        'failure_reason' => $failureReason,
    ]);
}

function failureReasonApiBearer(string $rawToken): array
{
    return ['Authorization' => "Bearer {$rawToken}"];
}

function createFailureReasonApiKey(array $allowedTenantSlugs): string
{
    $admin = Operator::factory()->admin()->create();
    $rawToken = 'sk_'.bin2hex(random_bytes(32));

    ApiKey::create([
        'id' => Str::uuid()->toString(),
        'operator_id' => $admin->id,
        'name' => 'CustomerFailureReason test key',
        'token_hash' => hash('sha256', $rawToken),
        'scopes' => ['tenants:read'],
        'allowed_tenant_slugs' => $allowedTenantSlugs,
    ]);

    return $rawToken;
}

it('GET /api/v1/tenants/{slug} returns failure_reason when customer is failed', function (): void {
    $slug = 'failed-api-'.substr(uniqid(), -6);
    $reason = 'customer_readiness_timeout';
    makeFailureReasonApiTenant($slug, $reason);
    $rawToken = createFailureReasonApiKey([$slug]);

    $response = $this->getJson(
        "/api/v1/tenants/{$slug}",
        failureReasonApiBearer($rawToken),
    );

    $response->assertOk()
        ->assertJsonPath('data.slug', $slug)
        ->assertJsonPath('data.status', CustomerLifecycleStatus::FAILED)
        ->assertJsonPath('data.failure_reason', $reason);
});

it('GET /api/v1/tenants/{slug} omits failure_reason when customer is active', function (): void {
    $slug = 'active-api-'.substr(uniqid(), -6);
    Customer::create([
        'slug' => $slug,
        'cluster_server_id' => ClusterServer::factory()->create(['status' => 'active'])->id,
        'domain' => "{$slug}.example.com",
        'status' => CustomerLifecycleStatus::ACTIVE,
        'failure_reason' => null,
    ]);
    $rawToken = createFailureReasonApiKey([$slug]);

    $response = $this->getJson(
        "/api/v1/tenants/{$slug}",
        failureReasonApiBearer($rawToken),
    );

    $response->assertOk()
        ->assertJsonPath('data.slug', $slug)
        ->assertJsonPath('data.status', CustomerLifecycleStatus::ACTIVE)
        ->assertJsonMissingPath('data.failure_reason');
});
