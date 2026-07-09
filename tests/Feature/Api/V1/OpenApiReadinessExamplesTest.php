<?php

declare(strict_types=1);

it('openapi-external minimal tenant example references image_mode for readiness', function (): void {
    $specPath = base_path('docs/openapi-external.yaml');
    $spec = file_get_contents($specPath);

    expect($spec)->not->toBeFalse();

    preg_match(
        '/\/api\/v1\/tenants:[\s\S]*?examples:\s*\n\s*minimal:[\s\S]*?value:\s*\n((?:\s+.+\n)+)/',
        $spec,
        $matches,
    );

    expect($matches)->not->toBeEmpty('minimal example block under POST /api/v1/tenants not found');

    $minimalBlock = $matches[1];

    expect($minimalBlock)->toMatch('/image_mode:\s*true/');
});
