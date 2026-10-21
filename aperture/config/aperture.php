<?php

return [

    // User agent sent when fetching feeds and discovering endpoints
    'user_agent' => env('USER_AGENT'),

    // Hostname that serves files from storage/app/media
    'media_url' => env('MEDIA_URL'),

    // Allow anyone to sign in, instead of only users created with create:user
    'public_access' => env('PUBLIC_ACCESS', false),

    'email_signup' => env('EMAIL_SIGNUP'),

    // Token required by the /stats endpoints
    'munin_token' => env('MUNIN_TOKEN'),

    // Watchtower handles subscribing to feeds and delivers content via WebSub
    'watchtower' => [
        'url' => env('WATCHTOWER_URL'),
        'callback' => env('WATCHTOWER_CB'),
        'token' => env('WATCHTOWER_TOKEN'),
    ],

    // Camo-compatible image proxy
    'img_proxy' => [
        'url' => env('IMG_PROXY_URL'),
        'key' => env('IMG_PROXY_KEY'),
    ],

];
