<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function transaction()
    {
        return $this->belongsTo(
            InventoryTransaction::class,
            'inventory_transaction_id'
        );
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function relatedLoanItem()
    {
        return $this->belongsTo(
            InventoryTransactionItem::class,
            'related_loan_item_id'
        );
    }
}
