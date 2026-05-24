<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AdminResponseImage extends Model
{
    use HasUuids;

    protected $fillable = [
        'comment_id',
        'image_url',
        'latitude',
        'longitude',
        'taken_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'is_validated' => 'boolean',
            'taken_at' => 'datetime',
        ];
    }

    public function comment()
    {
        return $this->belongsTo(Comment::class);
    }
}