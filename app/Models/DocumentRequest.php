<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentRequest extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'letter_number',
        'start_date',
        'end_date',
        'destination',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'pdf_path',
        'department',
        'work_location',
        'employee_name',
        'reviewer_name_snapshot',
        'leave_entitlement_year',
        'leave_entitlement_days',
        'leave_taken_days',
        'transportation',
        'expense_paid_by',
        'return_to_work_date',
        'leave_due_date',
        'companions',
        'employee_signature_path',
        'manager_signature_path',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'return_to_work_date' => 'date',
        'leave_due_date' => 'date',
        'reviewed_at' => 'datetime',
        'companions' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(DocumentRequestHistory::class);
    }
}
