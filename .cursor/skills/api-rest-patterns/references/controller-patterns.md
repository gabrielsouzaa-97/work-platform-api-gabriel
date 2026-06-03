# Controller Patterns

Templates dos 3 tipos de controller usados na API. Copie o template do modo correto e
adapte — nunca invente uma estrutura nova sem motivo.

---

## Padrão 1: Async Lifecycle

Baseado em `CustomerLifecycleController`. Use para operações que disparam um job async
via SSH `--async` e retornam `202 { job_id }` em < 2s.

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lifecycle\CreateFooRequest;
use App\Models\Customer;
use App\Models\Operator;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Core\Translators\Exceptions\BlockedOnUpstreamException;
use App\Modules\Customers\Actions\LifecycleAsyncAction;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Customers\Exceptions\IdempotencyConflictException;
use App\Modules\Customers\Exceptions\TenantNotReadyException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FooLifecycleController extends Controller
{
    public function __construct(private readonly LifecycleAsyncAction $action) {}

    /** POST /customers/{customer}/foos */
    public function createFoo(Customer $customer, CreateFooRequest $request): JsonResponse
    {
        return $this->dispatch(
            $customer,
            'foos:create',
            [$request->string('name')->toString()],
            null,
            $request,
        );
    }

    /** DELETE /customers/{customer}/foos/{fooId} */
    public function deleteFoo(Customer $customer, string $fooId, Request $request): JsonResponse
    {
        // Validação inline de path params sem FormRequest
        if (! preg_match('/^[a-zA-Z0-9._-]+$/', $fooId) || strlen($fooId) > 64) {
            return response()->json(['error' => 'invalid_foo_id'], 422);
        }

        return $this->dispatch($customer, 'foos:delete', [$fooId], null, $request);
    }

    /**
     * Despacha um comando async. Centraliza o mapeamento de exceções comuns.
     *
     * @param array<int, string>      $args
     * @param array<string, mixed>|null $stdinPayload
     */
    private function dispatch(
        Customer $customer,
        string $cmd,
        array $args,
        ?array $stdinPayload,
        Request $request,
    ): JsonResponse {
        /** @var Operator $actor */
        $actor = $request->user();

        try {
            $job = $this->action->execute($customer, $cmd, $args, $stdinPayload, $actor);
        } catch (TenantNotReadyException $e) {
            return response()->json([
                'error'  => 'tenant_not_ready',
                'status' => $e->customerStatus,
            ], 503)->header('Retry-After', (string) $e->retryAfterSeconds);
        } catch (BlockedOnUpstreamException $e) {
            return response()->json([
                'error'  => 'not_implemented_yet',
                'reason' => 'Feature pendente nos deployer-scripts',
                'cmd'    => $e->cmd,
            ], 501);
        } catch (SshRemoteException $e) {
            // Exit codes com semântica específica vêm primeiro
            if ($e->remoteExitCode === 4) {
                return response()->json(['error' => 'already_exists'], 409);
            }

            return response()->json(['error' => 'upstream_error', 'exit_code' => $e->remoteExitCode], 502);
        } catch (\Throwable $e) {
            if ($r = $this->mapLifecycleException($e)) {
                return $r;
            }
            throw $e;
        }

        return response()->json(['job_id' => $job->job_id], 202);
    }

    /**
     * Mapeia exceções compartilhadas entre métodos do controller.
     * Retorna null para exceções que o caller deve tratar individualmente.
     */
    private function mapLifecycleException(\Throwable $e): ?JsonResponse
    {
        return match (true) {
            $e instanceof ClusterUnreachableException => response()->json(
                ['error' => 'cluster_unreachable'], 503
            )->header('Retry-After', '60'),
            $e instanceof SshTimeoutException => response()->json(
                ['error' => 'lifecycle_timeout'], 504
            ),
            $e instanceof IdempotencyConflictException => response()->json([
                'error'           => 'idempotency_conflict',
                'existing_job_id' => $e->getExistingJobId(),
            ], 409),
            default => null,
        };
    }
}
```

---

## Padrão 2: Sync OCC Passthrough

Baseado em `OccController`. Use para operações OCC síncronas que executam SSH e retornam
a resposta do upstream diretamente. Timeout de 60s.

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Occ\SetFooRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Operator;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Customers\Services\OccPassthroughService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class FooOccController extends Controller
{
    public function __construct(private readonly OccPassthroughService $occ) {}

    /** PUT /customers/{customer}/occ/foo/{target} */
    public function setFoo(Customer $customer, string $target, SetFooRequest $request): JsonResponse
    {
        // Validação inline de path params
        if (! preg_match('/^[a-zA-Z0-9._-]+$/', $target)) {
            return response()->json(['error' => 'invalid_target'], 422);
        }

        return $this->runOcc(
            $customer,
            'foo:set',
            [$target, $request->string('value')->toString()],
            'occ_set_foo',
            $request,
            ['target' => $target],
        );
    }

    /**
     * Executa OCC, escreve AuditLog no sucesso, mapeia exceções para HTTP.
     *
     * @param array<int, string>      $args
     * @param array<string, mixed>    $auditExtra
     */
    private function runOcc(
        Customer $customer,
        string $subcmd,
        array $args,
        string $auditAction,
        Request $request,
        array $auditExtra = [],
    ): JsonResponse {
        return $this->runOccExec(
            $customer,
            $subcmd,
            fn () => $this->occ->exec($customer, $subcmd, $args),
            $auditAction,
            $request,
            array_merge(['args' => $args], $auditExtra),
        );
    }

    /**
     * @param callable(): array<string, mixed> $execute
     * @param array<string, mixed>             $auditPayloadExtra
     */
    private function runOccExec(
        Customer $customer,
        string $subcmd,
        callable $execute,
        string $auditAction,
        Request $request,
        array $auditPayloadExtra = [],
    ): JsonResponse {
        try {
            $result = $execute();
        } catch (ClusterUnreachableException) {
            return response()->json(['error' => 'cluster_unreachable'], 503)
                ->header('Retry-After', '60');
        } catch (SshTimeoutException) {
            return response()->json(['error' => 'occ_timeout'], 504);
        } catch (SshRemoteException $e) {
            if ($e->remoteExitCode === 1) {
                return response()->json(['error' => 'not_found'], 404);
            }
            if ($e->remoteExitCode === 16) {
                return response()->json([
                    'error'    => 'occ_subcmd_not_allowed',
                    'detail'   => 'Subcmd "'.$subcmd.'" fora da allowlist do occ-exec (exit 16).',
                    'subcmd'   => $subcmd,
                    'exit_code' => 16,
                ], 403);
            }

            return response()->json([
                'error'     => 'upstream_error',
                'exit_code' => $e->remoteExitCode,
            ], 502);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'invalid_upstream_response', 'message' => $e->getMessage()], 502);
        }

        /** @var Operator $actor */
        $actor = $request->user();

        AuditLog::create([
            'id'                => Str::uuid()->toString(),
            'actor_id'          => $actor->id,
            'action'            => $auditAction,
            'resource_type'     => 'customer',
            'resource_id'       => $customer->slug,
            'payload'           => array_merge(['subcmd' => $subcmd], $auditPayloadExtra),
            'cluster_server_id' => $customer->clusterServer?->id,
        ]);

        return response()->json($result);
    }
}
```

---

## Padrão 3: Provision / Remove (Síncrono com Resource)

Baseado em `CustomerController`. Use para operações que criam ou removem um recurso
persistido, retornam 201 com Resource ou 202 com job_id para operações longas.

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProvisionFooRequest;
use App\Http\Requests\RemoveFooRequest;
use App\Http\Resources\FooResource;
use App\Models\Foo;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Customers\Exceptions\ConfirmationMismatchException;
use App\Modules\Customers\Exceptions\IdempotencyConflictException;
use App\Modules\Customers\Exceptions\StateConflictException;
use App\Modules\Foo\Actions\ProvisionFooAction;
use App\Modules\Foo\Actions\RemoveFooAction;
use App\Modules\Foo\Dto\FooPayload;
use Illuminate\Http\JsonResponse;

final class FooController extends Controller
{
    public function store(ProvisionFooRequest $request, ProvisionFooAction $action): FooResource|JsonResponse
    {
        // Buscar ghost (soft-deleted) para suportar re-provision
        $ghost = Foo::withTrashed()
            ->where('slug', $request->string('slug')->toString())
            ->whereNotNull('deleted_at')
            ->first();

        $payload = FooPayload::fromRequestWithFoo($request, $ghost);

        try {
            $result = $action->execute($payload, $request->user());
        } catch (IdempotencyConflictException $e) {
            return response()->json([
                'error'           => 'idempotency_conflict',
                'existing_job_id' => $e->getExistingJobId(),
            ], 409);
        } catch (StateConflictException $e) {
            return response()->json([
                'error' => 'state_conflict',
                'diff'  => $e->getDiff(),
            ], 409);
        } catch (ClusterUnreachableException) {
            return response()->json(['error' => 'cluster_unreachable'], 503)
                ->header('Retry-After', '60');
        } catch (SshRemoteException $e) {
            return response()->json([
                'error'     => 'upstream_error',
                'exit_code' => $e->remoteExitCode,
                'detail'    => $e->parsedJson,
            ], 502);
        }

        return new FooResource($result['foo']);  // 200; use ->response()->setStatusCode(201) se necessário
    }

    public function destroy(string $slug, RemoveFooRequest $request, RemoveFooAction $action): JsonResponse
    {
        try {
            $job = $action->execute(
                $slug,
                $request->string('confirm_slug')->toString(),
                $request->user(),
            );
        } catch (ConfirmationMismatchException) {
            return response()->json(['error' => 'confirm_slug_mismatch'], 422);
        } catch (StateConflictException) {
            return response()->json(['error' => 'state_conflict'], 409);
        } catch (SshRemoteException $e) {
            return response()->json(['error' => 'upstream_error', 'message' => $e->getMessage()], 502);
        }

        return response()->json(['job_id' => $job->job_id], 202);
    }
}
```

---

## Ordem de captura de exceções

Sempre capturar do mais específico para o mais genérico:

```
1. Exceções de negócio específicas do endpoint (IdempotencyConflict, StateConflict...)
2. SshRemoteException com exit code específico (exit 4, exit 16, exit 22...)
3. SshRemoteException genérico (exit code não mapeado → 502)
4. Exceções de infraestrutura (Timeout → 504, Unreachable → 503)
5. BlockedOnUpstreamException → 501
6. \RuntimeException (OCC only → invalid_upstream_response 502)
7. \Throwable → mapear via helper ou re-throw
```

**NUNCA** capturar `\Exception` ou `\Throwable` sem re-throw ou log — erros inesperados
devem chegar ao handler do Laravel para logging adequado.
