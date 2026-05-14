<?php

use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerLifecycleController;
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

Route::middleware(['auth', 'active.operator', 'throttle:120,1'])->group(function (): void {
    Route::get('/queue', [JobController::class, 'index'])->name('api.queue.index');
    Route::get('/queue/stats', [JobController::class, 'stats'])->name('api.queue.stats');
    Route::get('/queue/{id}', [JobController::class, 'show'])->name('api.queue.show');
    Route::post('/queue/{id}/cancel', [JobController::class, 'cancel'])->name('api.queue.cancel');

    Route::post('/customers', [CustomerController::class, 'store'])->name('api.customers.store');
    Route::delete('/customers/{slug}', [CustomerController::class, 'destroy'])->name('api.customers.destroy');

    // OCC sync passthrough (F6 — Feature P) — timeout 60s, resposta direta upstream
    Route::prefix('customers/{customer}/occ')->group(function (): void {
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
    Route::prefix('customers/{customer}')->group(function (): void {
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
