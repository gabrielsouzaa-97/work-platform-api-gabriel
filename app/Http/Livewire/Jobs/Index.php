<?php

declare(strict_types=1);

namespace App\Http\Livewire\Jobs;

use App\Models\Job;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'tab')]
    public string $activeTab = 'all';

    public string $stateFilter = '';

    #[Url(as: 'job_type')]
    public string $jobTypeFilter = '';

    #[Url(as: 'customer')]
    public string $customerFilter = '';

    public bool $autoRefresh = true;

    public function mount(): void
    {
        $this->syncStateFilterFromTab($this->activeTab);
    }

    public function updatingActiveTab(string $value): void
    {
        $this->resetPage();
        $this->syncStateFilterFromTab($value);
    }

    public function updatingJobTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingCustomerFilter(): void
    {
        $this->resetPage();
    }

    public function exportCsv(): StreamedResponse
    {
        Gate::authorize('manage-operators');

        $filename = 'jobs-export-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(
            fn () => $this->writeCsvToOutputStream(),
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    private function writeCsvToOutputStream(): void
    {
        $out = fopen('php://output', 'w');
        if ($out === false) {
            throw new \RuntimeException('Unable to open output stream for CSV export.');
        }

        try {
            fputcsv($out, ['job_id', 'customer_slug', 'job_type', 'state', 'exit_code', 'queued_at', 'finished_at']);
            $this->baseJobsQuery()
                ->orderByDesc('created_at')
                ->chunk(500, function ($jobs) use ($out): void {
                    foreach ($jobs as $job) {
                        fputcsv($out, [
                            $job->job_id,
                            $job->customer_slug,
                            $job->job_type,
                            $job->state,
                            $job->exit_code,
                            $job->queued_at?->format('Y-m-d H:i:s'),
                            $job->finished_at?->format('Y-m-d H:i:s'),
                        ]);
                    }
                });
        } finally {
            fclose($out);
        }
    }

    public function render(): View
    {
        $jobs = $this->baseJobsQuery()
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('livewire.jobs.index', array_merge(
            compact('jobs'),
            $this->stats(),
        ));
    }

    /**
     * @return array{
     *     activeOps: int,
     *     completed24h: int,
     *     failed24h: int,
     *     avgProvisionTime: int|null
     * }
     */
    private function stats(): array
    {
        return [
            'activeOps' => Job::whereIn('state', ['queued', 'running'])->count(),
            'completed24h' => Job::where('state', 'success')
                ->where('finished_at', '>=', now()->subDay())
                ->count(),
            'failed24h' => Job::where('state', 'failed')
                ->where('finished_at', '>=', now()->subDay())
                ->count(),
            'avgProvisionTime' => $this->averageProvisionSeconds(),
        ];
    }

    private function averageProvisionSeconds(): ?int
    {
        $avg = Job::query()
            ->where('state', 'success')
            ->whereNotNull('started_at')
            ->whereNotNull('finished_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, finished_at)) as avg_sec')
            ->value('avg_sec');

        if ($avg === null) {
            return null;
        }

        return (int) round((float) $avg);
    }

    private function baseJobsQuery(): Builder
    {
        return Job::query()
            ->with(['customer', 'clusterServer'])
            ->when($this->stateFilter !== '', fn ($q) => $q->where('state', $this->stateFilter))
            ->when($this->jobTypeFilter !== '', fn ($q) => $q->where('job_type', $this->jobTypeFilter))
            ->when(
                $this->customerFilter !== '',
                fn ($q) => $q->where('customer_slug', 'like', '%'.addcslashes($this->customerFilter, '%_').'%')
            );
    }

    private function syncStateFilterFromTab(string $tab): void
    {
        $this->stateFilter = match ($tab) {
            'running' => 'running',
            'failed' => 'failed',
            default => '',
        };
    }
}
