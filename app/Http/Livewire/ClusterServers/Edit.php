<?php

declare(strict_types=1);

namespace App\Http\Livewire\ClusterServers;

use App\Models\ClusterServer;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use phpseclib3\Crypt\PublicKeyLoader;

#[Layout('layouts.app')]
class Edit extends Component
{
    public ClusterServer $clusterServer;

    public string $name = '';

    public string $ssh_host = '';

    public int $ssh_port = 22;

    public string $ssh_user = '';

    public bool $replacingKey = false;

    public string $ssh_private_key = '';

    public string $sftp_user = '';

    public bool $replacingSftpKey = false;

    public string $sftp_private_key = '';

    /** Derived OpenSSH public key for Canal B — shown read-only so admin can install it on the server. */
    public string $sftp_public_key = '';

    protected array $rules = [
        'name' => ['required', 'string', 'min:3', 'max:255'],
        'ssh_host' => ['required', 'string', 'max:255'],
        'ssh_port' => ['required', 'integer', 'min:1', 'max:65535'],
        'ssh_user' => ['required', 'string', 'max:100'],
        'ssh_private_key' => ['nullable', 'string'],
        'sftp_user' => ['nullable', 'string', 'max:100'],
        'sftp_private_key' => ['nullable', 'string'],
    ];

    public function mount(ClusterServer $clusterServer): void
    {
        $this->clusterServer = $clusterServer;
        $this->name = $clusterServer->name;
        $this->ssh_host = $clusterServer->ssh_host;
        $this->ssh_port = $clusterServer->ssh_port ?? 22;
        $this->ssh_user = $clusterServer->ssh_user ?? 'ncsaas-api';
        $this->sftp_user = $clusterServer->sftp_user ?? 'ncsaas-sftp';

        if (! empty($clusterServer->sftp_private_key_encrypted)) {
            $this->sftp_public_key = $this->derivePublicKey($clusterServer->sftp_private_key_encrypted);
        }
    }

    /**
     * Derive the OpenSSH public key from a stored private key PEM.
     * Returns an empty string on failure (invalid key, unsupported type).
     */
    private function derivePublicKey(string $privatePem): string
    {
        try {
            $key = PublicKeyLoader::load($privatePem);

            if (! method_exists($key, 'getPublicKey')) {
                return '';
            }

            return $key->getPublicKey()->toString('OpenSSH');
        } catch (\Throwable) {
            return '';
        }
    }

    public function toggleReplaceKey(): void
    {
        $this->replacingKey = ! $this->replacingKey;
        $this->ssh_private_key = '';
    }

    public function toggleReplaceSftpKey(): void
    {
        $this->replacingSftpKey = ! $this->replacingSftpKey;
        $this->sftp_private_key = '';
    }

    public function save(): mixed
    {
        Gate::authorize('manage-cluster-servers');

        if ($this->replacingKey) {
            $this->validate([
                'name' => ['required', 'string', 'min:3', 'max:255'],
                'ssh_host' => ['required', 'string', 'max:255'],
                'ssh_port' => ['required', 'integer', 'min:1', 'max:65535'],
                'ssh_user' => ['required', 'string', 'max:100'],
                'ssh_private_key' => ['required', 'string', 'min:50'],
            ]);
        } else {
            $this->validate();
        }

        $data = [
            'name' => $this->name,
            'ssh_host' => $this->ssh_host,
            'ssh_port' => $this->ssh_port,
            'ssh_user' => $this->ssh_user,
            'sftp_user' => $this->sftp_user ?: 'ncsaas-sftp',
        ];

        if ($this->replacingKey && $this->ssh_private_key !== '') {
            $this->clusterServer->ssh_private_key_encrypted = $this->ssh_private_key;
        }

        if ($this->replacingSftpKey && $this->sftp_private_key !== '') {
            if (! preg_match('/-----BEGIN[\s\S]+?KEY-----[\s\S]+?-----END[\s\S]+?KEY-----/', $this->sftp_private_key)) {
                $this->addError('sftp_private_key', 'A chave privada SFTP deve ser um PEM válido (BEGIN/END KEY).');

                return null;
            }
            $this->clusterServer->sftp_private_key_encrypted = $this->sftp_private_key;
            $this->sftp_public_key = $this->derivePublicKey($this->sftp_private_key);
        }

        $this->clusterServer->update($data);

        return $this->redirect(route('cluster-servers.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.cluster-servers.edit', [
            'clusterServer' => $this->clusterServer,
        ]);
    }
}
