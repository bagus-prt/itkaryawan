<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function ptppRequest(): BelongsTo
    {
        return $this->belongsTo(PtppRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
