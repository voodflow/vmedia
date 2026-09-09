# VoodMedia (`voodflow/vmedia`)

![VoodMedia — Filament Media Vault by VoodFlow](docs/images/promo.png)

**Media Vault** for Laravel + Filament: upload once, reuse everywhere. One vault storage copy, many gallery memberships, morph attachments for your domain models.

Works **standalone** in any Filament app. Optional integrations with VoodBuilder (Asset Manager), Voodflow (e.g. Approval Page heroes), and any third-party package that registers its own vault root.

Built on Filament 5 and [Spatie Media Library](https://github.com/spatie/laravel-medialibrary).

## Screenshots

### Media library

Central library: preview, galleries, **usage** counts, type, size, and upload time. Upload media or **Import ZIP** from the header.

![VoodMedia media library](docs/images/library.png)

### Galleries (folders & albums)

Organize vault media with a hierarchy: **Folder** = container (year, product, plugin…); **Album** = holds photos/files. Mark a **default gallery** and toggle **public** albums.

![VoodMedia galleries list](docs/images/galleries.png)

### Album editor

Edit album metadata (parent, slug, public/default), manage images (browse library, upload, ZIP import, drag reorder), and assign **album tags**.

![VoodMedia album edit](docs/images/album-edit.png)

### Picker (`VmediaPicker`)

Modal for forms and builders: upload into the selected folder/album, filter by parent/gallery, search, grid/list view, multi-select → **Use selection**.

![VoodMedia choose from media library](docs/images/picker.png)

## Requirements

| Requirement | Notes |
|-------------|--------|
| PHP | 8.4+ |
| Laravel | 12 or 13 |
| Filament | 5 |
| Spatie Media Library | 11 |
| PHP `gd` extension | **Must** be built with **JPEG** (and preferably **WebP** + FreeType). Spatie generates `thumb` conversions on upload; without JPEG support you get `imagecreatefromstring(): No JPEG support in this PHP build`. |

Verify in the app container:

```bash
php -r 'var_export(gd_info()["JPEG Support"] ?? false);'
# expect: true
```

Docker / `php:*-fpm` example:

```dockerfile
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install gd
```

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

## Plugin vault roots (for packages & host apps)

VoodMedia does **not** hardcode product folders. Each package (or the host app) **registers** a top-level folder + default **Library** album, then uses it for uploads/pickers.

### 1. Register + ensure on boot

In your package `ServiceProvider` (or `AppServiceProvider`):

```php
use Voodflow\Vmedia\Support\Integration\RegistersPluginVault;

public function boot(): void
{
    if (! class_exists(RegistersPluginVault::class)) {
        return;
    }

    // source = stable id (integration_source), slug = folder slug in the UI
    RegistersPluginVault::register(
        source: 'acme',
        slug: 'acme',
        name: 'Acme', // or fn (): string => __('acme::nav.media_root')
        // integrationKey: 'root:acme', // optional; default root:{slug}
    );
    RegistersPluginVault::ensureOnBoot($this->app, 'acme');
}
```

Equivalent low-level API:

```php
use Voodflow\Vmedia\Vmedia;

Vmedia::registerPluginVault('acme', 'acme', 'Acme');
Vmedia::ensurePluginVault('acme'); // or RegistersPluginVault::ensureOnBoot(...)
```

After boot you get a folder **Acme** with a child **Library** album (upload target when browsing that folder).

Sync all *registered* roots (after companions have booted):

```bash
php artisan vmedia:ensure-plugin-roots
```

### 2. Use the vault in code

```php
use Voodflow\Vmedia\Support\Integration\PluginVaultRootGroup;
use Voodflow\Vmedia\Support\Integration\PluginVaultLibraryGallery;
use Voodflow\Vmedia\Support\MediaLibrary;
use Voodflow\Vmedia\Filament\Forms\Components\VmediaPicker;

// Top-level folder (KIND_GROUP)
$root = PluginVaultRootGroup::for('acme');

// Default Library album under that folder (KIND_ALBUM) — prefer this for uploads
$library = PluginVaultLibraryGallery::album('acme');

MediaLibrary::store($uploadedFile, $library);

// Lock a Filament picker to your plugin library
VmediaPicker::make('hero')
    ->images()
    ->attachToRecord(false)
    ->vaultGallery(fn (): int => (int) PluginVaultLibraryGallery::album('acme')->getKey());
```

Optional: nest under a configured parent (settings / multi-tenant layout):

```php
PluginVaultRootGroup::for('acme', $optionalOuterRootId);
```

### 3. Conventions

| Concept | Rule |
| ------- | ---- |
| `source` | Stable string stored as `integration_source` (e.g. `acme`, `voodflow`) |
| `slug` | URL/path segment for the folder |
| Library album | Auto-created under the root; use it as default upload target |
| Isolation | Your package owns only its `source`; do not hardcode other products in VoodMedia |

Shared **Logos** folder is provided by VoodMedia itself (`PluginVaultRootGroup::logos()`).

## Model

- **Vault** — singleton Spatie owner of every file on disk (one storage copy)
- **Galleries** — many-to-many membership (a photo/video/file can sit in several galleries)
- **Folders vs albums** — folders nest structure; albums hold media
- **Default gallery** — fallback target for uploads when no gallery is selected; Choose can browse any gallery
- **Attachments** — `HasAttachedMedia` morph pivot (domain models never own Spatie collections)

## Features

| Area | What you get |
|------|----------------|
| Admin | Library + galleries hierarchy, metadata (alt, caption, credits; video poster on videos), soft delete / trash |
| Picker | `VmediaPicker` + `VmediaFileUpload` for other plugins |
| Files | Photos, videos, and documents (PDF/Office/ZIP…) |
| Thumbs | Spatie `thumb` conversion (WebP) for images |
| Usage | Attachment counts; delete protected when media is in use |
| Tags | Media tags + album tags; bulk assign |
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
| `POST` | `/vmedia/media/upload` | Upload; optional `gallery_id` (folder/album — folders resolve to library album) |
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
```

## Docs

- User manual: [docs/manual/user/index.md](docs/manual/user/index.md)
- Developer manual: [docs/manual/developer/index.md](docs/manual/developer/index.md)
- Product overview: [docs/sales/README.md](docs/sales/README.md)

## License

MIT — see [LICENSE](LICENSE).
