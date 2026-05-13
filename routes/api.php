<?php

use App\Http\Controllers\Api\WebhookController;
use App\Http\Middleware\VerifyWebhookHmac;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — mework360-deployer
|--------------------------------------------------------------------------
|
| Webhook receiver: public endpoint, protected by HMAC + IP whitelist.
| Queue endpoints (D5.2+): protected by auth:sanctum.
|
*/

Route::post('/jobs/hook', [WebhookController::class, 'receive'])
    ->middleware(VerifyWebhookHmac::class)
    ->name('jobs.hook');
