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
