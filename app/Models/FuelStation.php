<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelStation extends Model
{
    protected $fillable = [
        'code',
        'name',
        'address',
        'city',
        'public_qr_token',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }
}
