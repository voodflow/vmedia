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
    | When true and the companion is activated, register editor media routes
    | (galleries browser + vault upload) that replace Core GrapesJS Asset Manager list/upload.
    */
    'voodbuilder' => [
        'editor_routes' => env('VOODBUILDER_MEDIA_EDITOR_ROUTES', true),
    ],

    /*
    | Activate without registering VoodbuilderMediaPlugin on a Filament panel.
    | Prefer the Filament plugin in host apps so commenting it out restores Core media UX.
    */
    'auto_register' => env('VOODBUILDER_MEDIA_AUTO_REGISTER', false),

    'tables' => [
        'galleries' => 'voodbuilder_media_galleries',
        'vaults' => 'voodbuilder_media_vaults',
        'gallery_media' => 'voodbuilder_media_gallery_media',
    ],

    'browser' => [
        'per_page' => (int) env('VOODBUILDER_MEDIA_BROWSER_PER_PAGE', 48),
    ],
];
