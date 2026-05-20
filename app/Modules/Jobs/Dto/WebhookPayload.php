<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Dto;

final readonly class WebhookPayload
{
    public const EVENT_STARTED = 'job.started';

    public const EVENT_FINISHED = 'job.finished';

    public function __construct(
        public string $jobId,
        public string $state,
        public string $event,
        public ?string $cmd,
        public ?string $client,
        public ?int $exitCode,
        public ?string $startedAt,
        public ?string $finishedAt,
        public ?int $durationMs,
    ) {}

    /**
     * Builds the DTO tolerating the upstream's additive schema (schema_version="1"):
     *
     *  - `event` is optional: payloads without it are treated as `job.finished` so
     *    the API stays backwards-compatible with the pre-event upstream releases.
     *  - `finished_at`, `exit_code`, `duration_ms` are absent on `job.started`.
     *  - `ts` (event timestamp) is the fallback for `finished_at` on `job.finished`
     *    and for `started_at` on `job.started` — see upstream `_fire_callback`.
     */
    public static function fromArray(array $data): self
    {
        $event = isset($data['event']) && is_string($data['event'])
            ? $data['event']
            : self::EVENT_FINISHED;

        $ts = $data['ts'] ?? null;

        $startedAt = $data['started_at'] ?? null;
        if ($startedAt === null && $event === self::EVENT_STARTED) {
            $startedAt = $ts;
        }

        $finishedAt = $data['finished_at'] ?? null;
        if ($finishedAt === null && $event === self::EVENT_FINISHED) {
            $finishedAt = $ts;
        }

        return new self(
            jobId: $data['job_id'],
            state: $data['state'],
            event: $event,
            cmd: $data['cmd'] ?? null,
            client: $data['client'] ?? null,
            exitCode: isset($data['exit_code']) ? (int) $data['exit_code'] : null,
            startedAt: $startedAt,
            finishedAt: $finishedAt,
            durationMs: isset($data['duration_ms']) ? (int) $data['duration_ms'] : null,
        );
    }

    public function isStarted(): bool
    {
        return $this->event === self::EVENT_STARTED;
    }

    public function isFinished(): bool
    {
        return $this->event === self::EVENT_FINISHED;
    }
}
