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
        'branding_meta',
        'last_sync_at',
    ];

    protected function casts(): array
    {
        return [
            'branding_meta' => 'array',
            'last_sync_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function clusterServer(): BelongsTo
    {
        return $this->belongsTo(ClusterServer::class, 'cluster_server_id');
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
