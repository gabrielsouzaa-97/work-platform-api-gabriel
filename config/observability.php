<?php

declare(strict_types=1);

return [

    'enabled' => env('OBSERVABILITY_ENABLED', true),

    /*
    | Non-terminal jobs older than this threshold emit transport.job_stuck_sla.
    | Default 60s matches staging SLA from ISSUE-038 / ADR final.md §6.
    */
    'stuck_job_sla_seconds' => (int) env('OBSERVABILITY_STUCK_JOB_SLA_SECONDS', 60),

    /*
    | Jobs dispatched without any webhook callback after this threshold emit
    | transport.webhook_missing (checked by jobs:observability-check).
    */
    'missing_webhook_sla_seconds' => (int) env('OBSERVABILITY_MISSING_WEBHOOK_SLA_SECONDS', 60),

    'dispatch_cache_ttl_seconds' => (int) env('OBSERVABILITY_DISPATCH_CACHE_TTL', 86400),

    'parity' => [
        'enabled' => env('OBSERVABILITY_PARITY_ENABLED', true),
        'lookback_hours' => (int) env('OBSERVABILITY_PARITY_LOOKBACK_HOURS', 24),
        'min_samples_per_transport' => (int) env('OBSERVABILITY_PARITY_MIN_SAMPLES', 5),
        'success_rate_delta_threshold' => (float) env('OBSERVABILITY_PARITY_DELTA', 0.15),
    ],

];
