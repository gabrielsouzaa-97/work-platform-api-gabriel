<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D8 Polish — DBA findings F003–F009
 *
 * F003: remove redundant index on operators.email (UNIQUE already covers it)
 * F004: remove redundant index on api_keys.token_hash (UNIQUE already covers it)
 * F005: composite index audit_logs(resource_type, resource_id, created_at)
 * F006: composite index jobs(state, queued_at) for poll-stuck command
 * F007: composite index jobs(state, created_at) for pagination ORDER BY
 * F008: pg_trgm GIN indexes for LIKE '%...%' searches
 * F009: composite index webhook_secret_history(cluster_server_id, valid_until)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── F003: drop redundant index on operators.email ─────────────────────
        Schema::table('operators', function (Blueprint $table): void {
            if ($this->indexExists('operators', 'idx_operators_email')) {
                $table->dropIndex('idx_operators_email');
            }
        });

        // ── F004: drop redundant index on api_keys.token_hash ────────────────
        Schema::table('api_keys', function (Blueprint $table): void {
            if ($this->indexExists('api_keys', 'idx_api_keys_token_hash')) {
                $table->dropIndex('idx_api_keys_token_hash');
            }
        });

        // ── F005: composite index on audit_logs ───────────────────────────────
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->index(['resource_type', 'resource_id', 'created_at'], 'idx_audit_logs_rtype_rid_cat');
        });

        // ── F006 + F007: composite indexes on jobs ────────────────────────────
        Schema::table('jobs', function (Blueprint $table): void {
            $table->index(['state', 'queued_at'], 'idx_jobs_state_queued_at');
            $table->index(['state', 'created_at'], 'idx_jobs_state_created_at');
        });

        // ── F008: pg_trgm GIN indexes for LIKE '%...%' ────────────────────────
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_audit_logs_action_trgm ON audit_logs USING gin (action gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_jobs_customer_slug_trgm ON jobs USING gin (customer_slug gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_customers_slug_trgm ON customers USING gin (slug gin_trgm_ops)');

        // ── F009: composite index on webhook_secret_history ───────────────────
        Schema::table('webhook_secret_history', function (Blueprint $table): void {
            $table->index(['cluster_server_id', 'valid_until'], 'idx_wsh_cluster_valid_until');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_secret_history', function (Blueprint $table): void {
            $table->dropIndex('idx_wsh_cluster_valid_until');
        });

        DB::statement('DROP INDEX IF EXISTS idx_customers_slug_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_jobs_customer_slug_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_audit_logs_action_trgm');

        Schema::table('jobs', function (Blueprint $table): void {
            $table->dropIndex('idx_jobs_state_created_at');
            $table->dropIndex('idx_jobs_state_queued_at');
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('idx_audit_logs_rtype_rid_cat');
        });

        // Restore dropped indexes if needed (idempotent — only for rollback completeness)
        Schema::table('api_keys', function (Blueprint $table): void {
            $table->index('token_hash', 'idx_api_keys_token_hash');
        });

        Schema::table('operators', function (Blueprint $table): void {
            $table->index('email', 'idx_operators_email');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::selectOne(
            'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?',
            [$table, $indexName]
        );

        return $result !== null;
    }
};
