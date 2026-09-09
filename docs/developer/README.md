# Vmedia — developer guide

Composer: `voodflow/vmedia` · Namespace: `Voodflow\Vmedia` · Plugin id: `vmedia` · License: **MIT**

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
| `MediaVault` | Singleton Spatie owner + `thumb` conversion |
| `MediaGallery` | Album / membership |
| `MediaItem` | Media subclass + pivot + soft deletes + metadata helpers |
| `MediaLibrary` | Shared list/store/delete helpers |
| `MediaUsage` | Attachment counts, orphans, stats |
| `HasAttachedMedia` | Morph attachments for domain models |
| `VmediaFileUpload` | Filament upload → vault + attach |
| `VmediaPicker` | Filament browse/select from vault |
| `ZipImporter` | Bulk ZIP → vault |
| `UploadGuard` | MIME + path safety |
| `VmediaRoutes` | Package HTTP API |

## Events

- `MediaStored`
- `MediaAttached` / `MediaDetached`
- `MediaDeleted` (soft or force)
- `MediaRestored`

## Commands

```bash
php artisan vmedia:stats
php artisan vmedia:prune-orphans [--days=30] [--force] [--hard]
```

## Public gallery

When `VMEDIA_PUBLIC_GALLERIES=true`:

- `GET /galleries/{slug}` → `PublicGalleryController` (only `is_public` galleries)

## Routes

Authenticated (`web`, `auth`, throttle, optional Gate ability):

- `GET vmedia/media/galleries`
- `GET vmedia/media`
- `POST vmedia/media/upload` — body: `file`, optional `gallery_id` (folder or album; folders → library album)
- `DELETE vmedia/media/{media}` (`?force=1`)

## Plugin vault roots

Any package or host app owns its top-level folder. Register then ensure:

```php
use Voodflow\Vmedia\Support\Integration\RegistersPluginVault;

RegistersPluginVault::register('myplugin', 'myplugin', 'My Plugin');
RegistersPluginVault::ensureOnBoot($this->app, 'myplugin');
```

Then resolve with `PluginVaultRootGroup::for('myplugin')` / `PluginVaultLibraryGallery::album('myplugin')`.

Full walkthrough: [README — Plugin vault roots](../../README.md#plugin-vault-roots-for-packages--host-apps).

## Extension points

- Policies for gallery/media CRUD
- Config disk, MIME lists, conversions, duplicates, public prefix
- Plugin vault registration (`Vmedia::registerPluginVault` / `RegistersPluginVault`)

## Do / don't

- **Do** keep uploads on the vault; resolve destination with `GalleryUploadTarget` (browse selection or default gallery)
- **Do** reject non-allow-listed MIME and traversal in filenames
- **Do** use `VmediaPicker` / `HasAttachedMedia` from sibling plugins
- **Don't** expose media routes without `auth`
- **Don't** delete the default gallery without promoting another
