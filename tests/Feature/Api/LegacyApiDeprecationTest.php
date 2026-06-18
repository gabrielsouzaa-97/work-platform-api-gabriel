<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Operator;
use Illuminate\Support\Str;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }
});

it('adds Deprecation headers on legacy DELETE /api/customers/{slug}', function (): void {
    $operator = Operator::factory()->create(['role' => 'admin', 'status' => 'active']);
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    Customer::create([
        'slug' => 'dep-tenant',
        'cluster_server_id' => $cluster->id,
        'domain' => 'dep-tenant.example.com',
        'status' => 'active',
    ]);

    $rawToken = Str::random(40);
    ApiKey::factory()->create([
        'operator_id' => $operator->id,
        'token_hash' => hash('sha256', $rawToken),
        'scopes' => ['customers:write'],
        'allowed_tenant_slugs' => ['dep-tenant'],
    ]);

    $response = $this->deleteJson('/api/customers/dep-tenant', [
        'confirm_slug' => 'wrong-slug',
    ], [
        'Authorization' => 'Bearer '.$rawToken,
        'Accept' => 'application/json',
    ]);

    expect($response->headers->get('Deprecation'))->toBe('true');
    expect($response->headers->get('Sunset'))->toBe('Sat, 31 Dec 2026 23:59:59 GMT');
    expect($response->headers->get('Link'))->toContain('/api/v1/tenants/dep-tenant');
});

it('does not add Deprecation headers on v1 routes', function (): void {
    $operator = Operator::factory()->create(['role' => 'admin', 'status' => 'active']);
    $rawToken = Str::random(40);
    ApiKey::factory()->create([
        'operator_id' => $operator->id,
        'token_hash' => hash('sha256', $rawToken),
        'scopes' => ['tenants:read'],
        'allowed_tenant_slugs' => ['missing'],
    ]);

    $response = $this->getJson('/api/v1/tenants/missing-tenant', [
        'Authorization' => 'Bearer '.$rawToken,
        'Accept' => 'application/json',
    ]);

    expect($response->headers->get('Deprecation'))->toBeNull();
});
