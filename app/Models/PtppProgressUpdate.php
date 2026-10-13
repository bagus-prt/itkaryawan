<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class PtppProgressUpdate extends Model
{
    protected $fillable = [
        'ptpp_request_id',
        'user_id',
        'progress_percent',
        'notes',
        'photo_path',
        'recorded_at',
    ];

    protected $casts = [
        'progress_percent' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function ptppRequest()
    {
        return $this->belongsTo(PtppRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
