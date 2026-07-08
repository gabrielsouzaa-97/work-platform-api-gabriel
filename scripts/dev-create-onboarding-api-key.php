<?php

declare(strict_types=1);

use App\Models\Operator;
use App\Modules\Core\Services\ApiKeyService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$operator = Operator::query()->where('status', 'active')->orderBy('id')->first();

if ($operator === null) {
    fwrite(STDERR, "No active operator found. Create one via the panel first.\n");
    exit(1);
}

$scopes = array_values(array_unique(array_merge(
    config('api-scopes.v1', []),
    config('api-scopes.legacy', []),
)));

/** @var ApiKeyService $service */
$service = app(ApiKeyService::class);
$result = $service->generate('onboarding-local-dev', $scopes, $operator);

echo $result['rawToken'].PHP_EOL;
