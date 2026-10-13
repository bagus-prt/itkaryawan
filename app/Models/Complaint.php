<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Complaint extends Model
{
    protected $fillable = [
        'fuel_station_id',
        'reporter_name',
        'reporter_contact',
        'category',
        'incident_at',
        'description',
        'photo_path',
        'status',
        'handled_by',
        'resolution_notes',
        'handled_at',
    ];

    protected $casts = [
        'incident_at' => 'datetime',
        'handled_at' => 'datetime',
    ];

    public function fuelStation()
    {
        return $this->belongsTo(FuelStation::class);
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
