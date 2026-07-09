<?php

declare(strict_types=1);

use App\Http\Livewire\Customers\Create;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Operator;
use App\Modules\ClusterServers\Actions\SyncWebhookSecretAction;
use App\Modules\ClusterServers\Services\WebhookSecretGenerator;
use App\Modules\Core\Ssh\SshClientInterface;
use Livewire\Livewire;

beforeEach(function (): void {
    config([
        'platform.suite_catalog.path' => base_path('tests/fixtures/suite_catalog.json'),
        'platform.suite_catalog.default_mode' => true,
        'platform.image_mode.default_mode' => false,
    ]);
});

function createReadinessAdmin(): Operator
{
    return Operator::factory()->admin()->create();
}

function createReadinessCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

it('customer create rejects legacy impossible payload before provision', function (): void {
    $admin = createReadinessAdmin();
    $cluster = createReadinessCluster();
    $slug = 'lw-legacy-'.substr(uniqid(), -6);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    $ssh->shouldNotReceive('run');
    app()->instance(SshClientInterface::class, $ssh);

    $component = Livewire::actingAs($admin)
        ->test(Create::class)
        ->set('slug', $slug)
        ->set('clusterServerId', $cluster->id)
        ->set('domain', "{$slug}.example.com")
        ->set('selectedAppIds', ['mail']);

    $component->call(
        'save',
        app(WebhookSecretGenerator::class),
        app(SyncWebhookSecretAction::class),
    );

    expect(Customer::find($slug))->toBeNull()
        ->and(Job::count())->toBe(0);

    $errors = $component->errors();
    $encoded = json_encode($errors->getMessages(), JSON_THROW_ON_ERROR);

    expect($encoded)
        ->toContain('LEGACY_READINESS_UNSATISFIABLE')
        ->and($encoded)->toMatch('/image_mode/i');
});
