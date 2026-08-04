<?php

declare(strict_types=1);

return [
    'enabled' => env('VMEDIA_ENABLED', true),

    /*
    | Own Filament navigation group (independent from page-builder settings).
    */
    'navigation' => [
        'group' => env('VMEDIA_NAV_GROUP', 'Media'),
        'sort' => (int) env('VMEDIA_NAV_SORT', 40),
    ],

    /*
    | Disk used for Spatie collections (images / videos).
    */
    'disk' => env('VMEDIA_DISK', 'public'),

    'upload' => [
        'image_max_kb' => (int) env('VMEDIA_IMAGE_MAX_KB', 12288),
        'video_max_kb' => (int) env('VMEDIA_VIDEO_MAX_KB', 51200),
        'allowed_image_mimes' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'image/avif',
        ],
        'allowed_video_mimes' => [
            'video/mp4',
            'video/webm',
            'video/ogg',
            'video/quicktime',
            'video/x-m4v',
        ],
        'allowed_extensions' => [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif',
            'mp4', 'webm', 'ogg', 'mov', 'm4v',
        ],
    ],

    /*
    | HTTP routes owned by this package (always registered when active).
    | Prefix/name are package-owned and do not require a page builder.
    */
    'routes' => [
        'prefix' => env('VMEDIA_ROUTE_PREFIX', 'vmedia'),
        'name_prefix' => env('VMEDIA_ROUTE_NAME', 'vmedia.'),
        'middleware' => ['web', 'auth', 'throttle:60,1'],
    ],

    /*
    | Optional compatibility aliases for page-builder editor Asset Manager URLs.
    | Host apps can turn these off once the builder points at vmedia.* routes.
    */
    'integrations' => [
        'voodbuilder' => [
            'editor_routes' => env('VMEDIA_VOODBUILDER_EDITOR_ROUTES', true),
            'prefix' => 'voodbuilder/editor',
            'name_prefix' => 'voodbuilder.editor.',
        ],
    ],

    /*
    | Optional Gate ability checked on HTTP media routes (in addition to auth).
    | null = any authenticated user. Filament panel access is separate.
    */
    'authorization' => [
        'ability' => env('VMEDIA_ABILITY'),
    ],

    /*
    | Activate without registering VmediaPlugin on a Filament panel.
    | Prefer the Filament plugin in host apps.
    */
    'auto_register' => env('VMEDIA_AUTO_REGISTER', false),

    /*
    | Table names kept for backward compatibility with existing installs.
    */
    'tables' => [
        'galleries' => 'voodbuilder_media_galleries',
        'vaults' => 'voodbuilder_media_vaults',
        'gallery_media' => 'voodbuilder_media_gallery_media',
    ],

    'browser' => [
        'per_page' => (int) env('VMEDIA_BROWSER_PER_PAGE', 48),
    ],
];
