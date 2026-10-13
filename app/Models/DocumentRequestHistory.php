<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class DocumentRequestHistory extends Model
{
    protected $fillable = [
        'document_request_id',
        'actor_id',
        'action',
        'old_status',
        'new_status',
        'notes',
    ];

    public function documentRequest()
    {
        return $this->belongsTo(DocumentRequest::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
