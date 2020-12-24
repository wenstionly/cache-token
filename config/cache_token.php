<?php

return [
    'store' => env('CACHE_TOKEN_STORE'),
    'token_map_prefix' => env('CACHE_TOKEN_MAP_PREFIX', 'T2U.'),
    'user_map_prefix' => env('CACHE_TOKEN_USER_PREFIX', 'U2T.'),
];
