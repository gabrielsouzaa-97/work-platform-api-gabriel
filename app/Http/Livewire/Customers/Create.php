<?php

declare(strict_types=1);

namespace App\Http\Livewire\Customers;

use App\Models\AppCatalogEntry;
use App\Models\ClusterServer;
use App\Models\Operator;
use App\Models\Plan;
use App\Modules\ClusterServers\Actions\SyncWebhookSecretAction;
use App\Modules\ClusterServers\Services\WebhookSecretGenerator;
use App\Modules\Customers\Actions\ProvisionCustomerAction;
use App\Modules\Customers\Dto\ProvisionPayload;
use App\Modules\Customers\Dto\ResolvedProvisionContext;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Customers\Exceptions\IdempotencyConflictException;
use App\Modules\Customers\Exceptions\StateConflictException;
use App\Modules\Customers\Validation\ProvisioningReadinessValidator;
use App\Modules\Product\Services\PlanAppResolver;
use App\Support\DomainNormalizer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
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
    public ?string $planSlug = null;

    #[Validate('required|string|max:253')]
    public string $domain = '';

    /** @var list<string> */
    #[Validate('nullable|array')]
    public array $selectedAppIds = [];

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
        $defaultSlug = Plan::query()
            ->where('is_default', true)
            ->where('status', 'active')
            ->value('slug');

        if (is_string($defaultSlug) && $defaultSlug !== '') {
            $this->planSlug = $defaultSlug;
        }
    }

    public function updatedDomain(): void
    {
        $this->normalizedDomain = DomainNormalizer::normalize($this->domain);
    }

    public function updatedPlanSlug(): void
    {
        $this->selectedAppIds = [];
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

    /**
     * @return Collection<int, AppCatalogEntry>
     */
    #[Computed]
    public function availableApps(): Collection
    {
        if ($this->planSlug === null || $this->planSlug === '') {
            return AppCatalogEntry::query()->whereRaw('1 = 0')->get();
        }

        return AppCatalogEntry::query()
            ->select('app_catalog_entries.*')
            ->join('plan_apps', 'plan_apps.app_catalog_id', '=', 'app_catalog_entries.id')
            ->where('plan_apps.plan_slug', $this->planSlug)
            ->orderBy('app_catalog_entries.label')
            ->get();
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

        $resolvedApps = [];

        $this->withValidator(function ($validator): void {
            $validator->after(function ($v): void {
                $this->appendProvisioningReadinessErrors($v);
            });
        });

        $this->validate();

        $planSlug = $this->planSlug !== null && $this->planSlug !== '' ? $this->planSlug : null;
        $resolvedApps = app(PlanAppResolver::class)->resolve($planSlug, $this->selectedAppIds);

        $this->submitting = true;
        $this->errorMessage = '';

        /** @var Operator $operator */
        $operator = auth()->user();

        $payload = new ProvisionPayload(
            slug: $this->slug,
            domain: DomainNormalizer::normalize($this->domain),
            clusterServerId: $this->clusterServerId,
            apps: $resolvedApps,
            fullApps: $this->fullApps,
            logoPath: $this->logo?->getRealPath(),
            backgroundPath: $this->background?->getRealPath(),
            suiteCatalog: $this->suiteCatalog,
            imageMode: $this->imageMode,
            planSlug: $this->planSlug !== null && $this->planSlug !== '' ? $this->planSlug : null,
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

    private function appendProvisioningReadinessErrors($validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $planSlug = $this->planSlug !== null && $this->planSlug !== '' ? $this->planSlug : null;

        try {
            $resolvedApps = app(PlanAppResolver::class)->resolve($planSlug, $this->selectedAppIds);
            $context = new ResolvedProvisionContext(
                imageMode: $this->imageMode,
                suiteCatalog: $this->suiteCatalog && ! $this->fullApps,
                fullApps: $this->fullApps,
                legacyVendor: false,
                resolvedApps: $resolvedApps,
                planSlug: $planSlug,
                clusterServerId: $this->clusterServerId,
            );
            app(ProvisioningReadinessValidator::class)->assertValid($context);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add($field, $message);
                }
            }
        }
    }

    public function render(): View
    {
        return view('livewire.customers.create', [
            'clusters' => ClusterServer::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'plans' => Plan::query()->where('status', 'active')->orderBy('name')->get(['slug', 'name']),
        ]);
    }
}
