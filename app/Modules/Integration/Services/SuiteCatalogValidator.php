<?php

declare(strict_types=1);

namespace App\Modules\Integration\Services;

use Illuminate\Validation\ValidationException;

final class SuiteCatalogValidator
{
    /** @var array<string, mixed>|null */
    private ?array $catalog = null;

    /**
     * @param  list<string>  $apps
     *
     * @throws ValidationException
     */
    public function validateAppIds(array $apps): void
    {
        if ($apps === []) {
            return;
        }

        $catalog = $this->loadCatalog();
        /** @var array<int, array<string, mixed>> $entries */
        $entries = $catalog['apps'] ?? [];
        $known = [];
        foreach ($entries as $entry) {
            $appId = $entry['app_id'] ?? null;
            if (is_string($appId) && $appId !== '') {
                $known[$appId] = $entry;
            }
        }

        $errors = [];
        foreach ($apps as $index => $appId) {
            if (! is_string($appId) || $appId === '') {
                continue;
            }

            $entry = $known[$appId] ?? null;
            if ($entry === null) {
                $errors["apps.{$index}"] = "Unknown suite catalog app_id: {$appId}";

                continue;
            }

            if (($entry['status'] ?? '') !== 'active') {
                $status = is_string($entry['status'] ?? null) ? $entry['status'] : 'unknown';
                $errors["apps.{$index}"] = "App '{$appId}' is not active in suite catalog (status: {$status}).";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function loadCatalog(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }

        $path = config('platform.suite_catalog.path');
        if (! is_string($path) || ! is_readable($path)) {
            throw ValidationException::withMessages([
                'suite_catalog' => 'Suite catalog JSON is not readable.',
            ]);
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'suite_catalog' => 'Suite catalog JSON is invalid.',
            ]);
        }

        $this->catalog = $decoded;

        return $this->catalog;
    }
}
