<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Dto;

final readonly class WebhookPayload
{
    public function __construct(
        public string $jobId,
        public string $state,
        public string $cmd,
        public string $client,
        public ?int $exitCode,
        public string $finishedAt,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            jobId: $data['job_id'],
            state: $data['state'],
            cmd: $data['cmd'],
            client: $data['client'],
            exitCode: isset($data['exit_code']) ? (int) $data['exit_code'] : null,
            finishedAt: $data['finished_at'],
        );
    }
}
