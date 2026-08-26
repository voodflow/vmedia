# voodflow/vmedia

Reusable **media library & galleries** for Filament 5, backed by [Spatie Media Library](https://github.com/spatie/laravel-medialibrary).

**Free MIT pilot** for the Voodflow plugin family: one vault storage copy, many gallery memberships, morph attachments for your domain models.

Works **standalone** (admin + HTTP API + public galleries). Optionally integrates with a page-builder Asset Manager.

## Quick start (5 minutes)

```bash
composer require voodflow/vmedia
php artisan vmedia:install
```

Register on the Filament panel:

```php
->plugins([
    \Voodflow\Vmedia\VmediaPlugin::make(),
])
```

Attach media to a model:

```php
use Voodflow\Vmedia\Concerns\HasAttachedMedia;

class Booth extends Model
{
    use HasAttachedMedia;
}
```

Upload in a Filament form:

```php
use Voodflow\Vmedia\Filament\Forms\Components\VmediaFileUpload;

VmediaFileUpload::make('logo')
    ->collection('logo')
    ->image();
```

Pick existing vault media:

```php
use Voodflow\Vmedia\Filament\Forms\Components\VmediaPicker;

VmediaPicker::make('gallery')
    ->collection('gallery')
    ->images()
    ->multiple();
```

## Model

- **Vault** — singleton Spatie owner of every file on disk (one storage copy)
- **Galleries** — many-to-many membership (a photo/video/file can sit in several galleries)
- **Default gallery** — only target for editor/frontend uploads; Choose can browse any gallery
- **Attachments** — `HasAttachedMedia` morph pivot (domain models never own Spatie collections)

## Features

| Area | What you get |
|------|----------------|
| Admin | Library + galleries, metadata (alt, caption, credits; video poster on videos), soft delete / trash |
| Picker | `VmediaPicker` + `VmediaFileUpload` for other plugins |
| Files | Photos, videos, and documents (PDF/Office/ZIP…) |
| Thumbs | Spatie `thumb` conversion (WebP) for images |
| Usage | Attachment counts; delete protected when media is in use |
| Duplicates | SHA-256 reuse (optional) |
| ZIP import | Bulk extract into the vault |
| Events | `MediaStored`, `MediaAttached`, `MediaDetached`, `MediaDeleted`, `MediaRestored` |
| Stats | Dashboard widget + `php artisan vmedia:stats` |
| Orphans | `php artisan vmedia:prune-orphans --force` |
| Public | `GET /galleries/{slug}` Blade gallery for `is_public` galleries |

## HTTP API (package-owned)

| Method | Path | Role |
|--------|------|------|
| `GET` | `/vmedia/media/galleries` | Gallery list + counts |
| `GET` | `/vmedia/media?page=&per_page=&gallery_id=&type=&q=` | Paginated assets (`type`: image\|video\|file) |
| `POST` | `/vmedia/media/upload` | Upload → **default gallery only** |
| `DELETE` | `/vmedia/media/{media}` | Soft-delete (`?force=1` force-deletes) |

All routes require authentication (and optional Gate ability `VMEDIA_ABILITY`).

## Config highlights

```env
VMEDIA_ENABLED=true
VMEDIA_DISK=public
VMEDIA_CONVERSIONS=true
VMEDIA_DETECT_DUPLICATES=true
VMEDIA_PROTECT_DELETE=true
VMEDIA_PUBLIC_GALLERIES=true
VMEDIA_VOODBUILDER_EDITOR_ROUTES=true
```

## Docs

- User manual: [docs/manual/user/index.md](docs/manual/user/index.md)
- Developer manual: [docs/manual/developer/index.md](docs/manual/developer/index.md)
- Product overview: [docs/sales/README.md](docs/sales/README.md)

## License

MIT — see [LICENSE](LICENSE).
