<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fine extends Model
{
    protected $fillable = [
        'car_name', 'plate_code', 'plate_number', 'dateandtime', 'location',
        'source', 'amount', 'fine_number', 'details', 'dispute'
    ];
}
