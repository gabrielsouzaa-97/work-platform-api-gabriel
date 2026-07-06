<?php

declare(strict_types=1);

use App\Http\Livewire\Customers\Create;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Operator;
use App\Models\Plan;
use App\Modules\ClusterServers\Actions\SyncWebhookSecretAction;
use App\Modules\ClusterServers\Services\WebhookSecretGenerator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Str;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

function planSelectCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function planSelectAdmin(): Operator
{
    return Operator::factory()->admin()->create();
}

it('customer create page renders plan select with available plans', function (): void {
    $admin = planSelectAdmin();
    $cluster = planSelectCluster();

    Plan::create([
        'slug' => 'basic',
        'name' => 'Basic',
        'default_quota' => '5 GB',
        'is_default' => true,
        'status' => 'active',
    ]);

    Plan::create([
        'slug' => 'enterprise',
        'name' => 'Enterprise',
        'default_quota' => '50 GB',
        'is_default' => false,
        'status' => 'active',
    ]);

    actingAs($admin)
        ->get(route('customers.create'))
        ->assertOk()
        ->assertSee('Basic')
        ->assertSee('Enterprise');

    Livewire::actingAs($admin)
        ->test(Create::class)
        ->assertSet('clusterServerId', null)
        ->assertSee('Basic')
        ->assertSee('enterprise');
});

it('customer create persists selected plan_slug on save', function (): void {
    $admin = planSelectAdmin();
    $cluster = planSelectCluster();
    $jobId = Str::uuid()->toString();
    $slug = 'cust-plan-'.substr(uniqid(), -6);

    Plan::create([
        'slug' => 'pro',
        'name' => 'Pro',
        'default_quota' => '20 GB',
        'is_default' => false,
        'status' => 'active',
    ]);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->andReturn(new SshResponse(
            stdout: json_encode(['job_id' => $jobId]),
            stderr: '',
            exitCode: 0,
            parsedJson: ['job_id' => $jobId],
        ));
    app()->instance(SshClientInterface::class, $ssh);

    $component = Livewire::actingAs($admin)
        ->test(Create::class)
        ->set('slug', $slug)
        ->set('clusterServerId', $cluster->id)
        ->set('domain', "{$slug}.example.com")
        ->set('planSlug', 'pro');

    $component->instance()->save(
        app(WebhookSecretGenerator::class),
        app(SyncWebhookSecretAction::class),
    );

    expect(Customer::find($slug)?->plan_slug)->toBe('pro');
});

it('customer create defaults plan select to platform default plan', function (): void {
    $admin = planSelectAdmin();

    Plan::create([
        'slug' => 'default',
        'name' => 'Default',
        'default_quota' => '5 GB',
        'is_default' => true,
        'status' => 'active',
    ]);

    Livewire::actingAs($admin)
        ->test(Create::class)
        ->assertSet('planSlug', 'default');
});
