<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function isKadis(): bool
    {
        return $this->role === UserRole::KADIS;
    }

    public function isBendahara(): bool
    {
        return $this->role === UserRole::BENDAHARA;
    }

    public function canApprove(): bool
    {
        return in_array($this->role, [UserRole::KADIS, UserRole::ADMIN]);
    }

    public function canVerify(): bool
    {
        return in_array($this->role, [UserRole::BENDAHARA, UserRole::ADMIN]);
    }
}
