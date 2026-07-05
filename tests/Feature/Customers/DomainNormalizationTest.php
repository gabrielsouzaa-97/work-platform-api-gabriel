<?php

declare(strict_types=1);

use App\Http\Livewire\Customers\Create;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Str;
use Livewire\Livewire;

function domainNormCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function domainNormOperator(): Operator
{
    return Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
}

function domainNormSshSuccess(string $jobId): SshResponse
{
    return new SshResponse(
        stdout: json_encode(['job_id' => $jobId]),
        stderr: '',
        exitCode: 0,
        parsedJson: ['job_id' => $jobId],
    );
}

function mockDomainNormProvision(string $jobId): void
{
    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('runAsync')->once()->andReturn(domainNormSshSuccess($jobId));
    app()->instance(SshClientInterface::class, $sshMock);
}

function mockDomainNormProvisionBlocked(): void
{
    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $sshMock);
}

it('accepts lowercase FQDN and persists normalized domain (N39.1 scenario 1)', function () {
    $cluster = domainNormCluster();
    $operator = domainNormOperator();
    $jobId = Str::uuid()->toString();
    $slug = 'pacoteste-'.substr(uniqid(), -6);
    $domain = 'pacoteste.image-pilot.mework360.com.br';

    mockDomainNormProvision($jobId);

    $response = test()->actingAs($operator)->postJson('/api/customers', [
        'slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'domain' => $domain,
    ]);

    $response->assertStatus(201);
    expect(Customer::find($slug)?->domain)->toBe($domain);
    expect(Job::find($jobId))->not->toBeNull();
});

it('normalizes uppercase FQDN with trailing slash to lowercase without slash (N39.1 scenario 2)', function () {
    $cluster = domainNormCluster();
    $operator = domainNormOperator();
    $jobId = Str::uuid()->toString();
    $slug = 'pacoteste-slash-'.substr(uniqid(), -6);
    $expected = 'pacoteste.image-pilot.mework360.com.br';

    mockDomainNormProvision($jobId);

    $response = test()->actingAs($operator)->postJson('/api/customers', [
        'slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'domain' => 'Pacoteste.Image-Pilot.MeWork360.Com.Br/',
    ]);

    $response->assertSuccessful();
    expect(Customer::find($slug)?->domain)->toBe($expected);
});

it('rejects FQDN with protocol with 422 before SSH (N39.1 scenario 3)', function () {
    $cluster = domainNormCluster();
    $operator = domainNormOperator();

    mockDomainNormProvisionBlocked();

    $response = test()->actingAs($operator)->postJson('/api/customers', [
        'slug' => 'proto-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'https://foo.bar',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['domain']);
});

it('rejects FQDN without TLD with 422 before SSH (N39.1 scenario 4)', function () {
    $cluster = domainNormCluster();
    $operator = domainNormOperator();

    mockDomainNormProvisionBlocked();

    $response = test()->actingAs($operator)->postJson('/api/customers', [
        'slug' => 'notld-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'foo',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['domain']);
});

it('regression: valid provision with standard domain still succeeds (N39.1 scenario 5)', function () {
    $cluster = domainNormCluster();
    $operator = domainNormOperator();
    $jobId = Str::uuid()->toString();
    $slug = 'acme-regression-'.substr(uniqid(), -6);

    mockDomainNormProvision($jobId);

    $response = test()->actingAs($operator)->postJson('/api/customers', [
        'slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'domain' => 'acme-regression.example.com',
    ]);

    $response->assertStatus(201);
    expect(Customer::find($slug)?->domain)->toBe('acme-regression.example.com');
    expect(Job::find($jobId))->not->toBeNull();
});

it('Livewire Create exposes normalizedDomain preview after domain input', function () {
    $operator = domainNormOperator();

    Livewire::actingAs($operator)
        ->test(Create::class)
        ->set('domain', 'Pacoteste.Image-Pilot.MeWork360.Com.Br/')
        ->assertSet('normalizedDomain', 'pacoteste.image-pilot.mework360.com.br');
});
