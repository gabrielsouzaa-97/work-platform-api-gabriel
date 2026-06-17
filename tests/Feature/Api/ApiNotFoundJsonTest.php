<?php

declare(strict_types=1);

it('returns JSON 404 for unknown API route without Accept header', function () {
    $response = $this->get('/api/rota-inexistente');

    $response->assertNotFound();
    $response->assertHeader('Content-Type', 'application/json');
    $response->assertJson([
        'error' => 'route_not_found',
        'path' => '/api/rota-inexistente',
        'method' => 'GET',
    ]);
    expect($response->getContent())->not->toContain('<!DOCTYPE');
});

it('returns JSON 404 for unknown API route with Accept application/json', function () {
    $response = $this->getJson('/api/rota-inexistente');

    $response->assertNotFound();
    $response->assertJson([
        'error' => 'route_not_found',
        'path' => '/api/rota-inexistente',
        'method' => 'GET',
    ]);
});

it('returns JSON 404 for GET /api without Accept header', function () {
    $response = $this->get('/api');

    $response->assertNotFound();
    $response->assertHeader('Content-Type', 'application/json');
    $response->assertJson([
        'error' => 'route_not_found',
        'path' => '/api',
        'method' => 'GET',
    ]);
    expect($response->getContent())->not->toContain('<!DOCTYPE');
});

it('returns HTML 404 for unknown web route', function () {
    $response = $this->get('/rota-inexistente');

    $response->assertNotFound();
    expect($response->headers->get('Content-Type'))->toContain('text/html');
    expect($response->getContent())->toContain('<!DOCTYPE');
});

it('returns JSON 405 for disallowed HTTP method on API route', function () {
    $response = $this->call('GET', '/api/jobs/hook');

    $response->assertStatus(405);
    $response->assertHeader('Content-Type', 'application/json');
    $response->assertJson([
        'error' => 'method_not_allowed',
        'path' => '/api/jobs/hook',
        'method' => 'GET',
    ]);
});

it('returns JSON 405 for disallowed method with Accept text/html header', function () {
    $response = $this->call('GET', '/api/jobs/hook', [], [], [], [
        'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
    ]);

    $response->assertStatus(405);
    $response->assertHeader('Content-Type', 'application/json');
    $response->assertJson([
        'error' => 'method_not_allowed',
        'path' => '/api/jobs/hook',
        'method' => 'GET',
    ]);
    expect($response->getContent())->not->toContain('<!DOCTYPE');
});

it('returns JSON 405 for POST on GET-only API route', function () {
    $response = $this->post('/api/queue');

    $response->assertStatus(405);
    $response->assertHeader('Content-Type', 'application/json');
    $response->assertJson([
        'error' => 'method_not_allowed',
        'path' => '/api/queue',
        'method' => 'POST',
    ]);
});

it('returns DomainError envelope for unknown /api/v1 route', function () {
    $response = $this->getJson('/api/v1/rota-inexistente');

    $response->assertNotFound();
    $response->assertJsonStructure([
        'error' => [
            'code',
            'message',
        ],
    ]);
    expect($response->json('error'))->toBeArray();
    expect($response->json('error.code'))->toBeString()->not->toBeEmpty();
    expect(strtolower($response->getContent()))->not->toContain('"subcmd"')
        ->and(strtolower($response->getContent()))->not->toContain('"exit_code"')
        ->and(strtolower($response->getContent()))->not->toContain('"cmd_canonical"');
});

it('does not leak debug keys in 404/405 JSON when APP_DEBUG is true', function (string $method, string $path, int $status) {
    config(['app.debug' => true]);

    $response = $this->call($method, $path);

    $response->assertStatus($status);
    $response->assertHeader('Content-Type', 'application/json');
    $response->assertJsonMissing(['trace', 'file', 'exception']);
})->with([
    ['GET', '/api/rota-inexistente', 404],
    ['GET', '/api/jobs/hook', 405],
]);
