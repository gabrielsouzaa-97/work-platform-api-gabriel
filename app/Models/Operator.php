<?php

declare(strict_types=1);

namespace App\Models;

use App\Mail\OperatorPasswordResetMail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class Operator extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $table = 'operators';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'email',
        'name',
        'password_hash',
        'invite_expires_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password_hash',
        'invite_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'invite_expires_at' => 'datetime',
            'last_login_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getRememberTokenName(): ?string
    {
        return null;
    }

    public function sendPasswordResetNotification($token): void
    {
        $resetUrl = URL::temporarySignedRoute(
            'password.reset',
            now()->addMinutes(60),
            ['token' => $token, 'email' => $this->email],
        );

        Mail::to($this->email)->send(new OperatorPasswordResetMail($this, $resetUrl));
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }
}
