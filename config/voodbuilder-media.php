<?php

declare(strict_types=1);

return [
    'enabled' => env('VOODBUILDER_MEDIA_ENABLED', true),

    'navigation' => [
        'group' => env('VOODBUILDER_MEDIA_NAV_GROUP', 'Media'),
        'sort' => (int) env('VOODBUILDER_MEDIA_NAV_SORT', 40),
    ],

    /*
    | Disk used for Spatie collections (images / videos).
    */
    'disk' => env('VOODBUILDER_MEDIA_DISK', 'public'),

    'upload' => [
        'image_max_kb' => (int) env('VOODBUILDER_MEDIA_IMAGE_MAX_KB', 8192),
        'video_max_kb' => (int) env('VOODBUILDER_MEDIA_VIDEO_MAX_KB', 51200),
    ],

    /*
    | When true and voodflow/voodbuilder is installed, register editor media routes
    | that feed the GrapesJS Asset Manager (Choose / upload).
    */
    'voodbuilder' => [
        'editor_routes' => env('VOODBUILDER_MEDIA_EDITOR_ROUTES', true),
    ],

    'tables' => [
        'galleries' => 'voodbuilder_media_galleries',
        'vaults' => 'voodbuilder_media_vaults',
        'gallery_media' => 'voodbuilder_media_gallery_media',
    ],
];
