<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class PtppRequest extends Model
{
    protected $fillable = [
        'ptpp_number',
        'user_id',
        'title',
        'description',
        'location',
        'priority',
        'status',
        'progress_percent',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'completion_submitted_at',
        'verified_by',
        'completed_at',
    ];

    protected $casts = [
        'progress_percent' => 'integer',
        'reviewed_at' => 'datetime',
        'completion_submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function progressUpdates()
    {
        return $this->hasMany(PtppProgressUpdate::class);
    }
}
