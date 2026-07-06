<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $table = 'plans';

    protected $primaryKey = 'slug';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'default_quota',
        'max_users',
        'max_apps',
        'is_default',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'max_users' => 'integer',
            'max_apps' => 'integer',
        ];
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'plan_slug', 'slug');
    }
}
