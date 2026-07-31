# voodflow/voodbuilder-media

Reusable **media library & galleries** for Filament 5, backed by [Spatie Media Library](https://github.com/spatie/laravel-medialibrary).

Works **standalone** (admin galleries + library). With [VoodBuilder](https://github.com/voodflow/voodbuilder) it also powers the editor Asset Manager (Choose / upload).

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

When this companion is installed, Core’s built-in Media library resource and editor media routes are skipped so there is a single source of truth.

## Features (base)

- **Galleries** — name, slug, description, default gallery, public flag, sort order
- **Media library** — flat table of all Spatie media across galleries (preview, filter, upload, delete)
- **Collections** — `images` / `videos` on each gallery
- **Migration** — moves legacy Core `voodbuilder_media_library` media onto the default gallery
- **Editor API** — `GET/POST /voodbuilder/editor/media` + `upload` when VoodBuilder is present

## Config

| Key | Default | Role |
|-----|---------|------|
| `enabled` | `true` | Toggle package |
| `navigation.group` | `Media` | Filament nav group |
| `disk` | `public` | Spatie collection disk |
| `upload.image_max_kb` | `8192` | Max image size |
| `upload.video_max_kb` | `51200` | Max video size |
| `voodbuilder.editor_routes` | `true` | Register editor list/upload routes |

```env
VOODBUILDER_MEDIA_ENABLED=true
VOODBUILDER_MEDIA_NAV_GROUP=Voodbuilder
VOODBUILDER_MEDIA_DISK=public
```

Scaffolded from [filamentphp/plugin-skeleton](https://github.com/filamentphp/plugin-skeleton) `5.x`.
