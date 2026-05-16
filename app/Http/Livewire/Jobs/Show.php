<?php

declare(strict_types=1);

namespace App\Http\Livewire\Jobs;

use App\Models\Job;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
class Show extends Component
{
    public Job $job;

    public function mount(string $jobId): void
    {
        $this->job = Job::query()
            ->where('job_id', $jobId)
            ->with(['customer.clusterServer'])
            ->firstOrFail();
    }

    /**
     * @return list<string>
     */
    public function parsedLogLines(): array
    {
        if (empty($this->job->summary)) {
            return [];
        }

        $summary = $this->job->summary;
        $raw = is_array($summary) ? $summary : explode("\n", (string) $summary);

        return array_values(array_filter(
            array_map(static fn (mixed $row): string => trim((string) $row), $raw),
            static fn (string $line): bool => $line !== '',
        ));
    }

    public function exportLog(): StreamedResponse
    {
        Gate::authorize('manage-operators');

        $lines = $this->parsedLogLines();
        $filename = sprintf('job-%s-log.txt', $this->job->job_id);
        $body = implode("\n", $lines);

        return response()->streamDownload(static function () use ($body): void {
            echo $body;
        }, $filename, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function render(): View
    {
        $logLines = $this->parsedLogLines();

        return view('livewire.jobs.show', [
            'logLines' => $logLines,
            'durationLabel' => $this->formatDurationLabel(),
        ]);
    }

    private function formatDurationLabel(): ?string
    {
        $startedAt = $this->job->started_at;
        $finishedAt = $this->job->finished_at;

        if ($startedAt === null || $finishedAt === null) {
            return null;
        }

        return $startedAt->diffForHumans($finishedAt, true);
    }
}
