<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $table = 'customers';

    protected $primaryKey = 'slug';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'slug',
        'cluster_server_id',
        'domain',
        'status',
        'tier',
        'plan_slug',
        'image_mode',
        'objectstore_enabled',
        'objectstore_bucket',
        'branding_meta',
        'mail_provision_payload',
        'last_sync_at',
    ];

    protected function casts(): array
    {
        return [
            'image_mode' => 'boolean',
            'objectstore_enabled' => 'boolean',
            'branding_meta' => 'array',
            'mail_provision_payload' => 'array',
            'last_sync_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function clusterServer(): BelongsTo
    {
        return $this->belongsTo(ClusterServer::class, 'cluster_server_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_slug', 'slug');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class, 'customer_slug', 'slug');
    }

    public function idempotencyKeys(): HasMany
    {
        return $this->hasMany(IdempotencyKey::class, 'customer_slug', 'slug');
    }
}
