<?php

declare(strict_types=1);

namespace App\Http\Livewire\ClusterServers;

use App\Models\ClusterServer;
use App\Models\WebhookSecretHistory;
use App\Modules\ClusterServers\Services\WebhookSecretGenerator;
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

    public string $ssh_user = 'root';

    public string $ssh_private_key = '';

    protected array $rules = [
        'name' => ['required', 'string', 'min:3', 'max:255'],
        'ssh_host' => ['required', 'string', 'max:255'],
        'ssh_port' => ['required', 'integer', 'min:1', 'max:65535'],
        'ssh_user' => ['required', 'string', 'max:100'],
        'ssh_private_key' => ['required', 'string', 'regex:/-----BEGIN[\s\S]+?KEY-----[\s\S]+?-----END[\s\S]+?KEY-----/'],
    ];

    public function save(WebhookSecretGenerator $secretGen): mixed
    {
        Gate::authorize('manage-cluster-servers');
        $this->validate();

        // id is not in $fillable — assign directly to bypass mass-assignment guard.
        $cluster = new ClusterServer([
            'name' => $this->name,
            'ssh_host' => $this->ssh_host,
            'ssh_port' => $this->ssh_port,
            'ssh_user' => $this->ssh_user,
            'ssh_private_key_encrypted' => $this->ssh_private_key,
            'webhook_secret_encrypted' => $secretGen->generate(),
            'webhook_secret_version' => 1,
            'schema_version' => 1,
            'status' => 'active',
        ]);
        $cluster->id = Str::uuid()->toString();
        $cluster->save();

        WebhookSecretHistory::create([
            'cluster_server_id' => $cluster->id,
            'secret_encrypted' => $cluster->webhook_secret_encrypted,
            'version' => 1,
            'valid_from' => now(),
            'valid_until' => null,
        ]);

        return $this->redirect(route('cluster-servers.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.cluster-servers.create');
    }
}
