<?php

use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Middleware\VerifyWebhookHmac;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — mework360-deployer
|--------------------------------------------------------------------------
|
| Webhook receiver: public endpoint, protected by HMAC + IP whitelist.
| Queue endpoints: protected by auth:sanctum.
|
*/

Route::post('/jobs/hook', [WebhookController::class, 'receive'])
    ->middleware(VerifyWebhookHmac::class)
    ->name('jobs.hook');

Route::middleware(['auth', 'active.operator'])->group(function (): void {
    Route::get('/queue', [JobController::class, 'index'])->name('api.queue.index');
    Route::get('/queue/stats', [JobController::class, 'stats'])->name('api.queue.stats');
    Route::get('/queue/{id}', [JobController::class, 'show'])->name('api.queue.show');
    Route::post('/queue/{id}/cancel', [JobController::class, 'cancel'])->name('api.queue.cancel');
});
