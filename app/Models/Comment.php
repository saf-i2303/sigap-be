<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasUuids;

    protected $fillable = [
        'admin_id',
        'petugas_id',
        'complaint_id',
        'status_after_response',
        'message',
        'estimated_at',
        'type',
    ];

    protected $casts = [
        'estimated_at' => 'datetime',
    ];

    public function complaint()
    {
        return $this->belongsTo(Complaint::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function petugas()
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }

    public function images()
    {
        return $this->hasMany(AdminResponseImage::class);
    }
}