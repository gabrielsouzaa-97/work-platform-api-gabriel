<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class DocsController extends Controller
{
    public function api(): View
    {
        Gate::authorize('manage-operators');

        return view('docs.api', [
            'specUrl' => route('docs.api.spec'),
            'apiBaseUrl' => url('/api/v1'),
            'specVersion' => $this->resolveSpecVersion(),
        ]);
    }

    private function resolveSpecPath(): string
    {
        $configuredPath = config('platform.openapi.external_spec_path');
        $devPath = base_path('docs/openapi-external.yaml');

        if (is_string($configuredPath) && is_readable($configuredPath)) {
            return $configuredPath;
        }

        return $devPath;
    }

    private function resolveSpecVersion(): string
    {
        $path = $this->resolveSpecPath();

        if (! is_readable($path)) {
            return 'unknown';
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return 'unknown';
        }

        if (! preg_match('/^info:\R(?:  .+\R)*?  version:\s*(\S+)/m', $contents, $matches)) {
            return 'unknown';
        }

        return $matches[1];
    }

    public function spec(): Response
    {
        Gate::authorize('manage-operators');

        $path = $this->resolveSpecPath();

        if (! is_readable($path)) {
            abort(404, 'OpenAPI specification not found.');
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            abort(500, 'Unable to read OpenAPI specification.');
        }

        return response($contents, 200, [
            'Content-Type' => 'application/yaml; charset=UTF-8',
        ]);
    }
}
