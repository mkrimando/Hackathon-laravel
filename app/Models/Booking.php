<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'service_id',
        'slot_start',
    ];

    protected $dates = [
        'slot_start',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function attendees()
    {
        return $this->hasMany(BookingAttendee::class);
    }
}
