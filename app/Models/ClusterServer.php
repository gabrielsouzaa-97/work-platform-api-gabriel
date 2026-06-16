<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ClusterServer extends Model
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

    protected $table = 'cluster_servers';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'ssh_host',
        'ssh_port',
        'ssh_user',
        'sftp_user',
        'sftp_private_key_encrypted',
        'webhook_secret_version',
        'webhook_allowed_ip',
        'nextcloud_version',
        'schema_version',
        'status',
        'last_health_at',
    ];

    protected function casts(): array
    {
        return [
            'ssh_private_key_encrypted' => 'encrypted',
            'sftp_private_key_encrypted' => 'encrypted',
            'webhook_secret_encrypted' => 'encrypted',
            'last_health_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'cluster_server_id');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class, 'cluster_server_id');
    }

    public function webhookSecretHistory(): HasMany
    {
        return $this->hasMany(WebhookSecretHistory::class, 'cluster_server_id');
    }

    public function farmAgent(): HasOne
    {
        return $this->hasOne(FarmAgent::class, 'cluster_server_id');
    }
}
