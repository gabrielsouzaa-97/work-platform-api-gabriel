<?php

declare(strict_types=1);

it('openapi-external.yaml does not document occ routes', function (): void {
    $specPath = dirname(__DIR__, 3).'/docs/openapi-external.yaml';
    $spec = file_get_contents($specPath);
    expect($spec)->not->toBeFalse();

    expect($spec)->not->toMatch('/\\/occ/i');
    expect($spec)->not->toMatch('/customers\\/\\{[^}]+\\}\\/occ/');
});
