<?php

declare(strict_types=1);

namespace App\Http\Livewire\Customers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Operator;
use App\Modules\Customers\Actions\RemoveCustomerAction;
use App\Modules\Customers\Exceptions\ConfirmationMismatchException;
use App\Modules\Customers\Exceptions\RemoveInProgressException;
use App\Modules\Customers\Exceptions\StateConflictException;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Customer $customer;

    public bool $showRemoveModal = false;

    public string $confirmInput = '';

    public string $removeError = '';

    public bool $backupFirst = true;

    public function mount(string $slug): void
    {
        $this->customer = Customer::with('clusterServer')->findOrFail($slug);
    }

    public function remove(RemoveCustomerAction $action): void
    {
        Gate::authorize('provision-customers');

        $this->removeError = '';

        if ($this->confirmInput !== $this->customer->slug) {
            $this->removeError = 'Slug digitado não confere.';

            return;
        }

        /** @var Operator $operator */
        $operator = auth()->user();

        try {
            $action->execute(
                $this->customer->slug,
                $this->confirmInput,
                $this->backupFirst,
                $operator,
            );
        } catch (ConfirmationMismatchException) {
            $this->removeError = 'Slug digitado não confere.';

            return;
        } catch (RemoveInProgressException) {
            $this->removeError = 'Remoção já em andamento.';

            return;
        } catch (StateConflictException) {
            $this->removeError = 'Conflito de estado no upstream.';

            return;
        } catch (\Throwable $e) {
            $this->removeError = 'Erro inesperado: '.$e->getMessage();

            return;
        }

        $this->showRemoveModal = false;
        $this->confirmInput = '';
        $this->customer->refresh();
        $this->dispatch('toast', type: 'success', msg: 'Remoção iniciada. Aguarde o webhook de conclusão.');
    }

    public function render(): View
    {
        $jobs = Job::where('customer_slug', $this->customer->slug)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $auditLogs = AuditLog::where('resource_type', 'customer')
            ->where('resource_id', $this->customer->slug)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('livewire.customers.show', compact('jobs', 'auditLogs'));
    }
}
