<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\BrandingV1Controller;
use App\Http\Controllers\Api\V1\JobV1Controller;
use App\Http\Controllers\Api\V1\OnboardingV1Controller;
use App\Http\Controllers\Api\V1\TenantAppsController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\TenantUserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:web,api-key', 'active.operator', 'throttle:120,1'])->group(function (): void {
    Route::post('/tenants', [TenantController::class, 'store'])
        ->middleware(['api.tenant', 'api.scope:tenants:write'])
        ->name('api.v1.tenants.store');

    Route::get('/tenants/{slug}', [TenantController::class, 'show'])
        ->middleware(['api.tenant', 'api.scope:tenants:read'])
        ->name('api.v1.tenants.show');

    Route::delete('/tenants/{slug}', [TenantController::class, 'destroy'])
        ->middleware(['api.tenant', 'api.scope:tenants:write'])
        ->name('api.v1.tenants.destroy');

    Route::post('/tenants/{customer}/apps', [TenantAppsController::class, 'enableApps'])
        ->middleware(['api.tenant', 'api.scope:apps:write'])
        ->name('api.v1.tenants.apps.enable');

    Route::post('/tenants/{customer}/users', [TenantUserController::class, 'createUser'])
        ->middleware(['api.tenant', 'api.scope:users:write'])
        ->name('api.v1.tenants.users.create');

    Route::delete('/tenants/{customer}/users/{username}', [TenantUserController::class, 'deleteUser'])
        ->middleware(['api.tenant', 'api.scope:users:write'])
        ->name('api.v1.tenants.users.delete');

    Route::put('/tenants/{slug}/users/{username}/quota', [TenantUserController::class, 'setQuota'])
        ->middleware(['api.tenant', 'api.scope:users:write'])
        ->name('api.v1.tenants.users.quota');

    Route::put('/tenants/{slug}/branding', [BrandingV1Controller::class, 'update'])
        ->middleware(['api.tenant', 'api.scope:branding:write'])
        ->name('api.v1.tenants.branding.update');

    Route::post('/onboarding', [OnboardingV1Controller::class, 'store'])
        ->middleware('api.scope:onboarding:run')
        ->name('api.v1.onboarding.store');

    Route::get('/onboarding/{id}', [OnboardingV1Controller::class, 'show'])
        ->middleware('api.scope:onboarding:run')
        ->name('api.v1.onboarding.show');

    Route::get('/jobs/{id}', [JobV1Controller::class, 'show'])
        ->middleware('api.scope:jobs:read')
        ->name('api.v1.jobs.show');
});
