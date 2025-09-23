<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_custom',
    ];

    protected $casts = [
        'is_custom' => 'boolean',
    ];

    /**
     * Get the customers for this source.
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
