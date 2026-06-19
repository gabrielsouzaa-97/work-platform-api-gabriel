<?php

use App\Http\Controllers\Api\AgentGatewayController;
use App\Http\Controllers\Api\AgentInventoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerLifecycleController;
use App\Http\Controllers\Api\DomainVerifyController;
use App\Http\Controllers\Api\FarmAgentController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\OccController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Middleware\VerifyWebhookHmac;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — mework360-deployer
|--------------------------------------------------------------------------
|
| Webhook receiver: public endpoint, protected by HMAC + IP whitelist.
| All other endpoints: protected by auth + active.operator middleware.
|
*/

Route::post('/jobs/hook', [WebhookController::class, 'receive'])
    ->middleware(VerifyWebhookHmac::class)
    ->name('jobs.hook');

Route::prefix('agent/v1')
    ->middleware(['verify.agent', 'throttle:120,1'])
    ->group(function (): void {
        Route::get('/commands', [AgentGatewayController::class, 'pollCommands'])
            ->name('api.agent.commands.poll');
        Route::post('/events', [AgentGatewayController::class, 'receiveEvents'])
            ->name('api.agent.events.receive');
        Route::post('/inventory', [AgentInventoryController::class, 'store'])
            ->name('api.agent.inventory.store');
    });

Route::middleware(['auth:web,api-key', 'active.operator', 'throttle:120,1', 'api.legacy-deprecation'])->group(function (): void {
    Route::middleware('api.scope:farm-agents:read')->group(function (): void {
        Route::get('/farm-agents', [FarmAgentController::class, 'index'])->name('api.farm-agents.index');
        Route::get('/farm-agents/{id}', [FarmAgentController::class, 'show'])->name('api.farm-agents.show');
    });

    Route::middleware('api.scope:farm-agents:write')->group(function (): void {
        Route::post('/farm-agents', [FarmAgentController::class, 'store'])->name('api.farm-agents.store');
        Route::patch('/farm-agents/{id}', [FarmAgentController::class, 'update'])->name('api.farm-agents.update');
        Route::post('/farm-agents/{id}/ping', [FarmAgentController::class, 'enqueuePing'])
            ->name('api.farm-agents.ping');
    });

    Route::middleware('api.scope:queue:read')->group(function (): void {
        Route::get('/queue', [JobController::class, 'index'])->name('api.queue.index');
        Route::get('/queue/stats', [JobController::class, 'stats'])->name('api.queue.stats');
        Route::get('/queue/{id}', [JobController::class, 'show'])->name('api.queue.show');
    });

    Route::post('/queue/{id}/cancel', [JobController::class, 'cancel'])
        ->middleware('api.scope:queue:write')
        ->name('api.queue.cancel');

    Route::post('/customers', [CustomerController::class, 'store'])
        ->middleware(['api.tenant', 'api.scope:customers:write'])
        ->name('api.customers.store');
    Route::delete('/customers/{slug}', [CustomerController::class, 'destroy'])
        ->middleware(['api.tenant', 'can:provision-customers', 'api.scope:customers:write'])
        ->name('api.customers.destroy');

    Route::post('/customers/{customer}/domain/verify', [DomainVerifyController::class, 'verify'])
        ->middleware(['api.tenant', 'api.scope:customers:read'])
        ->name('api.customers.domain.verify');

    // OCC sync passthrough — admin/interno only (fora do spec externo; consumidores usam /api/v1)
    Route::prefix('customers/{customer}/occ')
        ->middleware(['can:manage-operators', 'api.tenant', 'api.scope:occ:write'])
        ->group(function (): void {
            Route::put('quota/default', [OccController::class, 'setQuotaDefault'])->name('api.occ.quota.default');
            Route::put('quota/all', [OccController::class, 'setQuotaAll'])->name('api.occ.quota.all');
            Route::get('quota/audit', [OccController::class, 'quotaAudit'])->name('api.occ.quota.audit');
            Route::get('quota/options', [OccController::class, 'quotaOptions'])->name('api.occ.quota.options');
            Route::put('quota/{username}', [OccController::class, 'setQuota'])->name('api.occ.quota.set');
            Route::put('branding', [OccController::class, 'setBranding'])->name('api.occ.branding');
            Route::post('maintenance', [OccController::class, 'toggleMaintenance'])->name('api.occ.maintenance');
            Route::post('files-rescan', [OccController::class, 'filesRescan'])->name('api.occ.files-rescan');
            Route::post('apps/{appId}/enable', [OccController::class, 'enableApp'])->name('api.occ.app.enable');
        });

    // Lifecycle async (F6 — Feature O.2) — SSH --async, retorna job_id em <2s
    Route::prefix('customers/{customer}')
        ->middleware(['api.tenant', 'api.scope:lifecycle:write'])
        ->group(function (): void {
            Route::post('users', [CustomerLifecycleController::class, 'createUser'])->name('api.lifecycle.users.create');
            Route::delete('users/{username}', [CustomerLifecycleController::class, 'deleteUser'])->name('api.lifecycle.users.delete');
            Route::post('groups', [CustomerLifecycleController::class, 'createGroup'])->name('api.lifecycle.groups.create');
            Route::delete('groups/{group}', [CustomerLifecycleController::class, 'deleteGroup'])->name('api.lifecycle.groups.delete');
            Route::post('groups/{group}/users', [CustomerLifecycleController::class, 'addUserToGroup'])->name('api.lifecycle.groups.users.add');
            Route::delete('groups/{group}/users/{username}', [CustomerLifecycleController::class, 'removeUserFromGroup'])->name('api.lifecycle.groups.users.remove');
            Route::post('apps/enable', [CustomerLifecycleController::class, 'enableApps'])->name('api.lifecycle.apps.enable');
            Route::post('apps/disable', [CustomerLifecycleController::class, 'disableApps'])->name('api.lifecycle.apps.disable');
        });
});
