<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AppCatalogEntry extends Model
{
    use HasFactory;

    protected $table = 'app_catalog_entries';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'app_id',
        'label',
        'description',
        'category',
        'cluster_server_id',
        'is_active',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            if ($model->id === null || $model->id === '') {
                $model->id = Str::uuid()->toString();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function clusterServer(): BelongsTo
    {
        return $this->belongsTo(ClusterServer::class, 'cluster_server_id');
    }
}
