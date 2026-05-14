<?php

declare(strict_types=1);

use App\Console\Commands\AuditPurgeCommand;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

function makeAuditLog(string $createdAt): AuditLog
{
    return AuditLog::create([
        'id' => Str::uuid()->toString(),
        'actor_id' => null,
        'action' => 'test_action',
        'resource_type' => 'customer',
        'resource_id' => 'test-slug',
        'payload' => [],
        'created_at' => $createdAt,
    ]);
}

// Cenário 1: Cron processa logs > 12m, mantém os dentro do range

it('deleta logs com created_at > 12 meses e preserva os recentes', function () {
    $old = makeAuditLog(now()->subMonths(13)->toDateTimeString());
    $recent = makeAuditLog(now()->subMonths(6)->toDateTimeString());

    $this->artisan(AuditPurgeCommand::class)
        ->assertExitCode(0)
        ->expectsOutputToContain('1 registro(s) removidos');

    expect(AuditLog::find($old->id))->toBeNull();
    expect(AuditLog::find($recent->id))->not->toBeNull();
});

// Cenário 2: --dry-run não deleta, exibe contagem

it('--dry-run exibe contagem sem deletar registros', function () {
    $old = makeAuditLog(now()->subMonths(13)->toDateTimeString());

    $output = Artisan::call('audit:purge', ['--dry-run' => true]);
    $outputText = Artisan::output();

    expect($output)->toBe(0);
    expect($outputText)->toContain('1 registro(s) com created_at anterior');
    expect($outputText)->toContain('seriam removidos');

    expect(AuditLog::find($old->id))->not->toBeNull();
});

// Cenário 3: Tabela vazia → no-op sem erro

it('tabela vazia → no-op sem erro', function () {
    $this->artisan(AuditPurgeCommand::class)
        ->assertExitCode(0)
        ->expectsOutputToContain('0 registro(s) removidos');
});
