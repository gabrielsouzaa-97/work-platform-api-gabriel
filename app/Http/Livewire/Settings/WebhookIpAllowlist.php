<?php

declare(strict_types=1);

namespace App\Http\Livewire\Settings;

use App\Models\AuditLog;
use App\Models\ClusterServer;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
final class WebhookIpAllowlist extends Component
{
    /** @var array<string, string|null> */
    public array $allowedIps = [];

    public function mount(): void
    {
        Gate::authorize('manage-cluster-servers');

        foreach (ClusterServer::query()->where('status', 'active')->orderBy('name')->get() as $cluster) {
            $this->allowedIps[$cluster->id] = $cluster->webhook_allowed_ip ?? '';
        }
    }

    public function save(): void
    {
        Gate::authorize('manage-cluster-servers');
        $clusters = ClusterServer::query()->where('status', 'active')->orderBy('name')->get();
        $this->normalizeSubmittedIps($clusters);
        $this->validate($this->buildRules($clusters));
        $this->persistUpdates($clusters);
        $this->dispatch('toast', type: 'success', msg: 'IPs do webhook atualizados.');
    }

    public function render(): View
    {
        return view('livewire.settings.webhook-ip-allowlist', [
            'clusters' => ClusterServer::query()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    /**
     * @param  Collection<int, ClusterServer>  $clusters
     */
    private function normalizeSubmittedIps(Collection $clusters): void
    {
        foreach ($clusters as $cluster) {
            $raw = $this->allowedIps[$cluster->id] ?? null;
            if ($raw === null) {
                $this->allowedIps[$cluster->id] = null;

                continue;
            }
            $trimmed = trim((string) $raw);
            $this->allowedIps[$cluster->id] = $trimmed === '' ? null : $trimmed;
        }
    }

    /**
     * @param  Collection<int, ClusterServer>  $clusters
     * @return array<string, array<int, string>>
     */
    private function buildRules(Collection $clusters): array
    {
        $rules = [];
        foreach ($clusters as $cluster) {
            $rules[sprintf('allowedIps.%s', $cluster->id)] = ['nullable', 'string', 'max:45', 'ip'];
        }

        return $rules;
    }

    /**
     * @param  Collection<int, ClusterServer>  $clusters
     */
    private function persistUpdates(Collection $clusters): void
    {
        foreach ($clusters as $cluster) {
            $newIp = $this->allowedIps[$cluster->id] ?? null;
            $cluster->refresh();
            $previous = $cluster->webhook_allowed_ip ?? null;

            if ($previous === $newIp) {
                continue;
            }

            $cluster->update(['webhook_allowed_ip' => $newIp]);
            $this->writeAuditEntry($cluster, $newIp);
        }
    }

    private function writeAuditEntry(ClusterServer $cluster, ?string $newIp): void
    {
        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'actor_id' => auth()->id(),
            'action' => 'cluster_server.webhook_ip_updated',
            'resource_type' => 'cluster_server',
            'resource_id' => $cluster->id,
            'payload' => [
                'webhook_allowed_ip' => $newIp,
            ],
            'cluster_server_id' => $cluster->id,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
