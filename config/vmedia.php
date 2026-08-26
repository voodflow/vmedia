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
    | Disk used for Spatie collections (images / videos / files).
    */
    'disk' => env('VMEDIA_DISK', 'public'),

    'upload' => [
        'image_max_kb' => (int) env('VMEDIA_IMAGE_MAX_KB', 12288),
        'video_max_kb' => (int) env('VMEDIA_VIDEO_MAX_KB', 51200),
        'file_max_kb' => (int) env('VMEDIA_FILE_MAX_KB', 20480),
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
        'allowed_file_mimes' => [
            'application/pdf',
            'application/zip',
            'application/x-zip-compressed',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/csv',
        ],
        'allowed_extensions' => [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif',
            'mp4', 'webm', 'ogg', 'mov', 'm4v',
            'pdf', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv',
        ],
    ],

    'conversions' => [
        'enabled' => env('VMEDIA_CONVERSIONS', true),
        'queued' => env('VMEDIA_CONVERSIONS_QUEUED', false),
        'thumb' => [
            'width' => (int) env('VMEDIA_THUMB_WIDTH', 400),
            'height' => (int) env('VMEDIA_THUMB_HEIGHT', 400),
            'format' => env('VMEDIA_THUMB_FORMAT', 'webp'),
        ],
    ],

    'duplicates' => [
        'detect' => env('VMEDIA_DETECT_DUPLICATES', true),
        /*
        | When true, store() reuses the existing vault row instead of uploading again.
        | Galleries from the new upload are still attached.
        */
        'reuse' => env('VMEDIA_REUSE_DUPLICATES', true),
    ],

    'usage' => [
        /*
        | Block delete when the media is attached to domain models, unless force is used.
        */
        'protect_delete' => env('VMEDIA_PROTECT_DELETE', true),
    ],

    'soft_deletes' => env('VMEDIA_SOFT_DELETES', true),

    'zip' => [
        'max_files' => (int) env('VMEDIA_ZIP_MAX_FILES', 100),
    ],

    'public' => [
        'enabled' => env('VMEDIA_PUBLIC_GALLERIES', true),
        'prefix' => env('VMEDIA_PUBLIC_PREFIX', 'galleries'),
        'middleware' => ['web'],
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
        'attachments' => 'vmedia_attachments',
    ],

    'browser' => [
        'per_page' => (int) env('VMEDIA_BROWSER_PER_PAGE', 48),
    ],
];
