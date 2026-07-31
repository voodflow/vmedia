<?php

return [
    'library' => [
        'navigation' => 'Libreria media',
        'model' => 'Media',
        'plural' => 'Libreria media',
        'intro' => 'Carica una volta, riusa ovunque. Organizza i file in gallerie; integra il dialogo Choose di VoodBuilder.',
        'preview' => 'Anteprima',
        'name' => 'Nome',
        'gallery' => 'Galleria',
        'type' => 'Tipo',
        'images' => 'Immagini',
        'videos' => 'Video',
        'size' => 'Dimensione',
        'uploaded_at' => 'Caricato',
        'open' => 'Apri',
        'upload' => 'Carica media',
        'files' => 'File',
        'upload_help' => 'Immagini e video. Memorizzati con Spatie Media Library.',
        'uploaded' => 'Media caricati',
        'deleted' => 'Media eliminato',
        'empty_heading' => 'Nessun media',
        'empty_body' => 'Carica immagini o video per iniziare la libreria.',
    ],
    'galleries' => [
        'navigation' => 'Gallerie',
        'model' => 'Galleria',
        'plural' => 'Gallerie',
        'browse_media' => 'Sfoglia media',
        'fields' => [
            'name' => 'Nome',
            'slug' => 'Slug',
            'description' => 'Descrizione',
            'is_default' => 'Galleria predefinita',
            'is_public' => 'Pubblica',
            'sort_order' => 'Ordine',
            'media_count' => 'Elementi',
        ],
        'helpers' => [
            'is_default' => 'Gli upload dell’editor e la libreria condivisa finiscono qui. Solo una galleria può essere predefinita.',
        ],
    ],
];
