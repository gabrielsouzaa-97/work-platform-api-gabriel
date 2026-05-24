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
use App\Modules\Customers\Support\OccQuotaValue;
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
            [$username, 'files', 'quota', OccQuotaValue::forSshArgv($request->string('quota')->toString())],
            'occ_set_quota',
            $request,
        );
    }

    /** PUT /customers/{customer}/occ/quota/default */
    public function setQuotaDefault(Customer $customer, SetQuotaRequest $request): JsonResponse
    {
        // F?-OCC-4 (ISSUE-016): `config:app:set` está na allowlist upstream v12.3.0+.
        // OCC exige `--value` para o valor (positional extra causa exit 16 = occ_command_failed,
        // não allowlist — allowlist rejection usa wrapper exit 100). Ver occ_bridge.sh.
        return $this->runOcc(
            $customer,
            'config:app:set',
            ['files', 'default_quota', '--value', OccQuotaValue::forSshArgv($request->string('quota')->toString())],
            'occ_set_quota_default',
            $request,
        );
    }

    /** PUT /customers/{customer}/occ/quota/all */
    public function setQuotaAll(Customer $customer, SetQuotaRequest $request): JsonResponse
    {
        // ISSUE-011: `user:setting` está fora da allowlist do `occ-exec` upstream (exit 16),
        // independentemente de flags. Não há workaround positional: o subcmd nunca chega ao OCC.
        // Retornamos 501 explícito (em vez de tentar SSH e receber 403) porque a operação
        // bulk `--all` exige flag não-global que o argv positional sequer expressa — é gap
        // de capability, não erro de allowlist por chamada. Reabrir quando upstream
        // expandir allowlist OU expor caminho equivalente em verbos de domínio.
        return response()->json([
            'error' => 'occ_subcmd_not_supported',
            'detail' => 'user:setting --all não é suportado pelo upstream nextcloud-saas-manager. Subcmd está fora da allowlist de occ-exec; aplique a quota usuário-a-usuário via PUT /customers/{slug}/occ/quota/{username}. Ver docs/ISSUES.md ISSUE-011.',
        ], 501);
    }

    /** GET /customers/{customer}/occ/quota/audit */
    public function quotaAudit(Customer $customer, Request $request): JsonResponse
    {
        // ISSUE-011: o ideal seria `files:scan --all --show-quota`, porém esse modo não é
        // exposto pelo wrapper `occ-exec` (não está na allowlist). Fallback: `user:list`
        // está na allowlist e devolve a quota por usuário (bridge anexa --output=json).
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
        // P-10: `theming:config` accepts one `<key> <value>` pair per OCC invocation.
        // execThemingConfig loops non-empty fields — one SSH call per key.
        $fields = [];
        foreach (['name', 'color', 'url', 'slogan', 'imprintUrl', 'privacyUrl'] as $field) {
            if ($request->filled($field)) {
                $fields[$field] = $request->string($field)->toString();
            }
        }

        return $this->runOccExec(
            $customer,
            'theming:config',
            fn () => $this->occ->execThemingConfig($customer, $fields),
            'occ_set_branding',
            $request,
            ['fields' => array_keys($fields)],
        );
    }

    /** POST /customers/{customer}/occ/maintenance */
    public function toggleMaintenance(Customer $customer, ToggleMaintenanceRequest $request): JsonResponse
    {
        // ISSUE-011: `maintenance:mode` está fora da allowlist do `occ-exec` upstream (exit 16).
        // Argv canônico OCC é `--on`/`--off` (alinhado com OccPanel e REQUIREMENTS §6.6).
        // Testes P-15 mostram que positional `on` também falha com exit 16 — refuta hipótese
        // antiga de "flag stripping" (a falha é allowlist, não forma do argv).
        // runOcc mapeia exit 16 → HTTP 403 occ_subcmd_not_allowed.
        $mode = $request->boolean('on') ? '--on' : '--off';

        return $this->runOcc($customer, 'maintenance:mode', [$mode], 'occ_maintenance_toggle', $request);
    }

    /** POST /customers/{customer}/occ/files-rescan */
    public function filesRescan(Customer $customer, FilesRescanRequest $request): JsonResponse
    {
        // ISSUE-011: `files:scan` está na allowlist do `occ-exec`, mas a forma `--all` (sem
        // username) depende de flag não-global que o wrapper não expõe. Exigimos `?username=`
        // para usar o argv positional `files:scan <user>`, que funciona em produção.
        if (! $request->filled('username')) {
            return response()->json([
                'error' => 'occ_bulk_not_supported',
                'detail' => 'files:scan --all não é suportado pelo upstream. Forneça ?username=<user> para escanear um usuário específico. Ver docs/ISSUES.md ISSUE-011.',
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
        return $this->runOccExec(
            $customer,
            $subcmd,
            fn () => $this->occ->exec($customer, $subcmd, $args),
            $auditAction,
            $request,
            ['args' => $args],
        );
    }

    /**
     * @param  callable(): array<string, mixed>  $execute
     * @param  array<string, mixed>  $auditPayloadExtra
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

            // ISSUE-011: exit 16 do `nextcloud-manage <slug> occ-exec` indica que o subcmd
            // OCC requisitado está fora da allowlist do wrapper upstream (refutado o
            // diagnóstico antigo de "flag stripping" — argv positional puro também falha).
            // Devolvemos 403 com erro explícito para não confundir com falha transitória
            // de upstream (502). Subcmds atualmente bloqueados: user:setting, config:app:set,
            // theming:config, maintenance:mode. Ver docs/ISSUES.md ISSUE-011 e P-15.
            if ($e->remoteExitCode === 16) {
                return response()->json([
                    'error' => 'occ_subcmd_not_allowed',
                    'detail' => 'Subcmd "'.$subcmd.'" não está na allowlist do nextcloud-saas-manager occ-exec (exit 16). Ver docs/ISSUES.md ISSUE-011.',
                    'subcmd' => $subcmd,
                    'exit_code' => 16,
                ], 403);
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
            'payload' => array_merge(['subcmd' => $subcmd], $auditPayloadExtra),
            'cluster_server_id' => $customer->clusterServer?->id,
        ]);

        return response()->json($result);
    }
}
