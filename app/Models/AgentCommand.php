<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AgentCommand extends Model
{
    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = Str::uuid()->toString();
            }
        });
    }

    protected $table = 'agent_commands';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'farm_agent_id',
        'operation_id',
        'operation',
        'payload',
        'idempotency_key',
        'status',
        'requested_at',
        'acked_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'requested_at' => 'datetime',
            'acked_at' => 'datetime',
        ];
    }

    public function farmAgent(): BelongsTo
    {
        return $this->belongsTo(FarmAgent::class, 'farm_agent_id');
    }
}
