<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class InventoryTransaction extends Model
{
    protected $fillable = [
        'transaction_code',
        'type',
        'borrower_name',
        'division',
        'created_by',
        'status',
        'notes',
        'transaction_at',
    ];

    protected $casts = [
        'transaction_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(InventoryTransactionItem::class);
    }
}
