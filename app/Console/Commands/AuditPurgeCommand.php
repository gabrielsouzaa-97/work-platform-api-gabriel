<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AuditPurgeCommand extends Command
{
    protected $signature = 'audit:purge
                            {--retention-months=12 : Meses de retenção}
                            {--dry-run : Exibe contagem sem deletar}
                            {--max=100000 : Limite de registros por execução}';

    protected $description = 'Remove audit_logs com created_at anterior ao período de retenção (LGPD)';

    public function handle(): int
    {
        $months = (int) $this->option('retention-months');
        $max = (int) $this->option('max');
        $cutoff = now()->subMonths($months);

        $query = AuditLog::where('created_at', '<', $cutoff);

        if ($this->option('dry-run')) {
            $count = $query->count();
            $this->info("audit:purge --dry-run: {$count} registro(s) com created_at anterior a {$cutoff->toIso8601String()} seriam removidos.");

            return self::SUCCESS;
        }

        $deleted = 0;

        $query->chunkById(1000, function ($chunk) use (&$deleted, $max): bool {
            $ids = $chunk->pluck('id')->take($max - $deleted)->all();

            if (empty($ids)) {
                return false;
            }

            AuditLog::whereIn('id', $ids)->delete();
            $deleted += count($ids);

            usleep(100_000); // 100 ms entre chunks para reduzir lock contention

            return $deleted < $max;
        }, 'id');

        $this->info("audit:purge: {$deleted} registro(s) removidos (cutoff={$cutoff->toIso8601String()}).");
        Log::info('audit:purge', [
            'deleted' => $deleted,
            'cutoff' => $cutoff->toIso8601String(),
            'retention_months' => $months,
        ]);

        return self::SUCCESS;
    }
}
