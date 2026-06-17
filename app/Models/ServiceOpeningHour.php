<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceOpeningHour extends Model
{
    protected $fillable = [
        'service_id',
        'day_of_week',
        'open_time',
        'close_time',
        'is_closed',
    ];

    protected $casts = [
        'is_closed' => 'boolean',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
