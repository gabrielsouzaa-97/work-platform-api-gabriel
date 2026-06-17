<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'actor_id',
        'api_key_id',
        'action',
        'resource_type',
        'resource_id',
        'payload',
        'cluster_server_id',
        'job_id',
        'ip',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booting(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->created_at)) {
                $model->created_at = now();
            }

            if (empty($model->api_key_id)) {
                $apiKey = current_api_key();

                if ($apiKey !== null) {
                    $model->api_key_id = $apiKey->id;
                }
            }
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Operator::class, 'actor_id');
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class, 'api_key_id');
    }

    public function clusterServer(): BelongsTo
    {
        return $this->belongsTo(ClusterServer::class, 'cluster_server_id');
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id', 'job_id');
    }
}
