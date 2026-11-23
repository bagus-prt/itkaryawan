<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PtppRequestHistory extends Model
{
    protected $fillable = [
        'ptpp_request_id',
        'actor_id',
        'action',
        'old_approval_status',
        'new_approval_status',
        'old_work_status',
        'new_work_status',
        'notes',
    ];

    public function ptppRequest(): BelongsTo
    {
        return $this->belongsTo(PtppRequest::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
