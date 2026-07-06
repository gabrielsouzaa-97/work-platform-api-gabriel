<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TenantUser extends Model
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

    protected $table = 'tenant_users';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'customer_slug',
        'username',
        'email',
        'quota',
        'groups',
        'origin',
        'user_template_slug',
        'synced_at',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'groups' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_slug', 'slug');
    }
}
