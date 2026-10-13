<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\DocumentRequestHistory;

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
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function histories()
    {
        return $this->hasMany(DocumentRequestHistory::class);
    }
}
