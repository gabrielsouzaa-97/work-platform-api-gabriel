<?php

declare(strict_types=1);

namespace App\Http\Livewire\ClusterServers;

use App\Mail\WebhookSecretRotatedMail;
use App\Models\ClusterServer;
use App\Modules\ClusterServers\Actions\RotateWebhookSecretAction;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public function testConnection(string $clusterId, SshClientInterface $ssh): void
    {
        Gate::authorize('manage-cluster-servers');
        $cluster = ClusterServer::findOrFail($clusterId);

        try {
            $resp = $ssh->ping($cluster, 10);

            if ($resp->exitCode === 0) {
                $cluster->update(['status' => 'active', 'last_health_at' => now()]);
                $this->dispatch('toast', type: 'success', msg: 'Conexão OK');
            } else {
                $cluster->update(['status' => 'unreachable', 'last_health_at' => now()]);
                $this->dispatch('toast', type: 'warning', msg: "Comando retornou exit {$resp->exitCode}");
            }
        } catch (SshTimeoutException) {
            $cluster->update(['status' => 'unreachable', 'last_health_at' => now()]);
            $this->dispatch('toast', type: 'error', msg: 'Timeout ao conectar');
        } catch (SshRemoteException $e) {
            $cluster->update(['status' => 'unreachable', 'last_health_at' => now()]);
            $this->dispatch('toast', type: 'warning', msg: "SSH OK mas comando retornou exit {$e->remoteExitCode}");
        } catch (SshConnectionException $e) {
            $cluster->update(['status' => 'unreachable', 'last_health_at' => now()]);
            $this->dispatch('toast', type: 'error', msg: 'Conexão falhou: '.$e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', msg: 'Erro inesperado');
            report($e);
        }
    }

    public function rotateSecret(string $clusterId, RotateWebhookSecretAction $action): void
    {
        Gate::authorize('manage-cluster-servers');
        $cluster = ClusterServer::findOrFail($clusterId);

        try {
            $new = $action->execute($cluster, auth()->id());
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', msg: 'Não foi possível rotacionar: histórico de secret ausente para este cluster.');

            return;
        }

        Mail::to(auth()->user()->email)->queue(new WebhookSecretRotatedMail($cluster, $new));

        $graceHours = (int) config('services.webhook.grace_period_hours', 24);
        $graceUntil = $new->valid_from->copy()->addHours($graceHours)->format('d/m/Y H:i');
        $this->dispatch('toast', type: 'success', msg: "Secret rotacionado. Versão anterior válida até {$graceUntil}.");
    }

    public function render(): View
    {
        return view('livewire.cluster-servers.index', [
            'clusters' => ClusterServer::orderBy('created_at', 'desc')->paginate(25),
        ]);
    }
}
