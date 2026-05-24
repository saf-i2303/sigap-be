<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'wilayah',
        'last_login',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login' => 'datetime',
        ];
    }

    // cek role
    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin';
    }

    // relasi
    public function complaints()
    {
        return $this->hasMany(Complaint::class, 'created_by');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'admin_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function systemLogs()
    {
        return $this->hasMany(AdminSystemLog::class, 'admin_id');
    }
}