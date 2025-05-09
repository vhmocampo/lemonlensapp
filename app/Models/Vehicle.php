<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Vehicle extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'vehicles';
    protected $primaryKey = '_id';

    protected $fillable = [
        'make',
        'model',
        'year',
    ];
}