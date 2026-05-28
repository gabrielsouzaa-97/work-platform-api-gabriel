<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lifecycle\AddUserToGroupRequest;
use App\Http\Requests\Lifecycle\CreateGroupRequest;
use App\Http\Requests\Lifecycle\CreateUserRequest;
use App\Http\Requests\Lifecycle\DisableAppsRequest;
use App\Http\Requests\Lifecycle\EnableAppsRequest;
use App\Http\Requests\Lifecycle\RemoveUserFromGroupRequest;
use App\Models\Customer;
use App\Models\Operator;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Core\Translators\Exceptions\BlockedOnUpstreamException;
use App\Modules\Customers\Actions\LifecycleAsyncAction;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Customers\Exceptions\IdempotencyConflictException;
use App\Modules\Customers\Exceptions\TenantNotReadyException;
use App\Modules\Customers\Support\UserCreateStdinPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerLifecycleController extends Controller
{
    public function __construct(private readonly LifecycleAsyncAction $action) {}

    /** POST /customers/{customer}/users */
    public function createUser(Customer $customer, CreateUserRequest $request): JsonResponse
    {
        // Upstream `user create` accepts a single positional <username>; all other fields
        // travel via JSON `--payload-stdin`. See ISSUE-006 §DP3 and upstream schema
        // {password, display_name?, email?, quota?, groups?, subadmin_groups?}.
        $stdinPayload = UserCreateStdinPayload::build(
            password: $request->string('password')->toString(),
            displayName: $request->string('display_name', '')->toString() ?: null,
            email: $request->string('email', '')->toString() ?: null,
            quota: $request->string('quota', '')->toString() ?: null,
            groups: $request->array('groups', []),
            subadminGroups: $request->array('subadmin_groups', []),
        );

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
            return response()->json([
                'error' => 'tenant_not_ready',
                'status' => $e->customerStatus,
            ], 503)->header('Retry-After', (string) $e->retryAfterSeconds);
        } catch (BlockedOnUpstreamException $e) {
            // Verb is pending in mework360-deployer-scripts (D3/D4) — surface HTTP 501
            // so clients know this is *intentionally* unavailable, not a transient bug.
            return response()->json([
                'error' => 'not_implemented_yet',
                'reason' => 'upstream group membership pending mework360-deployer-scripts D3/D4',
                'cmd' => $e->cmd,
            ], 501);
        } catch (SshRemoteException $e) {
            if ($e->remoteExitCode === 4) {
                return response()->json(['error' => 'already_exists'], 409);
            }
            if ($e->remoteExitCode === 22) {
                return response()->json(['error' => 'validation_failed', 'message' => 'Password does not meet requirements.'], 422);
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
        } catch (SshRemoteException $e) {
            return response()->json([
                'error' => 'upstream_error',
                'exit_code' => $e->remoteExitCode,
                'apps_csv' => $appsCsv,
            ], 502);
        } catch (\Throwable $e) {
            if ($r = $this->mapLifecycleException($e)) {
                return $r;
            }
            throw $e;
        }

        return response()->json([
            'job_id' => $job->job_id,
            'apps_csv' => $appsCsv,
        ], 202);
    }

    /**
     * Maps common lifecycle exceptions to JSON responses. Returns null for exceptions
     * that require caller-specific handling (e.g. SshRemoteException with exit-code routing).
     */
    private function mapLifecycleException(\Throwable $e): ?JsonResponse
    {
        return match (true) {
            $e instanceof ClusterUnreachableException => response()->json(['error' => 'cluster_unreachable'], 503)
                ->header('Retry-After', '60'),
            $e instanceof SshTimeoutException => response()->json(['error' => 'lifecycle_timeout'], 504),
            $e instanceof IdempotencyConflictException => response()->json([
                'error' => 'idempotency_conflict',
                'existing_job_id' => $e->getExistingJobId(),
            ], 409),
            default => null,
        };
    }
}
