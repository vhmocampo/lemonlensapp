<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stat extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'category',
        'description',
        'last_updated',
    ];

    protected $casts = [
        'value' => 'integer',
        'last_updated' => 'datetime',
    ];

    /**
     * Get a statistic by key
     *
     * @param string $key
     * @return int|null
     */
    public static function getValue(string $key): ?int
    {
        $stat = self::where('key', $key)->first();
        return $stat ? $stat->value : null;
    }

    /**
     * Set or update a statistic
     *
     * @param string $key
     * @param int $value
     * @param string|null $category
     * @param string|null $description
     * @return Stat
     */
    public static function setValue(string $key, int $value, ?string $category = null, ?string $description = null): Stat
    {
        return self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'category' => $category,
                'description' => $description,
                'last_updated' => now(),
            ]
        );
    }
}