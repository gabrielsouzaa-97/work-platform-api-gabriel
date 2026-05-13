<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Operator;
use Illuminate\Support\Str;

function makeJobFixture(string $clusterId, string $customerSlug, string $state = 'running', string $jobType = 'provision'): Job
{
    Customer::firstOrCreate(['slug' => $customerSlug], [
        'cluster_server_id' => $clusterId,
        'domain' => $customerSlug.'.example.com',
        'status' => 'active',
    ]);

    return Job::create([
        'job_id' => Str::uuid()->toString(),
        'customer_slug' => $customerSlug,
        'cluster_server_id' => $clusterId,
        'cmd_canonical' => "nextcloud-manage {$customerSlug} _ {$jobType}",
        'job_type' => $jobType,
        'state' => $state,
        'idempotency_key' => Str::uuid()->toString(),
        'queued_at' => now()->subMinutes(5),
    ]);
}

it('GET /api/queue retorna 200 paginado com todos os jobs', function () {
    $cluster = ClusterServer::factory()->create();
    makeJobFixture($cluster->id, 'acme-a', 'running');
    makeJobFixture($cluster->id, 'acme-b', 'success');

    $admin = Operator::factory()->admin()->create();
    $this->actingAs($admin);

    $response = $this->getJson('/api/queue');

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['job_id', 'customer_slug', 'state', 'job_type']],
        'meta' => ['total', 'per_page', 'current_page'],
    ]);
    expect($response->json('meta.total'))->toBe(2);
});

it('GET /api/queue?state=running retorna apenas jobs running', function () {
    $cluster = ClusterServer::factory()->create();
    makeJobFixture($cluster->id, 'acme-a', 'running');
    makeJobFixture($cluster->id, 'acme-b', 'success');

    $this->actingAs(Operator::factory()->admin()->create());

    $response = $this->getJson('/api/queue?state=running');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(1);
    expect($response->json('data.0.state'))->toBe('running');
});

it('GET /api/queue?per_page=200 é limitado a 100', function () {
    $cluster = ClusterServer::factory()->create();
    Customer::firstOrCreate(['slug' => 'bulk-test'], [
        'cluster_server_id' => $cluster->id,
        'domain' => 'bulk-test.example.com',
        'status' => 'active',
    ]);

    for ($i = 0; $i < 5; $i++) {
        Job::create([
            'job_id' => Str::uuid()->toString(),
            'customer_slug' => 'bulk-test',
            'cluster_server_id' => $cluster->id,
            'cmd_canonical' => 'nextcloud-manage bulk-test _ provision',
            'job_type' => 'provision',
            'state' => 'success',
            'idempotency_key' => Str::uuid()->toString(),
            'queued_at' => now()->subMinutes(5),
        ]);
    }

    $this->actingAs(Operator::factory()->admin()->create());

    $response = $this->getJson('/api/queue?per_page=200');

    $response->assertOk();
    expect($response->json('meta.per_page'))->toBe(100);
});

it('GET /api/queue?customer=acme retorna jobs do customer por like', function () {
    $cluster = ClusterServer::factory()->create();
    makeJobFixture($cluster->id, 'acme-prod', 'running');
    makeJobFixture($cluster->id, 'other-co', 'running');

    $this->actingAs(Operator::factory()->admin()->create());

    $response = $this->getJson('/api/queue?customer=acme');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(1);
    expect($response->json('data.0.customer_slug'))->toBe('acme-prod');
});

it('GET /api/queue sem autenticação retorna 401', function () {
    $response = $this->getJson('/api/queue');
    $response->assertUnauthorized();
});
