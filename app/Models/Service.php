<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'name',
        'duration_minutes',
        'slot_interval_minutes',
        'break_between_minutes',
        'max_clients_per_slot',
        'max_booking_days',
    ];

    public function openingHours()
    {
        return $this->hasMany(ServiceOpeningHour::class);
    }

    public function breaks()
    {
        return $this->hasMany(ServiceBreak::class);
    }

    public function closures()
    {
        return $this->hasMany(ServiceClosure::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
