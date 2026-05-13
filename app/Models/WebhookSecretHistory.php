<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WebhookSecretHistory extends Model
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

    protected $table = 'webhook_secret_history';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'cluster_server_id',
        'secret_encrypted',
        'version',
        'valid_from',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'secret_encrypted' => 'encrypted',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }

    public function clusterServer(): BelongsTo
    {
        return $this->belongsTo(ClusterServer::class, 'cluster_server_id');
    }
}
