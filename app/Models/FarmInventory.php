<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmInventory extends Model
{
    protected $table = 'farm_inventories';

    protected $fillable = [
        'farm_id',
        'active_tenants',
        'max_tenants',
        'available_slots',
        'platform_version',
        'latency_ms',
        'reported_at',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
        ];
    }

    public function farmAgent(): BelongsTo
    {
        return $this->belongsTo(FarmAgent::class, 'farm_id', 'farm_id');
    }
}
