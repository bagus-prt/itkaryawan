<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryTransactionItem extends Model
{
    protected $fillable = [
        'inventory_transaction_id',
        'inventory_item_id',
        'related_loan_item_id',
        'quantity',
        'condition_notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(
            InventoryTransaction::class,
            'inventory_transaction_id'
        );
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function relatedLoanItem(): BelongsTo
    {
        return $this->belongsTo(
            InventoryTransactionItem::class,
            'related_loan_item_id'
        );
    }

    public function returns(): HasMany
    {
        return $this->hasMany(self::class, 'related_loan_item_id');
    }
}
