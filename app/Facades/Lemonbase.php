<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class Lemonbase extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Clients\LemonbaseClient::class;
    }
}