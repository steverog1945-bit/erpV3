<?php

return [

    'enabled' => env('LANDING_PAGE_BUILDER_ENABLED', false),

    'fallback_to_existing' => true,

    'published_slug' => 'home',

    'preview_allowed_roles' => [],

    'media' => [
        'max_size_mb' => 10,
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        'max_dimensions' => 4096,
    ],

    'publish' => [
        'rate_limit' => 5,
        'require_approval' => false,
    ],

];