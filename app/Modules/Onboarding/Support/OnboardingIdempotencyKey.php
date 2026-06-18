<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Support;

use App\Models\Onboarding;

final class OnboardingIdempotencyKey
{
    /**
     * @param  array{slug: string, domain: string, admin_username: string}  $payload
     */
    public static function hash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public static function findRecentReplay(string $idempotencyKey): ?Onboarding
    {
        return Onboarding::query()
            ->where('idempotency_key', $idempotencyKey)
            ->where('created_at', '>=', now()->subHours(24))
            ->first();
    }
}
