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
        $args = array_filter([$request->string('username')->toString(), $request->string('email', '')->toString()]);
        foreach ($request->array('groups', []) as $group) {
            $args[] = "--group={$group}";
        }

        return $this->dispatch(
            $customer,
            'users:create',
            array_values($args),
            ['password' => $request->string('password')->toString()],
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
        return $this->dispatchMulti($customer, 'apps:enable', $request->array('apps'), $request);
    }

    /** POST /customers/{customer}/apps/disable */
    public function disableApps(Customer $customer, DisableAppsRequest $request): JsonResponse
    {
        return $this->dispatchMulti($customer, 'apps:disable', $request->array('apps'), $request);
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
     * Dispatch one lifecycle command per app ID (bulk enable/disable).
     *
     * @param  array<int, string>  $apps
     */
    private function dispatchMulti(Customer $customer, string $cmd, array $apps, Request $request): JsonResponse
    {
        /** @var Operator $actor */
        $actor = $request->user();
        $jobIds = [];

        foreach ($apps as $appId) {
            try {
                $job = $this->action->execute($customer, $cmd, [$appId], null, $actor);
                $jobIds[] = $job->job_id;
            } catch (ClusterUnreachableException) {
                return response()->json(['error' => 'cluster_unreachable'], 503)
                    ->header('Retry-After', '60');
            } catch (IdempotencyConflictException $e) {
                $jobIds[] = $e->getExistingJobId();
            } catch (SshRemoteException $e) {
                return response()->json(['error' => 'upstream_error', 'exit_code' => $e->remoteExitCode, 'app' => $appId], 502);
            }
        }

        return response()->json(['job_ids' => $jobIds], 202);
    }
}
