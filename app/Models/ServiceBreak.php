<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceBreak extends Model
{
    protected $fillable = [
        'service_id',
        'start_time',
        'end_time',
        'name',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
