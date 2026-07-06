<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UserTemplate extends Model
{
    protected $table = 'user_templates';

    protected $primaryKey = 'slug';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'default_quota',
        'groups',
        'permissions',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'groups' => 'array',
            'permissions' => 'array',
        ];
    }

    public function appCatalogEntries(): BelongsToMany
    {
        return $this->belongsToMany(
            AppCatalogEntry::class,
            'user_template_apps',
            'user_template_slug',
            'app_catalog_id',
            'slug',
            'id',
        );
    }
}
