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

    // Feed polling. See App\Services\PollingSchedule for the tiers.
    'poll' => [
        // The queue that fetch jobs go on, so that slow feeds don't delay other jobs
        'queue' => env('FEED_QUEUE', 'feeds'),
        // The most sources that sources:poll queues each time it runs (once a minute)
        'max_per_run' => env('POLL_MAX_PER_RUN', 120),
        // The most sources on one hostname that it queues each time it runs
        'max_per_host' => env('POLL_MAX_PER_HOST', 10),
    ],

    // Camo-compatible image proxy
    'img_proxy' => [
        'url' => env('IMG_PROXY_URL'),
        'key' => env('IMG_PROXY_KEY'),
    ],

];
