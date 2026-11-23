<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    public const STOCK_STATUS_THRESHOLD = 10;

    protected $fillable = [
        'category_id',
        'code',
        'name',
        'image_path',
        'unit',
        'minimum_stock',
        'is_borrowable',
        'is_active',
        'description',
    ];

    protected $casts = [
        'stock_on_hand' => 'integer',
        'minimum_stock' => 'integer',
        'is_borrowable' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            InventoryCategory::class,
            'category_id'
        );
    }

    public function transactionItems(): HasMany
    {
        return $this->hasMany(InventoryTransactionItem::class);
    }
}
