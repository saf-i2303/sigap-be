<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ComplaintStatusLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'complaint_id',
        'status',
        'changed_by',
    ];

    public function complaint()
    {
        return $this->belongsTo(Complaint::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}