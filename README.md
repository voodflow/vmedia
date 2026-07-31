# voodflow/voodbuilder-media

Reusable **media library & galleries** for Filament 5, backed by [Spatie Media Library](https://github.com/spatie/laravel-medialibrary).

Works **standalone** (admin galleries + library). With [VoodBuilder](https://github.com/voodflow/voodbuilder) it also powers the editor Asset Manager (Choose / upload).

## Model

- **Vault** — singleton Spatie owner of every file on disk (one storage copy)
- **Galleries** — many-to-many membership (a photo/video can sit in several galleries)
- **Default gallery** — only target for editor/frontend uploads; Choose can browse any gallery

## Install

```bash
composer require voodflow/voodbuilder-media
php artisan voodbuilder-media:install
```

Register on the Filament panel:

```php
->plugins([
    \Voodflow\VoodbuilderMedia\VoodbuilderMediaPlugin::make(),
    // optional, next to Core:
    \Voodflow\Voodbuilder\VoodbuilderPlugin::make(),
])
```

When this companion is installed, Core’s built-in Media library resource and editor media list/upload routes are skipped.

## Admin UX

- **Photos vs videos** — type icon in the library table + filter (`images` / `videos` collections)
- **Galleries** — name, auto unique slug, description, default, public, sort
- **Library** — preview, multi-gallery badges; no per-row reassignment
- **Bulk** — assign/add galleries, replace memberships, or create a new gallery from the selection
- **Upload** — pick one or more galleries for membership (files always stored in the vault)

## Editor API

| Method | Path | Role |
|--------|------|------|
| `GET` | `/voodbuilder/editor/media/galleries` | Gallery list + counts |
| `GET` | `/voodbuilder/editor/media?page=&per_page=&gallery_id=&type=&q=` | Paginated assets |
| `POST` | `/voodbuilder/editor/upload` | Upload → **default gallery only** |

The editor **Choose** dialog is a custom media browser (not GrapesJS AM): gallery sidebar, photo/video filter, search, infinite scroll, lazy thumbnails, compact upload dropzone. Dark/light and mobile layouts use editor theme tokens.

## Config

| Key | Default | Role |
|-----|---------|------|
| `enabled` | `true` | Toggle package |
| `navigation.group` | `Media` | Filament nav group |
| `disk` | `public` | Spatie collection disk |
| `upload.image_max_kb` | `8192` | Max photo size |
| `upload.video_max_kb` | `51200` | Max video size |
| `voodbuilder.editor_routes` | `true` | Register editor routes |

```env
VOODBUILDER_MEDIA_ENABLED=true
VOODBUILDER_MEDIA_NAV_GROUP=Voodbuilder
VOODBUILDER_MEDIA_DISK=public
```

Scaffolded from [filamentphp/plugin-skeleton](https://github.com/filamentphp/plugin-skeleton) `5.x`.
