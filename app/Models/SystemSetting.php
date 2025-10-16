<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'value' => 'array',
    ];

    /**
     * Get a system setting by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        
        return $setting ? $setting->value : $default;
    }

    /**
     * Set a system setting by key.
     */
    public static function set(string $key, mixed $value, ?string $description = null): self
    {
        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'description' => $description,
            ]
        );
    }

    /**
     * Get all fee types.
     */
    public static function getFeeTypes(): array
    {
        return static::get('fee_types', []);
    }

    /**
     * Set fee types.
     */
    public static function setFeeTypes(array $feeTypes): self
    {
        return static::set(
            'fee_types',
            $feeTypes,
            'System-wide fee types for additional contract charges'
        );
    }
}

