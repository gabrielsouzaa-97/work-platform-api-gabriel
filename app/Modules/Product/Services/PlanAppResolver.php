<?php

declare(strict_types=1);

namespace App\Modules\Product\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PlanAppResolver
{
    /**
     * @param  list<string>|null  $explicitApps
     * @return list<string>
     *
     * @throws ValidationException
     */
    public function resolve(?string $planSlug, ?array $explicitApps): array
    {
        if ($planSlug === null || $planSlug === '') {
            return $explicitApps ?? [];
        }

        $allowedAppIds = $this->allowedAppIdsForPlan($planSlug);
        $apps = $explicitApps ?? [];

        if ($apps === []) {
            return $allowedAppIds;
        }

        $this->validateSubset($apps, $allowedAppIds);

        return $apps;
    }

    /**
     * @return list<string>
     */
    private function allowedAppIdsForPlan(string $planSlug): array
    {
        return DB::table('plan_apps')
            ->join('app_catalog_entries', 'app_catalog_entries.id', '=', 'plan_apps.app_catalog_id')
            ->where('plan_apps.plan_slug', $planSlug)
            ->orderBy('app_catalog_entries.app_id')
            ->pluck('app_catalog_entries.app_id')
            ->all();
    }

    /**
     * @param  list<string>  $apps
     * @param  list<string>  $allowed
     *
     * @throws ValidationException
     */
    private function validateSubset(array $apps, array $allowed): void
    {
        $allowedSet = array_flip($allowed);
        $errors = [];

        foreach ($apps as $appId) {
            if (! is_string($appId) || $appId === '') {
                continue;
            }

            if (! isset($allowedSet[$appId])) {
                $errors['apps'] = ["App '{$appId}' is not allowed for the selected plan."];

                break;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
