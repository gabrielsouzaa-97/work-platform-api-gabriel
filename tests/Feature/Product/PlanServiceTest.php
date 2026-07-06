<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Modules\Product\Services\PlanService;
use Illuminate\Support\Facades\DB;

function planService(): PlanService
{
    return app(PlanService::class);
}

function planServicePayload(string $slug, bool $isDefault = false): array
{
    return [
        'slug' => $slug,
        'name' => strtoupper($slug),
        'default_quota' => '5 GB',
        'is_default' => $isDefault,
        'status' => 'active',
        'app_ids' => [],
    ];
}

it('creates plan via PlanService and persists row', function (): void {
    $slug = 'svc-'.substr(uniqid(), -6);

    $plan = planService()->create(planServicePayload($slug));

    expect($plan->slug)->toBe($slug);
    $this->assertDatabaseHas('plans', ['slug' => $slug, 'default_quota' => '5 GB']);
});

it('enforces single is_default when creating a new default plan', function (): void {
    planService()->create(planServicePayload('alpha', isDefault: true));
    planService()->create(planServicePayload('beta', isDefault: true));

    expect(Plan::where('is_default', true)->count())->toBe(1);
    expect(Plan::find('alpha')?->is_default)->toBeFalse();
    expect(Plan::find('beta')?->is_default)->toBeTrue();
});

it('setAsDefault swaps default atomically inside a transaction', function (): void {
    planService()->create(planServicePayload('one', isDefault: true));
    planService()->create(planServicePayload('two', isDefault: false));

    planService()->setAsDefault('two');

    expect(Plan::where('is_default', true)->count())->toBe(1);
    expect(Plan::find('one')?->is_default)->toBeFalse();
    expect(Plan::find('two')?->is_default)->toBeTrue();
});

it('rapid default swaps keep exactly one default plan', function (): void {
    planService()->create(planServicePayload('a', isDefault: true));
    planService()->create(planServicePayload('b', isDefault: false));
    planService()->create(planServicePayload('c', isDefault: false));

    $service = planService();
    $service->setAsDefault('b');
    $service->setAsDefault('c');
    $service->setAsDefault('a');
    $service->setAsDefault('b');

    expect(Plan::where('is_default', true)->count())->toBe(1);
    expect(Plan::find('b')?->is_default)->toBeTrue();
});

it('concurrent default swap scenario leaves one default after transactional updates', function (): void {
    planService()->create(planServicePayload('north', isDefault: true));
    planService()->create(planServicePayload('south', isDefault: false));

    DB::transaction(function (): void {
        $service = planService();
        $service->setAsDefault('south');
        $service->setAsDefault('north');
    });

    expect(Plan::where('is_default', true)->count())->toBe(1);
    expect(Plan::find('north')?->is_default)->toBeTrue();
    expect(Plan::find('south')?->is_default)->toBeFalse();
});

it('updates plan without disturbing unrelated defaults', function (): void {
    planService()->create(planServicePayload('keep-default', isDefault: true));
    planService()->create(planServicePayload('mutable', isDefault: false));

    planService()->update('mutable', ['name' => 'Renamed', 'default_quota' => '15 GB']);

    expect(Plan::find('mutable')?->name)->toBe('Renamed');
    expect(Plan::find('keep-default')?->is_default)->toBeTrue();
    expect(Plan::where('is_default', true)->count())->toBe(1);
});
