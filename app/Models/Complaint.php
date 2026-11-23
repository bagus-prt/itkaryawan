<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    protected $fillable = [
        'fuel_station_id',
        'station_code',
        'station_address',
        'complaint_date',
        'reporter_name',
        'reporter_contact',
        'category',
        'incident_at',
        'description',
        'photo_path',
        'status',
        'created_by',
        'handled_by',
        'resolution_notes',
        'handled_at',
    ];

    protected $casts = [
        'complaint_date' => 'date',
        'incident_at' => 'datetime',
        'handled_at' => 'datetime',
    ];

    public function fuelStation(): BelongsTo
    {
        return $this->belongsTo(FuelStation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
