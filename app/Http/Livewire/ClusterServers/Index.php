<?php

declare(strict_types=1);

namespace App\Http\Livewire\ClusterServers;

use App\Mail\WebhookSecretRotatedMail;
use App\Models\ClusterServer;
use App\Modules\ClusterServers\Actions\RemoveClusterServerAction;
use App\Modules\ClusterServers\Actions\RotateWebhookSecretAction;
use App\Modules\Core\Ssh\Exceptions\SshClientException;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Integration\Dto\ProbeClusterHealthCommand;
use App\Modules\Integration\Exceptions\UpstreamUnavailableException;
use App\Modules\Integration\Services\PlatformPortFactory;
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

    public bool $showRemoveModal = false;

    public ?string $removeClusterId = null;

    public string $removeClusterName = '';

    public string $confirmInput = '';

    public string $removeError = '';

    public function openRemoveModal(string $clusterId): void
    {
        Gate::authorize('manage-cluster-servers');
        $cluster = ClusterServer::findOrFail($clusterId);
        $this->removeClusterId = $cluster->id;
        $this->removeClusterName = $cluster->name;
        $this->showRemoveModal = true;
        $this->confirmInput = '';
        $this->removeError = '';
    }

    public function removeCluster(RemoveClusterServerAction $action): void
    {
        Gate::authorize('manage-cluster-servers');
        $this->removeError = '';

        $cluster = ClusterServer::findOrFail($this->removeClusterId);

        if ($this->confirmInput !== $cluster->name) {
            $this->removeError = 'Nome digitado não confere.';

            return;
        }

        try {
            $action->execute($cluster);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === RemoveClusterServerAction::ERR_ACTIVE_CUSTOMERS) {
                $this->dispatch('toast', type: 'error', msg: 'Não é possível remover: existem customers ativos vinculados.');

                return;
            }

            throw $e;
        }

        $this->showRemoveModal = false;
        $this->confirmInput = '';
        $this->removeClusterId = null;
        $this->removeClusterName = '';
        $this->dispatch('toast', type: 'success', msg: 'Cluster removido com sucesso.');
    }

    public function testConnection(string $clusterId, PlatformPortFactory $factory): void
    {
        Gate::authorize('manage-cluster-servers');
        $cluster = ClusterServer::findOrFail($clusterId);

        try {
            $report = $factory->for($cluster)->probeClusterHealth(
                new ProbeClusterHealthCommand($cluster, 10),
            );

            if ($report->exitCode === 0) {
                $cluster->update(['status' => 'active', 'last_health_at' => now()]);
                $this->dispatch('toast', type: 'success', msg: 'Conexão OK');
            } else {
                $cluster->update(['status' => 'unreachable', 'last_health_at' => now()]);
                $this->dispatch('toast', type: 'warning', msg: "Comando retornou exit {$report->exitCode}");
            }
        } catch (UpstreamUnavailableException $e) {
            $cluster->update(['status' => 'unreachable', 'last_health_at' => now()]);

            if ($e->getPrevious() instanceof SshTimeoutException) {
                $this->dispatch('toast', type: 'error', msg: 'Timeout ao conectar');

                return;
            }

            if ($e->getPrevious() instanceof SshConnectionException) {
                report($e);
                $this->dispatch('toast', type: 'error', msg: SshClientException::userMessageFor($e->getPrevious()));

                return;
            }

            $previous = $e->getPrevious();
            if ($previous instanceof SshRemoteException) {
                $this->dispatch('toast', type: 'warning', msg: "SSH OK mas comando retornou exit {$previous->remoteExitCode}");

                return;
            }

            $this->dispatch('toast', type: 'error', msg: 'Erro inesperado');
            report($e);
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
