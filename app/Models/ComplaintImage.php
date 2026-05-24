<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ComplaintImage extends Model
{
    use HasUuids;

    protected $fillable = [
        'complaint_id',
        'image_url',
    ];

    public function complaint()
    {
        return $this->belongsTo(Complaint::class);
    }
}