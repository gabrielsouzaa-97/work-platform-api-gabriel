<?php

declare(strict_types=1);

namespace App\Http\Livewire\ClusterServers;

use App\Models\ClusterServer;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public function render(): View
    {
        return view('livewire.cluster-servers.index', [
            'clusters' => ClusterServer::orderBy('created_at', 'desc')->paginate(25),
        ]);
    }
}
