<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\FarmAgent;
use App\Models\Operator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Config::set('services.agent.transport_enabled', true);
});

function customAppsUpdateUrl(FarmAgent $agent): string
{
    return '/api/farm-agents/'.$agent->id.'/custom-apps/update';
}

it('dispatches custom-apps.update with ring canary and returns 202 with operation_id', function (): void {
    $admin = Operator::factory()->admin()->create();
    $agent = FarmAgent::factory()->create();

    $response = $this->actingAs($admin)->postJson(customAppsUpdateUrl($agent), [
        'ring' => 'canary',
        'app_id' => 'mework360_memail',
        'json' => true,
    ]);

    $response->assertAccepted()
        ->assertJsonPath('operation', 'custom-apps.update')
        ->assertJsonStructure(['operation_id']);

    expect($response->json('operation_id'))->not->toBeEmpty()
        ->and(Str::isUuid($response->json('operation_id')))->toBeTrue();
});

it('dispatches custom-apps.update with tenant slug and returns 202', function (): void {
    $admin = Operator::factory()->admin()->create();
    $agent = FarmAgent::factory()->create();

    $this->actingAs($admin)
        ->postJson(customAppsUpdateUrl($agent), [
            'tenant' => 'qa-platform-lab-001',
            'json' => true,
        ])
        ->assertAccepted()
        ->assertJsonPath('operation', 'custom-apps.update')
        ->assertJsonStructure(['operation_id']);
});

it('rejects ring and tenant together with 422', function (): void {
    $admin = Operator::factory()->admin()->create();
    $agent = FarmAgent::factory()->create();

    $this->actingAs($admin)
        ->postJson(customAppsUpdateUrl($agent), [
            'ring' => 'canary',
            'tenant' => 'qa-platform-lab-001',
            'json' => true,
        ])
        ->assertUnprocessable();
});

it('creates audit log with ring operator and operation_id on dispatch', function (): void {
    $admin = Operator::factory()->admin()->create();
    $agent = FarmAgent::factory()->create();

    $response = $this->actingAs($admin)->postJson(customAppsUpdateUrl($agent), [
        'ring' => 'canary',
        'json' => true,
    ]);

    $response->assertAccepted();
    $operationId = $response->json('operation_id');

    $log = AuditLog::where('action', 'farm_agent.custom_apps_update')
        ->where('resource_type', 'farm_agent')
        ->where('resource_id', $agent->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($admin->id)
        ->and($log->payload['ring'])->toBe('canary')
        ->and($log->payload['operation_id'])->toBe($operationId);
});

it('drill flow dispatches canary then stable sequentially with distinct operation_ids', function (): void {
    $admin = Operator::factory()->admin()->create();
    $agent = FarmAgent::factory()->create();

    $canaryResponse = $this->actingAs($admin)->postJson(customAppsUpdateUrl($agent), [
        'ring' => 'canary',
        'json' => true,
    ]);

    $canaryResponse->assertAccepted()
        ->assertJsonPath('operation', 'custom-apps.update');

    $stableResponse = $this->actingAs($admin)->postJson(customAppsUpdateUrl($agent), [
        'ring' => 'stable',
        'json' => true,
    ]);

    $stableResponse->assertAccepted()
        ->assertJsonPath('operation', 'custom-apps.update');

    $canaryOperationId = $canaryResponse->json('operation_id');
    $stableOperationId = $stableResponse->json('operation_id');

    expect($canaryOperationId)->not->toBeEmpty()
        ->and($stableOperationId)->not->toBeEmpty()
        ->and($canaryOperationId)->not->toBe($stableOperationId);
});
