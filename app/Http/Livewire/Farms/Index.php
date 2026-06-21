<?php

declare(strict_types=1);

namespace App\Http\Livewire\Farms;

use App\Models\FarmInventory;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public function mount(): void
    {
        Gate::authorize('manage-operators');
    }

    public function render(): View
    {
        return view('livewire.farms.index', [
            'inventories' => FarmInventory::query()
                ->orderBy('farm_id')
                ->get(),
        ]);
    }
}
