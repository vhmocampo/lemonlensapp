<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RepairDescription extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'slug',
        'description',
    ];

    /**
     * Find a repair description by its slug.
     *
     * @param string $slug
     * @return self|null
     */
    public static function findBySlug($slug)
    {
        return static::where('slug', $slug)->first();
    }

    /**
     * Create or update a repair description by slug.
     *
     * @param string $slug
     * @param string $description
     * @return self
     */
    public static function updateOrCreateBySlug($slug, $description)
    {
        return static::updateOrCreate(
            ['slug' => $slug],
            ['description' => $description]
        );
    }
}