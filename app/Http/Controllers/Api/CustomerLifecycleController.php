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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerLifecycleController extends Controller
{
    public function __construct(private readonly LifecycleAsyncAction $action) {}

    /** POST /customers/{customer}/users */
    public function createUser(Customer $customer, CreateUserRequest $request): JsonResponse
    {
        // Upstream `user create` accepts a single positional <username>; email and groups
        // must travel via the JSON `--payload-stdin` (alongside the password). Passing them
        // as positional args / --group flags silently no-ops upstream (per SSH probing).
        // See ISSUE-006 §"Design points" DP3.
        $stdinPayload = ['password' => $request->string('password')->toString()];

        $email = $request->string('email', '')->toString();
        if ($email !== '') {
            $stdinPayload['email'] = $email;
        }

        $groups = array_values(array_filter(
            $request->array('groups', []),
            static fn (mixed $g): bool => is_string($g) && $g !== '',
        ));
        if ($groups !== []) {
            $stdinPayload['groups'] = $groups;
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
        } catch (BlockedOnUpstreamException $e) {
            // Verb is pending in mework360-deployer-scripts (D3/D4) — surface HTTP 501
            // so clients know this is *intentionally* unavailable, not a transient bug.
            return response()->json([
                'error' => 'not_implemented_yet',
                'reason' => 'upstream group membership pending mework360-deployer-scripts D3/D4',
                'cmd' => $e->cmd,
            ], 501);
        } catch (ClusterUnreachableException) {
            return response()->json(['error' => 'cluster_unreachable'], 503)
                ->header('Retry-After', '60');
        } catch (SshTimeoutException) {
            return response()->json(['error' => 'lifecycle_timeout'], 504);
        } catch (IdempotencyConflictException $e) {
            return response()->json([
                'error' => 'idempotency_conflict',
                'existing_job_id' => $e->getExistingJobId(),
            ], 409);
        } catch (SshRemoteException $e) {
            if ($e->remoteExitCode === 4) {
                return response()->json(['error' => 'already_exists'], 409);
            }
            if ($e->remoteExitCode === 22) {
                return response()->json(['error' => 'validation_failed', 'message' => 'Password does not meet requirements.'], 422);
            }

            return response()->json(['error' => 'upstream_error', 'exit_code' => $e->remoteExitCode], 502);
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
     * @param  array<int, string>  $apps
     */
    private function dispatchAppsCsv(Customer $customer, string $cmd, array $apps, Request $request): JsonResponse
    {
        /** @var Operator $actor */
        $actor = $request->user();

        $appsCsv = implode(',', $apps);

        try {
            $job = $this->action->execute($customer, $cmd, [$appsCsv], null, $actor);
        } catch (ClusterUnreachableException) {
            return response()->json(['error' => 'cluster_unreachable'], 503)
                ->header('Retry-After', '60');
        } catch (SshTimeoutException) {
            return response()->json(['error' => 'lifecycle_timeout'], 504);
        } catch (IdempotencyConflictException $e) {
            return response()->json([
                'error' => 'idempotency_conflict',
                'existing_job_id' => $e->getExistingJobId(),
            ], 409);
        } catch (SshRemoteException $e) {
            return response()->json([
                'error' => 'upstream_error',
                'exit_code' => $e->remoteExitCode,
                'apps_csv' => $appsCsv,
            ], 502);
        }

        return response()->json([
            'job_id' => $job->job_id,
            'apps_csv' => $appsCsv,
        ], 202);
    }
}
