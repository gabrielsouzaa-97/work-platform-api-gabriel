<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Job extends Model
{
    protected $table = 'jobs';

    protected $primaryKey = 'job_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'job_id',
        'customer_slug',
        'cluster_server_id',
        'cmd_canonical',
        'job_type',
        'state',
        'idempotency_key',
        'payload_sanitized',
        'summary',
        'exit_code',
        'queued_at',
        'started_at',
        'finished_at',
        'callback_received_at',
        'last_poll_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_sanitized' => 'array',
            'summary' => 'array',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'callback_received_at' => 'datetime',
            'last_poll_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_slug', 'slug');
    }

    public function clusterServer(): BelongsTo
    {
        return $this->belongsTo(ClusterServer::class, 'cluster_server_id');
    }

    public function idempotencyKeys(): HasMany
    {
        return $this->hasMany(IdempotencyKey::class, 'job_id', 'job_id');
    }
}
