<?php

declare(strict_types=1);

use App\Http\Livewire\ClusterServers\Index;
use App\Mail\WebhookSecretRotatedMail;
use App\Models\ClusterServer;
use App\Models\Operator;
use App\Models\WebhookSecretHistory;
use App\Modules\ClusterServers\Services\WebhookSecretValidator;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

it('rotate secret cria versão N+1, expira versão N com valid_until, envia email', function () {
    Mail::fake();

    $admin = Operator::factory()->admin()->create();
    $cluster = ClusterServer::factory()->create(['webhook_secret_version' => 1]);

    WebhookSecretHistory::create([
        'cluster_server_id' => $cluster->id,
        'secret_encrypted' => 'original-secret',
        'version' => 1,
        'valid_from' => now()->subHour(),
        'valid_until' => null,
    ]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('rotateSecret', $cluster->id)
        ->assertDispatched('toast', type: 'success');

    $cluster->refresh();
    expect($cluster->webhook_secret_version)->toBe(2);

    $history = WebhookSecretHistory::where('cluster_server_id', $cluster->id)
        ->orderBy('version')
        ->get();

    expect($history)->toHaveCount(2);
    expect($history[0]->valid_until)->not->toBeNull();
    expect($history[1]->valid_until)->toBeNull();
    expect($history[1]->version)->toBe(2);

    Mail::assertQueued(WebhookSecretRotatedMail::class);
});

it('webhook receiver aceita ambos os secrets durante grace period', function () {
    $cluster = ClusterServer::factory()->create(['webhook_secret_version' => 1]);

    $oldSecret = 'old-secret-plain';
    $newSecret = 'new-secret-plain';

    WebhookSecretHistory::create([
        'cluster_server_id' => $cluster->id,
        'secret_encrypted' => $oldSecret,
        'version' => 1,
        'valid_from' => now()->subHours(2),
        'valid_until' => now()->addHours(23),
    ]);

    WebhookSecretHistory::create([
        'cluster_server_id' => $cluster->id,
        'secret_encrypted' => $newSecret,
        'version' => 2,
        'valid_from' => now(),
        'valid_until' => null,
    ]);

    $validator = app(WebhookSecretValidator::class);
    $body = '{"job_id":"test"}';

    $sigOld = 'sha256='.hash_hmac('sha256', $body, $oldSecret);
    $sigNew = 'sha256='.hash_hmac('sha256', $body, $newSecret);

    expect($validator->valid($cluster, $sigOld, $body))->toBeTrue();
    expect($validator->valid($cluster, $sigNew, $body))->toBeTrue();
});

it('webhook receiver rejeita secret expirado após grace period', function () {
    $cluster = ClusterServer::factory()->create(['webhook_secret_version' => 2]);

    $oldSecret = 'expired-secret';
    $newSecret = 'active-secret';

    WebhookSecretHistory::create([
        'cluster_server_id' => $cluster->id,
        'secret_encrypted' => $oldSecret,
        'version' => 1,
        'valid_from' => now()->subHours(48),
        'valid_until' => now()->subHours(24),
    ]);

    WebhookSecretHistory::create([
        'cluster_server_id' => $cluster->id,
        'secret_encrypted' => $newSecret,
        'version' => 2,
        'valid_from' => now()->subHours(24),
        'valid_until' => null,
    ]);

    $validator = app(WebhookSecretValidator::class);
    $body = '{"job_id":"test"}';

    $sigExpired = 'sha256='.hash_hmac('sha256', $body, $oldSecret);
    $sigActive = 'sha256='.hash_hmac('sha256', $body, $newSecret);

    expect($validator->valid($cluster, $sigExpired, $body))->toBeFalse();
    expect($validator->valid($cluster, $sigActive, $body))->toBeTrue();
});

it('cron clean remove registros expirados há mais de 30 dias mas mantém os em grace', function () {
    $cluster = ClusterServer::factory()->create();

    $oldExpired = WebhookSecretHistory::create([
        'cluster_server_id' => $cluster->id,
        'secret_encrypted' => 'very-old',
        'version' => 1,
        'valid_from' => now()->subDays(60),
        'valid_until' => now()->subDays(31),
    ])->fresh();

    $graceRecord = WebhookSecretHistory::create([
        'cluster_server_id' => $cluster->id,
        'secret_encrypted' => 'in-grace',
        'version' => 2,
        'valid_from' => now()->subHours(1),
        'valid_until' => now()->addHours(23),
    ])->fresh();

    $current = WebhookSecretHistory::create([
        'cluster_server_id' => $cluster->id,
        'secret_encrypted' => 'current',
        'version' => 3,
        'valid_from' => now(),
        'valid_until' => null,
    ])->fresh();

    $this->artisan('webhook-secrets:clean')->assertSuccessful();

    expect(WebhookSecretHistory::find($oldExpired->id))->toBeNull();
    expect(WebhookSecretHistory::find($graceRecord->id))->not->toBeNull();
    expect(WebhookSecretHistory::find($current->id))->not->toBeNull();
});

it('operador comum não pode chamar rotateSecret', function () {
    $operador = Operator::factory()->create(['role' => 'operador']);
    $cluster = ClusterServer::factory()->create();

    WebhookSecretHistory::create([
        'cluster_server_id' => $cluster->id,
        'secret_encrypted' => 'secret',
        'version' => 1,
        'valid_from' => now(),
        'valid_until' => null,
    ]);

    Livewire::actingAs($operador)
        ->test(Index::class)
        ->call('rotateSecret', $cluster->id)
        ->assertForbidden();
});
