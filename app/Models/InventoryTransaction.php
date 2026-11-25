<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryTransaction extends Model
{
    protected $fillable = [
        'transaction_code',
        'type',
        'borrower_name',
        'division',
        'borrower_phone',
        'borrower_signature_path',
        'created_by',
        'status',
        'notes',
        'transaction_at',
    ];

    protected $hidden = ['borrower_signature_path'];

    protected $casts = [
        'transaction_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryTransactionItem::class);
    }
}
