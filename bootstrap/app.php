<?php

use App\Http\Exceptions\RenderDomainError;
use App\Http\Middleware\EnsureApiKeyScope;
use App\Http\Middleware\EnsureOperatorIsActive;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureTenantBinding;
use App\Http\Middleware\LegacyApiDeprecation;
use App\Http\Middleware\SecureHeaders;
use App\Http\Middleware\VerifyAgentAuth;
use App\Http\Middleware\VerifyWebhookHmac;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')
                ->prefix('api/v1')
                ->group(base_path('routes/api_v1.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [
            SecureHeaders::class,
        ]);
        $middleware->alias([
            'active.operator' => EnsureOperatorIsActive::class,
            'role' => EnsureRole::class,
            'webhook.hmac' => VerifyWebhookHmac::class,
            'verify.agent' => VerifyAgentAuth::class,
            'api.scope' => EnsureApiKeyScope::class,
            'api.tenant' => EnsureTenantBinding::class,
            'api.legacy-deprecation' => LegacyApiDeprecation::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->is('api', 'api/*') || $request->expectsJson();
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            return RenderDomainError::renderNotFound($e, $request);
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            return RenderDomainError::renderMethodNotAllowed($e, $request);
        });
    })->create();
