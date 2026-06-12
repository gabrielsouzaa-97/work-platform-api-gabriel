<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->sessionsUserIdForeignExists()) {
            Schema::table('sessions', function (Blueprint $table): void {
                $table->foreign('user_id')
                    ->references('id')
                    ->on('operators')
                    ->cascadeOnDelete();
            });
        }

        if (! $this->operatorsInviteTokenHashUniqueExists()) {
            Schema::table('operators', function (Blueprint $table): void {
                $table->unique('invite_token_hash');
            });
        }
    }

    public function down(): void
    {
        if ($this->sessionsUserIdForeignExists()) {
            Schema::table('sessions', function (Blueprint $table): void {
                $table->dropForeign(['user_id']);
            });
        }

        if ($this->operatorsInviteTokenHashUniqueExists()) {
            Schema::table('operators', function (Blueprint $table): void {
                $table->dropUnique(['invite_token_hash']);
            });
        }
    }

    private function sessionsUserIdForeignExists(): bool
    {
        return collect(Schema::getConnection()->getSchemaBuilder()->getForeignKeys('sessions'))
            ->contains(fn (array $fk): bool => in_array('user_id', $fk['columns'], true)
                && ($fk['foreign_table'] ?? '') === 'operators');
    }

    private function operatorsInviteTokenHashUniqueExists(): bool
    {
        return collect(Schema::getConnection()->getSchemaBuilder()->getIndexes('operators'))
            ->contains(fn (array $index): bool => ($index['unique'] ?? false) === true
                && in_array('invite_token_hash', $index['columns'] ?? [], true));
    }
};
