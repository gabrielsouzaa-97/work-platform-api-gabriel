<?php

use App\Http\Middleware\EnsureOperatorIsActive;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SecureHeaders;
use App\Http\Middleware\VerifyWebhookHmac;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => 'route_not_found',
                    'path' => $request->getPathInfo(),
                    'method' => $request->method(),
                ], 404);
            }
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => 'method_not_allowed',
                    'path' => $request->getPathInfo(),
                    'method' => $request->method(),
                ], 405);
            }
        });
    })->create();
