<?php

return [
    'library' => [
        'navigation' => 'Media library',
        'model' => 'Media item',
        'plural' => 'Media library',
        'intro' => 'Upload once, reuse everywhere. Organize files into galleries; works with VoodBuilder editor Choose dialog.',
        'preview' => 'Preview',
        'name' => 'Name',
        'gallery' => 'Gallery',
        'type' => 'Type',
        'images' => 'Images',
        'videos' => 'Videos',
        'size' => 'Size',
        'uploaded_at' => 'Uploaded',
        'open' => 'Open',
        'upload' => 'Upload media',
        'files' => 'Files',
        'upload_help' => 'Images and videos. Stored with Spatie Media Library.',
        'uploaded' => 'Media uploaded',
        'deleted' => 'Media deleted',
        'empty_heading' => 'No media yet',
        'empty_body' => 'Upload images or videos to start building your library.',
    ],
    'galleries' => [
        'navigation' => 'Galleries',
        'model' => 'Gallery',
        'plural' => 'Galleries',
        'browse_media' => 'Browse media',
        'fields' => [
            'name' => 'Name',
            'slug' => 'Slug',
            'description' => 'Description',
            'is_default' => 'Default gallery',
            'is_public' => 'Public',
            'sort_order' => 'Sort order',
            'media_count' => 'Items',
        ],
        'helpers' => [
            'is_default' => 'Editor uploads and the shared library default go here. Only one gallery can be default.',
        ],
    ],
];
