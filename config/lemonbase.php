<?php

/**
 * Lemonbase Configuration
 * Currently, we're using mongodb directly, but in the future, we might want to use an API client.
 */
return [
    'base_url' => env('LEMONBASE_URL', 'https://api.lemonbase.ai'),
    'api_key' => env('LEMONBASE_API_KEY'),
    'retries' => env('LEMONBASE_RETRIES', 3),
];
