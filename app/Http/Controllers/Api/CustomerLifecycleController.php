<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Exceptions\RenderDomainError;
use App\Http\Requests\Lifecycle\AddUserToGroupRequest;
use App\Http\Requests\Lifecycle\CreateGroupRequest;
use App\Http\Requests\Lifecycle\CreateUserRequest;
use App\Http\Requests\Lifecycle\DisableAppsRequest;
use App\Http\Requests\Lifecycle\EnableAppsRequest;
use App\Http\Requests\Lifecycle\RemoveUserFromGroupRequest;
use App\Models\Customer;
use App\Models\Operator;
use App\Modules\Core\Domain\DomainError;
use App\Modules\Core\Translators\Exceptions\BlockedOnUpstreamException;
use App\Modules\Customers\Actions\LifecycleAsyncAction;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Customers\Exceptions\IdempotencyConflictException;
use App\Modules\Customers\Exceptions\TenantNotReadyException;
use App\Modules\Customers\Support\UserCreateStdinPayload;
use App\Modules\Product\Exceptions\PlanLimitExceededException;
use App\Modules\Product\Services\PlanAppResolver;
use App\Modules\Product\Services\PolicyResolver;
use App\Modules\Product\Services\UserCreateTemplateResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerLifecycleController extends Controller
{
    public function __construct(
        private readonly LifecycleAsyncAction $action,
        private readonly UserCreateTemplateResolver $userCreateTemplateResolver,
        private readonly PolicyResolver $policyResolver,
        private readonly PlanAppResolver $planAppResolver,
    ) {}

    /** POST /customers/{customer}/users */
    public function createUser(Customer $customer, CreateUserRequest $request): JsonResponse
    {
        /** @var Operator $actor */
        $actor = $request->user();

        try {
            $this->policyResolver->assertCanCreateUser($customer, $actor);
        } catch (PlanLimitExceededException) {
            return RenderDomainError::response(DomainError::PlanLimitExceeded);
        }

        $explicitQuota = $request->filled('quota')
            ? $request->string('quota')->toString()
            : null;
        $inheritedQuota = $explicitQuota === null
            ? $this->resolveInheritedQuota($customer)
            : null;

        $templateSlug = $request->filled('user_template_slug')
            ? $request->string('user_template_slug')->toString()
            : null;
        $explicitGroups = $request->has('groups') ? $request->array('groups') : null;
        $resolved = $this->userCreateTemplateResolver->resolve(
            $templateSlug,
            $explicitGroups,
            $explicitQuota,
        );

        $stdinPayload = UserCreateStdinPayload::build(
            password: $request->string('password')->toString(),
            displayName: $request->string('display_name', '')->toString() ?: null,
            email: $request->string('email', '')->toString() ?: null,
            groups: $resolved->groups,
            subadminGroups: $request->array('subadmin_groups', []),
        );

        if ($explicitGroups !== null) {
            $stdinPayload['groups'] = $explicitGroups;
        }

        $this->applyResolvedQuotaToStdin(
            $stdinPayload,
            $request->filled('quota') ? $request->string('quota')->toString() : null,
            $resolved->quota,
            $inheritedQuota,
            $templateSlug !== null,
        );

        if ($resolved->userTemplateSlug !== null) {
            $stdinPayload['user_template_slug'] = $resolved->userTemplateSlug;
        }

        return $this->dispatch(
            $customer,
            'users:create',
            [$request->string('username')->toString()],
            $stdinPayload,
            $request,
        );
    }

    /** DELETE /customers/{customer}/users/{username} */
    public function deleteUser(Customer $customer, string $username, Request $request): JsonResponse
    {
        if (! preg_match('/^[a-zA-Z0-9._-]+$/', $username) || strlen($username) > 64) {
            return response()->json(['error' => 'invalid_username'], 422);
        }

        return $this->dispatch($customer, 'users:delete', [$username], null, $request);
    }

    /** POST /customers/{customer}/groups */
    public function createGroup(Customer $customer, CreateGroupRequest $request): JsonResponse
    {
        return $this->dispatch(
            $customer,
            'groups:create',
            [$request->string('name')->toString()],
            null,
            $request,
        );
    }

    /** DELETE /customers/{customer}/groups/{group} */
    public function deleteGroup(Customer $customer, string $group, Request $request): JsonResponse
    {
        if (! preg_match('/^[a-zA-Z0-9._\- ]+$/', $group) || strlen($group) > 256) {
            return response()->json(['error' => 'invalid_group_name'], 422);
        }

        return $this->dispatch($customer, 'groups:delete', [$group], null, $request);
    }

    /** POST /customers/{customer}/groups/{group}/users */
    public function addUserToGroup(Customer $customer, string $group, AddUserToGroupRequest $request): JsonResponse
    {
        if (! preg_match('/^[a-zA-Z0-9._\- ]+$/', $group) || strlen($group) > 256) {
            return response()->json(['error' => 'invalid_group_name'], 422);
        }

        return $this->dispatch(
            $customer,
            'groups:add',
            [$request->string('username')->toString(), $group],
            null,
            $request,
        );
    }

    /** DELETE /customers/{customer}/groups/{group}/users/{username} */
    public function removeUserFromGroup(Customer $customer, string $group, string $username, RemoveUserFromGroupRequest $request): JsonResponse
    {
        if (! preg_match('/^[a-zA-Z0-9._\- ]+$/', $group) || strlen($group) > 256) {
            return response()->json(['error' => 'invalid_group_name'], 422);
        }

        if (! preg_match('/^[a-zA-Z0-9._-]+$/', $username) || strlen($username) > 64) {
            return response()->json(['error' => 'invalid_username'], 422);
        }

        return $this->dispatch($customer, 'groups:remove', [$username, $group], null, $request);
    }

    /** POST /customers/{customer}/apps/enable */
    public function enableApps(Customer $customer, EnableAppsRequest $request): JsonResponse
    {
        $this->planAppResolver->resolve($customer->plan_slug, $request->array('apps'));

        return $this->dispatchAppsCsv($customer, 'apps:enable', $request->array('apps'), $request);
    }

    /** POST /customers/{customer}/apps/disable */
    public function disableApps(Customer $customer, DisableAppsRequest $request): JsonResponse
    {
        return $this->dispatchAppsCsv($customer, 'apps:disable', $request->array('apps'), $request);
    }

    /**
     * Dispatch a single async lifecycle command.
     *
     * @param  array<int, string>  $args
     * @param  array<string, mixed>|null  $stdinPayload
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
            return RenderDomainError::tenantNotReadyResponse($request, $e);
        } catch (BlockedOnUpstreamException $e) {
            // Verb is pending in mework360-deployer-scripts (D3/D4) — surface HTTP 501
            // so clients know this is *intentionally* unavailable, not a transient bug.
            return response()->json([
                'error' => 'not_implemented_yet',
                'reason' => 'upstream group membership pending mework360-deployer-scripts D3/D4',
                'cmd' => $e->cmd,
            ], 501);
        } catch (\Throwable $e) {
            if ($r = RenderDomainError::mapPortTransportException($e, $request, timeoutError: 'lifecycle_timeout')) {
                return $r;
            }
            if ($r = $this->mapLifecycleException($e, $request)) {
                return $r;
            }
            throw $e;
        }

        return response()->json(['job_id' => $job->job_id], 202);
    }

    /**
     * Dispatch a single async apps:enable / apps:disable job carrying ALL app IDs
     * consolidated as a CSV positional arg.
     *
     * Upstream `apps enable|disable <apps_csv>` is natively CSV (confirmed via SSH
     * probing — ISSUE-006 §DP2). The previous N-jobs-per-app loop was a workaround
     * that wasted N SSH round-trips and broke atomicity.
     *
     * Apps order is intentionally preserved (input order = upstream order).
     * Two requests with the same apps in different order produce different idempotency
     * hashes — this is by design (policy A, QA-F5-008).
     *
     * @param  array<int, string>  $apps
     */
    private function dispatchAppsCsv(Customer $customer, string $cmd, array $apps, Request $request): JsonResponse
    {
        /** @var Operator $actor */
        $actor = $request->user();

        $appsCsv = implode(',', $apps);

        try {
            $job = $this->action->execute($customer, $cmd, [$appsCsv], null, $actor);
        } catch (\Throwable $e) {
            if ($r = RenderDomainError::mapPortTransportException(
                $e,
                $request,
                ['apps_csv' => $appsCsv],
                'lifecycle_timeout',
            )) {
                return $r;
            }
            if ($r = $this->mapLifecycleException($e, $request)) {
                return $r;
            }
            throw $e;
        }

        return response()->json([
            'job_id' => $job->job_id,
            'apps_csv' => $appsCsv,
        ], 202);
    }

    private function resolveInheritedQuota(Customer $customer): ?string
    {
        $customer->loadMissing('plan');

        return $customer->plan?->default_quota;
    }

    /**
     * @param  array<string, mixed>  $stdinPayload
     */
    private function applyResolvedQuotaToStdin(
        array &$stdinPayload,
        ?string $explicitQuota,
        ?string $templateQuota,
        ?string $inheritedQuota,
        bool $hasTemplate,
    ): void {
        if ($explicitQuota !== null && $explicitQuota !== '') {
            $stdinPayload['quota'] = $hasTemplate
                ? $explicitQuota
                : UserCreateStdinPayload::normalizeQuota($explicitQuota);

            return;
        }

        if ($templateQuota !== null && $templateQuota !== '') {
            $stdinPayload['quota'] = $templateQuota;

            return;
        }

        if ($inheritedQuota !== null && $inheritedQuota !== '') {
            $stdinPayload['quota'] = $inheritedQuota;
        }
    }

    /**
     * Maps common lifecycle exceptions to JSON responses. Returns null for exceptions
     * that require caller-specific handling (e.g. SshRemoteException with exit-code routing).
     */
    private function mapLifecycleException(\Throwable $e, Request $request): ?JsonResponse
    {
        return match (true) {
            $e instanceof ClusterUnreachableException => RenderDomainError::clusterUnreachableResponse($request),
            $e instanceof IdempotencyConflictException => RenderDomainError::idempotencyConflictResponse($request, $e),
            default => null,
        };
    }
}
