<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AppCatalogV1Controller;
use App\Http\Controllers\Api\V1\BrandingV1Controller;
use App\Http\Controllers\Api\V1\JobV1Controller;
use App\Http\Controllers\Api\V1\MaintenanceV1Controller;
use App\Http\Controllers\Api\V1\OnboardingV1Controller;
use App\Http\Controllers\Api\V1\PlanV1Controller;
use App\Http\Controllers\Api\V1\TenantAppsController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\TenantUserController;
use App\Http\Controllers\Api\V1\UserTemplateV1Controller;
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

    Route::get('/tenants/{slug}/users', [TenantUserController::class, 'index'])
        ->middleware(['api.tenant', 'api.scope:users:read'])
        ->name('api.v1.tenants.users.index');

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

    Route::post('/tenants/{slug}/maintenance', [MaintenanceV1Controller::class, 'toggle'])
        ->middleware(['api.tenant', 'api.scope:maintenance:write'])
        ->name('api.v1.tenants.maintenance.toggle');

    Route::post('/onboarding', [OnboardingV1Controller::class, 'store'])
        ->middleware('api.scope:onboarding:run')
        ->name('api.v1.onboarding.store');

    Route::get('/onboarding/{id}', [OnboardingV1Controller::class, 'show'])
        ->middleware('api.scope:onboarding:run')
        ->name('api.v1.onboarding.show');

    Route::get('/jobs/{id}', [JobV1Controller::class, 'show'])
        ->middleware('api.scope:jobs:read')
        ->name('api.v1.jobs.show');

    Route::get('/plans', [PlanV1Controller::class, 'index'])
        ->middleware('api.scope:product:read')
        ->name('api.v1.plans.index');

    Route::get('/plans/{slug}', [PlanV1Controller::class, 'show'])
        ->middleware('api.scope:product:read')
        ->name('api.v1.plans.show');

    Route::post('/plans', [PlanV1Controller::class, 'store'])
        ->middleware('api.scope:product:write')
        ->name('api.v1.plans.store');

    Route::patch('/plans/{slug}', [PlanV1Controller::class, 'update'])
        ->middleware('api.scope:product:write')
        ->name('api.v1.plans.update');

    Route::get('/app-catalog', [AppCatalogV1Controller::class, 'index'])
        ->middleware('api.scope:product:read')
        ->name('api.v1.app-catalog.index');

    Route::get('/app-catalog/{app_id}', [AppCatalogV1Controller::class, 'show'])
        ->middleware('api.scope:product:read')
        ->name('api.v1.app-catalog.show');

    Route::post('/app-catalog', [AppCatalogV1Controller::class, 'store'])
        ->middleware('api.scope:product:write')
        ->name('api.v1.app-catalog.store');

    Route::patch('/app-catalog/{app_id}', [AppCatalogV1Controller::class, 'update'])
        ->middleware('api.scope:product:write')
        ->name('api.v1.app-catalog.update');

    Route::get('/user-templates', [UserTemplateV1Controller::class, 'index'])
        ->middleware('api.scope:product:read')
        ->name('api.v1.user-templates.index');

    Route::get('/user-templates/{slug}', [UserTemplateV1Controller::class, 'show'])
        ->middleware('api.scope:product:read')
        ->name('api.v1.user-templates.show');

    Route::post('/user-templates', [UserTemplateV1Controller::class, 'store'])
        ->middleware('api.scope:product:write')
        ->name('api.v1.user-templates.store');

    Route::patch('/user-templates/{slug}', [UserTemplateV1Controller::class, 'update'])
        ->middleware('api.scope:product:write')
        ->name('api.v1.user-templates.update');
});
