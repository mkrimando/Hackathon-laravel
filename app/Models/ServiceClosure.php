<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ServiceClosure extends Model
{
    protected $fillable = [
        'service_id',
        'start_datetime',
        'end_datetime',
        'reason',
    ];

    protected $dates = [
        'start_datetime',
        'end_datetime',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
