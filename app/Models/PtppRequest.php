<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PtppRequest extends Model
{
    protected $fillable = [
        'ptpp_number',
        'user_id',
        'recipient_id',
        'title',
        'description',
        'location',
        'to_function',
        'from_function',
        'sender_signature_name',
        'sender_short_name',
        'sender_signature_path',
        'reviewer_signature_path',
        'source_category',
        'illustration_path',
        'priority',
        'status',
        'approval_status',
        'work_status',
        'progress_percent',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'reviewer_name_snapshot',
        'work_received_at',
        'completion_submitted_at',
        'verified_by',
        'completed_at',
    ];

    protected $casts = [
        'progress_percent' => 'integer',
        'reviewed_at' => 'datetime',
        'work_received_at' => 'datetime',
        'completion_submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $attributes = [
        'approval_status' => 'menunggu_persetujuan',
        'work_status' => 'menunggu_diterima',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function progressUpdates(): HasMany
    {
        return $this->hasMany(PtppProgressUpdate::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(PtppRequestHistory::class);
    }
}
