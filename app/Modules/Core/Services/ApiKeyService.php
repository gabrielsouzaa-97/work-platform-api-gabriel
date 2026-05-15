<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\Operator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ApiKeyService
{
    /**
     * Generate a new API key and return both the model and the raw token.
     * The raw token is only returned here and never stored — store it securely.
     *
     * @return array{apiKey: ApiKey, rawToken: string}
     */
    public function generate(string $name, ?array $scopes, Operator $actor): array
    {
        $rawToken = 'sk_'.bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $apiKey = DB::transaction(function () use ($name, $scopes, $tokenHash, $actor): ApiKey {
            $key = ApiKey::create([
                'id' => Str::uuid()->toString(),
                'operator_id' => $actor->id,
                'name' => $name,
                'token_hash' => $tokenHash,
                'scopes' => $scopes ?: null,
            ]);

            AuditLog::create([
                'id' => Str::uuid()->toString(),
                'actor_id' => $actor->id,
                'action' => 'api_key.create',
                'resource_type' => 'api_key',
                'resource_id' => $key->id,
                'payload' => ['name' => $name, 'scopes' => $scopes],
            ]);

            return $key;
        });

        return ['apiKey' => $apiKey, 'rawToken' => $rawToken];
    }

    /**
     * Revoke an existing API key by setting revoked_at.
     *
     * @throws \DomainException if already revoked or not found
     */
    public function revoke(string $id, Operator $actor): void
    {
        $key = ApiKey::findOrFail($id);

        if ($key->revoked_at !== null) {
            throw new \DomainException("API key [{$id}] is already revoked.");
        }

        DB::transaction(function () use ($key, $actor): void {
            $key->update(['revoked_at' => now()]);

            AuditLog::create([
                'id' => Str::uuid()->toString(),
                'actor_id' => $actor->id,
                'action' => 'api_key.revoke',
                'resource_type' => 'api_key',
                'resource_id' => $key->id,
                'payload' => ['name' => $key->name],
            ]);
        });
    }

    /**
     * List API keys with optional status filter, ordered newest first.
     */
    public function list(string $filterStatus = '', int $perPage = 20): LengthAwarePaginator
    {
        return ApiKey::query()
            ->when($filterStatus === 'active', fn ($q) => $q->whereNull('revoked_at'))
            ->when($filterStatus === 'revoked', fn ($q) => $q->whereNotNull('revoked_at'))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
