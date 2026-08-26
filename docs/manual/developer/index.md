# Vmedia developer manual

## Requirements

- PHP 8.4+
- Laravel 12 or 13
- Filament 5
- Spatie Media Library 11

## Install

```bash
composer require voodflow/vmedia
php artisan vmedia:install
```

Register the plugin on a Filament panel:

```php
->plugins([
    \Voodflow\Vmedia\VmediaPlugin::make(),
])
```

Alternatively set `VMEDIA_AUTO_REGISTER=true` (prefer the plugin so commenting it out disables routes/resources cleanly).

## Architecture

| Piece | Role |
|-------|------|
| `MediaVault` | Singleton Spatie `HasMedia` owner |
| `MediaGallery` | Album / membership |
| `MediaItem` | Spatie `Media` subclass + gallery pivot |
| `HasAttachedMedia` | Trait for companions: logical collections on `vmedia_attachments` |
| `VmediaFileUpload` | Filament field that stores in the vault and attaches |
| `MediaLibrary` | Shared list/store helpers |
| `UploadGuard` | MIME allow-list + path-traversal checks |
| Policies | Authz for gallery/media CRUD |

Table names default to legacy `voodbuilder_media_*` for backward compatibility. Morph aliases `voodbuilder_media_vault` / `voodbuilder_media_gallery` are preserved; `vmedia_*` aliases are also registered.

## Routes

Package-owned (always when active):

- `GET vmedia/media/galleries` → `vmedia.media.galleries`
- `GET vmedia/media` → `vmedia.media.index`
- `POST vmedia/media/upload` → `vmedia.media.upload`
- `DELETE vmedia/media/{media}` → `vmedia.media.destroy`

Middleware: `web`, `auth`, `throttle:60,1`, plus optional Gate ability (`VMEDIA_ABILITY`) and Eloquent policies.

Optional page-builder aliases (same controllers): see README.

## Security notes

- Unauthenticated requests receive 401/redirect.
- Policies require an authenticated user; default gallery cannot be deleted.
- Delete/open only apply to vault-owned image/video collections.
- Uploads reject path traversal in client filenames and non-allow-listed MIME types.
- Relative storage paths used for public URLs are sanitized (`UploadGuard::assertSafeRelativePath`).

## Testing

```bash
composer install
vendor/bin/phpunit
```

See `tests/Feature/MediaSecurityTest.php` for authz, MIME, and path-traversal coverage.

## Host wiring checklist

1. Point Composer path repo at `packages/voodflow/vmedia` (`voodflow/vmedia`).
2. Register `VmediaPlugin` on the admin panel.
3. Update page-builder code that referenced `Voodflow\VoodbuilderMedia\*` → `Voodflow\Vmedia\*` / `Vmedia::isActive()`.
4. Prefer `vmedia.*` route names; keep compat aliases until the builder is updated.
5. Domain models that need uploads should `use HasAttachedMedia` and `VmediaFileUpload` — do not put Spatie `HasMedia` on those models.
