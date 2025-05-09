<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = ['status', 'params', 'result'];

    protected $casts = [
        'params' => 'array',
        'result' => 'array',
    ];
}
