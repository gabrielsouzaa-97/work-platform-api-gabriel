<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Dto;

final readonly class WebhookPayload
{
    public function __construct(
        public string $jobId,
        public string $state,
        public ?string $cmd,
        public ?string $client,
        public ?int $exitCode,
        public string $finishedAt,
    ) {}

    public static function fromArray(array $data): self
    {
        // Upstream sends "ts" as event timestamp; "finished_at" kept for forward compatibility.
        $finishedAt = $data['finished_at'] ?? $data['ts'] ?? now()->toIso8601String();

        return new self(
            jobId: $data['job_id'],
            state: $data['state'],
            cmd: $data['cmd'] ?? null,
            client: $data['client'] ?? null,
            exitCode: isset($data['exit_code']) ? (int) $data['exit_code'] : null,
            finishedAt: $finishedAt,
        );
    }
}
