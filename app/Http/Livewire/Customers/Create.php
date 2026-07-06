<?php

declare(strict_types=1);

namespace App\Http\Livewire\Customers;

use App\Models\ClusterServer;
use App\Models\Operator;
use App\Models\Plan;
use App\Modules\ClusterServers\Actions\SyncWebhookSecretAction;
use App\Modules\ClusterServers\Services\WebhookSecretGenerator;
use App\Modules\Customers\Actions\ProvisionCustomerAction;
use App\Modules\Customers\Dto\ProvisionPayload;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Customers\Exceptions\IdempotencyConflictException;
use App\Modules\Customers\Exceptions\StateConflictException;
use App\Support\DomainNormalizer;
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

    #[Validate('nullable|string|exists:plans,slug')]
    public string $planSlug = '';

    #[Validate('required|string|max:253')]
    public string $domain = '';

    #[Validate('nullable|array')]
    public array $apps = [];

    #[Validate('boolean')]
    public bool $fullApps = false;

    #[Validate('boolean')]
    public bool $imageMode = false;

    #[Validate('boolean')]
    public bool $suiteCatalog = true;

    #[Validate('nullable|file|mimes:png,jpg,jpeg|max:5120')]
    public mixed $logo = null;

    #[Validate('nullable|file|mimes:png,jpg,jpeg|max:5120')]
    public mixed $background = null;

    public bool $submitting = false;

    public string $errorMessage = '';

    public string $normalizedDomain = '';

    public function mount(): void
    {
        $defaultPlan = Plan::query()->where('is_default', true)->value('slug');

        if (is_string($defaultPlan) && $defaultPlan !== '') {
            $this->planSlug = $defaultPlan;
        }
    }

    public function updatedDomain(): void
    {
        $this->normalizedDomain = DomainNormalizer::normalize($this->domain);
    }

    public function updatedClusterServerId(): void
    {
        if ($this->clusterServerId === '') {
            return;
        }

        $cluster = ClusterServer::query()
            ->whereKey($this->clusterServerId)
            ->value('name');

        if ($cluster === null) {
            return;
        }

        $isImagePilot = str_contains(strtolower($cluster), 'image');

        if ($isImagePilot) {
            $this->imageMode = true;
            $this->suggestTenantDomain();
        }
    }

    public function updatedSlug(): void
    {
        $this->suggestTenantDomain();
    }

    private function suggestTenantDomain(): void
    {
        if ($this->slug === '' || $this->clusterServerId === '') {
            return;
        }

        $clusterName = ClusterServer::query()
            ->whereKey($this->clusterServerId)
            ->value('name');

        if ($clusterName === null) {
            return;
        }

        $suffix = match (true) {
            str_contains(strtolower($clusterName), 'image') => '.image-pilot.mework360.com.br',
            str_contains(strtolower($clusterName), 'labwork') => '.labwork.mework360.com.br',
            default => null,
        };

        if ($suffix === null) {
            return;
        }

        $suggested = $this->slug.$suffix;

        if ($this->domain === '' || str_ends_with($this->domain, $suffix)) {
            $this->domain = $suggested;
        }
    }

    public function submit(ProvisionCustomerAction $action): void
    {
        $this->save($action);
    }

    public function save(
        ProvisionCustomerAction|WebhookSecretGenerator $action,
        ?SyncWebhookSecretAction $syncAction = null,
    ): void {
        if ($action instanceof WebhookSecretGenerator) {
            $action = app(ProvisionCustomerAction::class);
        }

        $this->validate();

        $this->submitting = true;
        $this->errorMessage = '';

        /** @var Operator $operator */
        $operator = auth()->user();

        $payload = new ProvisionPayload(
            slug: $this->slug,
            domain: DomainNormalizer::normalize($this->domain),
            clusterServerId: $this->clusterServerId,
            apps: $this->apps,
            fullApps: $this->fullApps,
            logoPath: $this->logo?->getRealPath(),
            backgroundPath: $this->background?->getRealPath(),
            suiteCatalog: $this->suiteCatalog,
            imageMode: $this->imageMode,
            planSlug: $this->planSlug !== '' ? $this->planSlug : null,
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
            'plans' => Plan::query()->where('status', 'active')->orderBy('name')->get(['slug', 'name']),
        ]);
    }
}
