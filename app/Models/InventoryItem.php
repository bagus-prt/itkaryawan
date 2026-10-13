<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $fillable = [
        'category_id',
        'code',
        'name',
        'unit',
        'stock_on_hand',
        'minimum_stock',
        'is_active',
        'description',
    ];

    protected $casts = [
        'stock_on_hand' => 'integer',
        'minimum_stock' => 'integer',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function transactionItems()
    {
        return $this->hasMany(InventoryTransactionItem::class);
    }
}
