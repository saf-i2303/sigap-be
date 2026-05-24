<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Complaint extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'tracking_id',
        'title',
        'description',
        'latitude',
        'longitude',
        'address',
        'wilayah',
        'status',
        'category_id',
        'admin_priority',
        'created_by',
        'verified_at',
        'processed_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'verified_at' => 'datetime',
            'processed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function images()
    {
        return $this->hasMany(ComplaintImage::class);
    }

    public function statusLogs()
    {
        return $this->hasMany(ComplaintStatusLog::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
}