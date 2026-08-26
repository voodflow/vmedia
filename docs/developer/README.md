# Vmedia — developer guide

Composer: `voodflow/vmedia` · Namespace: `Voodflow\Vmedia` · Plugin id: `vmedia`

Operator detail: [manual/developer/index.md](../manual/developer/index.md).

## Integration

```bash
composer require voodflow/vmedia
php artisan vmedia:install
```

```php
$panel->plugins([\Voodflow\Vmedia\VmediaPlugin::make()]);
```

Prefer the plugin over `VMEDIA_AUTO_REGISTER` so commenting the plugin disables cleanly.

## Architecture

| Piece | Role |
|-------|------|
| `MediaVault` | Singleton Spatie owner |
| `MediaGallery` | Album / membership |
| `MediaItem` | Media subclass + pivot |
| `MediaLibrary` | Shared list/store helpers |
| `HasAttachedMedia` | Morph attachments for domain models (no Spatie on those models) |
| `VmediaFileUpload` | Filament upload → vault + attach |
| `UploadGuard` | MIME + path safety |
| `VmediaRoutes` | Package HTTP API |

## Routes

Authenticated (`web`, `auth`, throttle, optional Gate ability):

- `GET vmedia/media/galleries`
- `GET vmedia/media`
- `POST vmedia/media/upload`
- `DELETE vmedia/media/{media}`

Optional builder aliases when `VMEDIA_VOODBUILDER_EDITOR_ROUTES=true`.

## Extension points

- Policies for gallery/media CRUD
- Config disk, MIME lists, nav group, ability name
- Soft page-builder bridge via route aliases + morph aliases

## Companions

Page builder / Voodbuilder Asset Manager (optional). Do not nest Media under builder settings UI.

## Do / don't

- **Do** keep uploads on the vault + default gallery path
- **Do** reject non-allow-listed MIME and traversal in filenames
- **Don't** expose media routes without `auth`
- **Don't** delete the default gallery without promoting another
