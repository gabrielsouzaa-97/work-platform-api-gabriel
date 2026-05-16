<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Occ\FilesRescanRequest;
use App\Http\Requests\Occ\SetBrandingRequest;
use App\Http\Requests\Occ\SetQuotaRequest;
use App\Http\Requests\Occ\ToggleMaintenanceRequest;
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

final class OccController extends Controller
{
    public function __construct(private readonly OccPassthroughService $occ) {}

    /** PUT /customers/{customer}/occ/quota/{username} */
    public function setQuota(Customer $customer, string $username, SetQuotaRequest $request): JsonResponse
    {
        return $this->runOcc(
            $customer,
            'user:setting',
            [$username, 'files', 'quota', $request->string('quota')->toString()],
            'occ_set_quota',
            $request,
        );
    }

    /** PUT /customers/{customer}/occ/quota/default */
    public function setQuotaDefault(Customer $customer, SetQuotaRequest $request): JsonResponse
    {
        // config:app:set requires --value flag which upstream dispatch strips (POSITIONAL filter bug).
        // Workaround: pass value as 3rd positional; OCC 25+ accepts both forms.
        return $this->runOcc(
            $customer,
            'config:app:set',
            ['files', 'default_quota', $request->string('quota')->toString()],
            'occ_set_quota_default',
            $request,
        );
    }

    /** PUT /customers/{customer}/occ/quota/all */
    public function setQuotaAll(Customer $customer, SetQuotaRequest $request): JsonResponse
    {
        // user:setting --all requires --all flag which upstream dispatch strips.
        // Pending upstream fix in nextcloud-manage dispatch.sh: pass OCC flags through POSITIONAL.
        return response()->json([
            'error' => 'upstream_dispatch_limitation',
            'detail' => 'user:setting --all requires --all flag; upstream dispatch strips OCC --flags. Fix dispatch_namespace_cmd in dispatch.sh to pass non-global --flags to occ-exec.',
        ], 501);
    }

    /** GET /customers/{customer}/occ/quota/audit */
    public function quotaAudit(Customer $customer, Request $request): JsonResponse
    {
        // files:scan --all --show-quota cannot be used: upstream dispatch strips OCC --flags.
        // Fallback: user:list returns all users (bridge auto-appends --output=json).
        return $this->runOcc($customer, 'user:list', [], 'occ_quota_audit', $request);
    }

    /** GET /customers/{customer}/occ/quota/options */
    public function quotaOptions(): JsonResponse
    {
        return response()->json(['options' => OccPassthroughService::quotaOptions()]);
    }

    /** PUT /customers/{customer}/occ/branding */
    public function setBranding(Customer $customer, SetBrandingRequest $request): JsonResponse
    {
        $args = [];
        foreach (['name', 'color', 'url', 'slogan', 'imprintUrl', 'privacyUrl'] as $field) {
            if ($request->filled($field)) {
                $args[] = $field;
                $args[] = $request->string($field)->toString();
            }
        }

        return $this->runOcc($customer, 'theming:config', $args, 'occ_set_branding', $request);
    }

    /** POST /customers/{customer}/occ/maintenance */
    public function toggleMaintenance(Customer $customer, ToggleMaintenanceRequest $request): JsonResponse
    {
        // maintenance:mode --on/--off flags are stripped by upstream dispatch.
        // Workaround: pass "on"/"off" as positional — OCC accepts this form via Symfony Console bool option.
        $mode = $request->boolean('on') ? 'on' : 'off';

        return $this->runOcc($customer, 'maintenance:mode', [$mode], 'occ_maintenance_toggle', $request);
    }

    /** POST /customers/{customer}/occ/files-rescan */
    public function filesRescan(Customer $customer, FilesRescanRequest $request): JsonResponse
    {
        // files:scan --all strips --all via upstream dispatch; require explicit username.
        if (! $request->filled('username')) {
            return response()->json([
                'error' => 'upstream_dispatch_limitation',
                'detail' => 'files:scan --all requires --all flag; upstream dispatch strips OCC --flags. Provide ?username=<user> to scan a specific user, or fix dispatch.sh.',
            ], 501);
        }

        return $this->runOcc(
            $customer,
            'files:scan',
            [$request->string('username')->toString()],
            'occ_files_rescan',
            $request,
        );
    }

    /** POST /customers/{customer}/occ/apps/{appId}/enable */
    public function enableApp(Customer $customer, string $appId, Request $request): JsonResponse
    {
        if (! preg_match('/^[a-z0-9_]+$/', $appId)) {
            return response()->json(['error' => 'invalid_app_id'], 422);
        }

        return $this->runOcc($customer, 'app:enable', [$appId], 'occ_app_enable', $request);
    }

    /**
     * Central execution — calls OCC passthrough, writes audit log, maps exceptions to HTTP status.
     *
     * @param  array<int, string>  $args
     */
    private function runOcc(Customer $customer, string $subcmd, array $args, string $auditAction, Request $request): JsonResponse
    {
        try {
            $result = $this->occ->exec($customer, $subcmd, $args);
        } catch (ClusterUnreachableException) {
            return response()->json(['error' => 'cluster_unreachable'], 503)
                ->header('Retry-After', '60');
        } catch (SshTimeoutException) {
            return response()->json(['error' => 'occ_timeout'], 504);
        } catch (SshRemoteException $e) {
            if ($e->remoteExitCode === 1) {
                return response()->json(['error' => 'not_found'], 404);
            }

            return response()->json([
                'error' => 'upstream_error',
                'exit_code' => $e->remoteExitCode,
            ], 502);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'invalid_upstream_response', 'message' => $e->getMessage()], 502);
        }

        /** @var Operator $actor */
        $actor = $request->user();

        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'actor_id' => $actor->id,
            'action' => $auditAction,
            'resource_type' => 'customer',
            'resource_id' => $customer->slug,
            'payload' => ['subcmd' => $subcmd, 'args' => $args],
            'cluster_server_id' => $customer->clusterServer?->id,
        ]);

        return response()->json($result);
    }
}
