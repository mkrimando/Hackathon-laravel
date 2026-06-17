<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BookingAttendee extends Model
{
    protected $fillable = [
        'booking_id',
        'first_name',
        'last_name',
        'email',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
