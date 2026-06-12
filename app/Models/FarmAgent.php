<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class FarmAgent extends Model
{
    use HasFactory, SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = Str::uuid()->toString();
            }
        });
    }

    protected $table = 'farm_agents';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'farm_id',
        'cluster_server_id',
        'agent_token_hash',
        'mtls_cert_fingerprint',
        'status',
        'last_seen_at',
    ];

    protected $hidden = [
        'agent_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function clusterServer(): BelongsTo
    {
        return $this->belongsTo(ClusterServer::class, 'cluster_server_id');
    }

    public function commands(): HasMany
    {
        return $this->hasMany(AgentCommand::class, 'farm_agent_id');
    }

    public function verifyToken(string $rawToken): bool
    {
        return hash_equals($this->agent_token_hash, hash('sha256', $rawToken));
    }

    public function isOnline(?int $withinSeconds = null): bool
    {
        if ($this->status !== 'active' || $this->last_seen_at === null) {
            return false;
        }

        $threshold = $withinSeconds ?? (int) config('services.agent.online_threshold_seconds', 120);

        return $this->last_seen_at->greaterThan(now()->subSeconds($threshold));
    }
}
