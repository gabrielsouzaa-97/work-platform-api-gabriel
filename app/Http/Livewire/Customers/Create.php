<?php

declare(strict_types=1);

namespace App\Http\Livewire\Customers;

use App\Models\ClusterServer;
use App\Models\Operator;
use App\Modules\Customers\Actions\ProvisionCustomerAction;
use App\Modules\Customers\Dto\ProvisionPayload;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Customers\Exceptions\IdempotencyConflictException;
use App\Modules\Customers\Exceptions\StateConflictException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Create extends Component
{
    use WithFileUploads;

    #[Validate('required|string|max:64|regex:/^[a-z0-9-]+$/|unique:customers,slug')]
    public string $slug = '';

    #[Validate('required|uuid|exists:cluster_servers,id')]
    public string $clusterServerId = '';

    #[Validate('required|string|max:253')]
    public string $domain = '';

    #[Validate('nullable|array')]
    public array $apps = [];

    #[Validate('boolean')]
    public bool $fullApps = false;

    #[Validate('nullable|file|mimes:png,jpg,jpeg|max:5120')]
    public mixed $logo = null;

    #[Validate('nullable|file|mimes:png,jpg,jpeg|max:5120')]
    public mixed $background = null;

    public bool $submitting = false;

    public string $errorMessage = '';

    public function submit(ProvisionCustomerAction $action): void
    {
        $this->validate();

        $this->submitting = true;
        $this->errorMessage = '';

        /** @var Operator $operator */
        $operator = auth()->user();

        $payload = new ProvisionPayload(
            slug: $this->slug,
            domain: $this->domain,
            clusterServerId: $this->clusterServerId,
            apps: $this->apps,
            fullApps: $this->fullApps,
            logoPath: $this->logo?->getRealPath(),
            backgroundPath: $this->background?->getRealPath(),
        );

        try {
            $result = $action->execute($payload, $operator);
        } catch (IdempotencyConflictException) {
            $this->errorMessage = 'Este customer já foi criado anteriormente.';
            $this->submitting = false;

            return;
        } catch (StateConflictException) {
            $this->errorMessage = 'Conflito de estado com o upstream.';
            $this->submitting = false;

            return;
        } catch (ClusterUnreachableException) {
            $this->errorMessage = 'Cluster inacessível. Tente novamente em instantes.';
            $this->submitting = false;

            return;
        }

        $this->submitting = false;
        $this->redirect(route('customers.show', ['slug' => $result['customer']->slug]));
    }

    public function render(): View
    {
        return view('livewire.customers.create', [
            'clusters' => ClusterServer::where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
