<?php

declare(strict_types=1);

namespace App\Http\Livewire\ClusterServers;

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\WebhookSecretHistory;
use App\Modules\ClusterServers\Actions\SyncWebhookSecretAction;
use App\Modules\ClusterServers\Services\WebhookSecretGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public string $name = '';

    public string $ssh_host = '';

    public int $ssh_port = 22;

    public string $ssh_user = 'ncsaas-api';

    /** @var string PEM — not bound via wire:model in the blade; read from request() in production. */
    public string $ssh_private_key = '';

    public string $sftp_user = 'ncsaas-sftp';

    /** @var string PEM — Canal B (ncsaas-sftp). Optional at creation; required when branding upload is needed. */
    public string $sftp_private_key = '';

    protected array $rules = [
        'name' => ['required', 'string', 'min:3', 'max:255'],
        'ssh_host' => ['required', 'string', 'max:255'],
        'ssh_port' => ['required', 'integer', 'min:1', 'max:65535'],
        'ssh_user' => ['required', 'string', 'max:100'],
        'sftp_user' => ['nullable', 'string', 'max:100'],
    ];

    public function save(WebhookSecretGenerator $secretGen, SyncWebhookSecretAction $syncAction): mixed
    {
        Gate::authorize('manage-cluster-servers');
        $this->validate();

        // PEM is read from request() in production (no wire:model in blade).
        // In tests, ->set('ssh_private_key', ...) populates the property directly.
        $pem = $this->ssh_private_key !== '' ? $this->ssh_private_key : request()->input('ssh_private_key', '');

        if (! preg_match('/-----BEGIN[\s\S]+?KEY-----[\s\S]+?-----END[\s\S]+?KEY-----/', $pem)) {
            $this->addError('ssh_private_key', 'O campo chave privada SSH deve ser um PEM válido (BEGIN/END KEY).');

            return null;
        }

        $sftpPem = $this->sftp_private_key !== '' ? $this->sftp_private_key : request()->input('sftp_private_key', '');

        if ($sftpPem !== '' && ! preg_match('/-----BEGIN[\s\S]+?KEY-----[\s\S]+?-----END[\s\S]+?KEY-----/', $sftpPem)) {
            $this->addError('sftp_private_key', 'A chave privada SFTP deve ser um PEM válido (BEGIN/END KEY).');

            return null;
        }

        // Save plain secret before create() so we can pass it to SSH (cast decrypts on read,
        // but holding the plain var is explicit and avoids any future cast-related surprises).
        $plainSecret = $secretGen->generate();

        $cluster = DB::transaction(function () use ($pem, $sftpPem, $plainSecret) {
            $created = ClusterServer::create([
                'name' => $this->name,
                'ssh_host' => $this->ssh_host,
                'ssh_port' => $this->ssh_port,
                'ssh_user' => $this->ssh_user,
                'ssh_private_key_encrypted' => $pem,
                'sftp_user' => $this->sftp_user ?: 'ncsaas-sftp',
                'sftp_private_key_encrypted' => $sftpPem !== '' ? $sftpPem : null,
                'webhook_secret_encrypted' => $plainSecret,
                'webhook_secret_version' => 1,
                'schema_version' => 1,
                'status' => 'active',
            ]);

            WebhookSecretHistory::create([
                'cluster_server_id' => $created->id,
                'secret_encrypted' => $created->webhook_secret_encrypted,
                'version' => 1,
                'valid_from' => now(),
                'valid_until' => null,
            ]);

            return $created;
        });

        unset($pem);

        try {
            $syncAction->execute($cluster, $plainSecret);
        } catch (\Throwable $e) {
            $cluster->update(['status' => 'error']);
            AuditLog::create([
                'id' => Str::uuid()->toString(),
                'actor_id' => auth()->id(),
                'action' => 'cluster_server.secret_sync_failed',
                'resource_type' => 'cluster_server',
                'resource_id' => $cluster->id,
                'payload' => ['error' => $e->getMessage()],
            ]);
            $this->addError('ssh_private_key', 'Cluster salvo mas falha ao sincronizar webhook secret: '.$e->getMessage());

            return null;
        }

        return $this->redirect(route('cluster-servers.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.cluster-servers.create');
    }
}
