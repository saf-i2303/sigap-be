<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AdminSystemLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'admin_id',
        'action',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}