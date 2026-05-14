<?php

use App\Http\Middleware\EnsureOperatorIsActive;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SecureHeaders;
use App\Http\Middleware\VerifyWebhookHmac;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SecureHeaders::class,
        ]);
        $middleware->alias([
            'active.operator' => EnsureOperatorIsActive::class,
            'role' => EnsureRole::class,
            'webhook.hmac' => VerifyWebhookHmac::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
