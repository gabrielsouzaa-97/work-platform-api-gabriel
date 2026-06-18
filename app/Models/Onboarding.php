<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Onboarding\Enums\OnboardingState;
use App\Modules\Onboarding\Enums\OnboardingStep;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Onboarding extends Model
{
    use HasFactory;

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = Str::uuid()->toString();
            }
        });
    }

    protected $table = 'onboardings';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_slug',
        'correlation_id',
        'state',
        'current_step',
        'steps',
        'idempotency_key',
        'api_key_id',
        'admin_payload',
        'apps_enabled',
        'branding_fields',
    ];

    protected $hidden = [
        'admin_payload',
    ];

    protected function casts(): array
    {
        return [
            'state' => OnboardingState::class,
            'current_step' => OnboardingStep::class,
            'steps' => 'array',
            'admin_payload' => 'encrypted:array',
            'apps_enabled' => 'array',
            'branding_fields' => 'array',
        ];
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class, 'api_key_id');
    }
}
