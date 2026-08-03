# voodflow/vmedia

Reusable **media library & galleries** for Filament 5, backed by [Spatie Media Library](https://github.com/spatie/laravel-medialibrary).

Works **standalone** (admin galleries + library + HTTP API). Optionally integrates with a page builder Asset Manager (Choose / upload).

## Model

- **Vault** — singleton Spatie owner of every file on disk (one storage copy)
- **Galleries** — many-to-many membership (a photo/video can sit in several galleries)
- **Default gallery** — only target for editor/frontend uploads; Choose can browse any gallery

## Install

```bash
composer require voodflow/vmedia
php artisan vmedia:install
```

Register on the Filament panel (own navigation group — not under page-builder settings):

```php
->plugins([
    \Voodflow\Vmedia\VmediaPlugin::make(),
])
```

## Admin UX

- **Photos vs videos** — type icon in the library table + filter (`images` / `videos` collections)
- **Galleries** — name, auto unique slug, description, default, public, sort
- **Library** — preview, multi-gallery badges; no per-row reassignment
- **Bulk** — assign/add galleries, replace memberships, or create a new gallery from the selection
- **Upload** — pick one or more galleries for membership (files always stored in the vault)

Filament navigation uses the **Media** group by default (`VMEDIA_NAV_GROUP`). Settings and resources are independent of any page-builder settings pages.

## HTTP API (package-owned)

| Method | Path | Role |
|--------|------|------|
| `GET` | `/vmedia/media/galleries` | Gallery list + counts |
| `GET` | `/vmedia/media?page=&per_page=&gallery_id=&type=&q=` | Paginated assets |
| `POST` | `/vmedia/media/upload` | Upload → **default gallery only** |
| `DELETE` | `/vmedia/media/{media}` | Delete vault media |

All routes require authentication (and optional Gate ability `VMEDIA_ABILITY`). Policies authorize gallery/media CRUD.

### Page-builder compatibility aliases

When `VMEDIA_VOODBUILDER_EDITOR_ROUTES=true` (default), the same handlers are also registered at:

| Method | Path | Route name |
|--------|------|------------|
| `GET` | `/voodbuilder/editor/media/galleries` | `voodbuilder.editor.media.galleries` |
| `GET` | `/voodbuilder/editor/media` | `voodbuilder.editor.media.index` |
| `POST` | `/voodbuilder/editor/upload` | `voodbuilder.editor.upload` |

Turn aliases off once the host builder points at `vmedia.*` routes.

## Config

| Key | Default | Role |
|-----|---------|------|
| `enabled` | `true` | Toggle package |
| `navigation.group` | `Media` | Filament nav group (own category) |
| `disk` | `public` | Spatie collection disk |
| `upload.image_max_kb` | `8192` | Max photo size |
| `upload.video_max_kb` | `51200` | Max video size |
| `routes.prefix` | `vmedia` | Package HTTP prefix |
| `integrations.voodbuilder.editor_routes` | `true` | Compat aliases |
| `authorization.ability` | `null` | Optional Gate ability |
| `auto_register` | `false` | Activate without Filament plugin |

```env
VMEDIA_ENABLED=true
VMEDIA_NAV_GROUP=Media
VMEDIA_DISK=public
VMEDIA_ABILITY=
VMEDIA_VOODBUILDER_EDITOR_ROUTES=true
```

## Docs

- User manual: [docs/manual/user/index.md](docs/manual/user/index.md)
- Developer manual: [docs/manual/developer/index.md](docs/manual/developer/index.md)

## License

Paid commercial / source-available — see [LICENSE](LICENSE).
