<?php

declare(strict_types=1);

use App\Models\Operator;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('shows api docs sidebar link only for manage-operators', function () {
    $admin = Operator::factory()->admin()->create();
    $operador = Operator::factory()->create(['role' => 'operador']);

    actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Documentação API', false)
        ->assertSee(route('docs.api'), false);

    actingAs($operador)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertDontSee('Documentação API', false);
});

it('admin with manage-operators can view api docs page and spec', function () {
    $admin = Operator::factory()->admin()->create();

    actingAs($admin)
        ->get(route('docs.api'))
        ->assertOk()
        ->assertSee('id="api-docs"', false)
        ->assertSee(config('app.env'), false)
        ->assertSee('1.0.0-draft', false);

    actingAs($admin)
        ->get(route('docs.api.spec'))
        ->assertOk()
        ->assertHeader('content-type', 'application/yaml; charset=UTF-8')
        ->assertSee('openapi: 3.0.3', false);
});

it('anonymous users are redirected to login for api docs routes', function () {
    get(route('docs.api'))
        ->assertRedirect(route('login'));

    get(route('docs.api.spec'))
        ->assertRedirect(route('login'));
});

it('authenticated operator without manage-operators receives 403 on api docs routes', function () {
    $operador = Operator::factory()->create(['role' => 'operador']);

    actingAs($operador)
        ->get(route('docs.api'))
        ->assertForbidden();

    actingAs($operador)
        ->get(route('docs.api.spec'))
        ->assertForbidden();
});

it('api spec does not expose internal occ paths from openapi.yaml', function () {
    $admin = Operator::factory()->admin()->create();

    actingAs($admin)
        ->get(route('docs.api.spec'))
        ->assertOk()
        ->assertDontSee('/occ/', false);
});

it('api spec is served from configured production storage path', function () {
    $admin = Operator::factory()->admin()->create();
    $devPath = base_path('docs/openapi-external.yaml');
    $productionPath = storage_path('app/testing-openapi-external.yaml');

    $this->assertFileIsReadable($devPath);

    copy($devPath, $productionPath);

    config()->set('platform.openapi.external_spec_path', $productionPath);

    actingAs($admin)
        ->get(route('docs.api.spec'))
        ->assertOk()
        ->assertHeader('content-type', 'application/yaml; charset=UTF-8')
        ->assertSee('openapi: 3.0.3', false);

    @unlink($productionPath);
});

it('existing panel routes remain accessible after api docs routes are registered', function () {
    $admin = Operator::factory()->admin()->create();

    actingAs($admin)
        ->get(route('api-keys.index'))
        ->assertOk();
});
