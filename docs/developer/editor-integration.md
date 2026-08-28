# Editor & Filament integration

How **vmedia** connects to the VoodBuilder canvas, Filament markdown/rich editors, and the HTTP upload API.

## Browse vs upload

| Action | Behaviour |
|--------|-----------|
| **Browse** | Any folder or album in the vault (sidebar / filters). |
| **Upload** | Follows the **currently selected** folder or album. Selecting a **folder** (group) uploads into its `Library` album (created automatically if missing). With no selection (“All media”), uploads use the **default gallery**. |

The API exposes the resolved destination on list endpoints as `upload_gallery_id` and `upload_gallery` (`id`, `name`, `path`, `kind`).

## HTTP API

### List galleries

`GET /vmedia/media/galleries?gallery_id={optional}`

Returns `data` (flat list, depth-first with `label`, `breadcrumb`, `depth`, `parent_name`), `tree`, `default_gallery_id`, `upload_gallery_id`, `upload_gallery`.

Each gallery row includes a disambiguated `label` (e.g. `Builder › Library` for nested albums that share names). The page-builder sidebar indents by `depth` and lists children directly under their parent folder.

Pass `gallery_id` to preview where an upload would land for that browse context.

### Paginate media

`GET /vmedia/media?gallery_id=&include_descendants=1&q=&type=&page=&per_page=`

When `gallery_id` points to a **folder**, set `include_descendants=1` to show media from child albums.

Response includes the same `upload_gallery_*` fields as galleries.

### Upload

`POST /vmedia/media/upload`

| Field | Required | Description |
|-------|----------|-------------|
| `file` | yes | Image, video, or document (config limits). |
| `gallery_id` | no | Folder or album id. Folders resolve to their library album via `GalleryUploadTarget`. Omit → default gallery. |
| `name`, `caption`, `alt` | no | Optional metadata. |

Example (page builder browser):

```http
POST /vmedia/media/upload
Content-Type: multipart/form-data

file=@photo.jpg
gallery_id=42
```

Builder JS sends `gallery_id` from the sidebar selection; Filament pickers resolve from folder/album filter fields.

## PHP: `GalleryUploadTarget`

```php
use Voodflow\Vmedia\Support\GalleryUploadTarget;

$album = GalleryUploadTarget::resolve($galleryId); // group → library album
$album = GalleryUploadTarget::resolveFromBrowse($parentFolderId, $galleryId, $fallbackGalleryId);
$payload = GalleryUploadTarget::payload($parentFolderId, $galleryId, $fallbackGalleryId);
```

Used by `MediaController`, `VmediaFilamentBrowser`, and `VmediaPicker`.

## Filament components

| Component | Use |
|-----------|-----|
| `VmediaPicker` | Entity attachments; optional locked vault gallery. Browse-all when unlocked; upload follows filters. |
| `VmediaFilamentBrowser` | Shared modal for markdown/rich editors (`VmediaMarkdownEditor`, `VmediaRichContentPlugin`). |
| `VmediaMarkdownEditor` | Extra toolbar button → vmedia modal → `![](url)`. Native `attachFiles` upload stays when enabled in the toolbar. |
| `VmediaRichEditor` + plugin | TipTap attach from library. |

Register plugin vault library as browse default:

```php
VmediaFilamentBrowser::resolveVaultGalleryId('voodbuilder'); // Builder/Library album id
```

## VoodBuilder editor

Requires `mediaLibraryUrl` + `mediaGalleriesUrl` in editor bootstrap (`EditorGate`).

JS entry: `resources/js/editor/media-browser.js` (`openMediaBrowser`).

- Sidebar gallery selection sets upload destination (subtitle + drop zone hint).
- Galleries are ordered depth-first; nested albums show `Parent › Album` labels (see `GalleryDisplay`).
- Upload `FormData` includes `gallery_id` from selection.
- Folder browse uses `include_descendants=1` on the index API.

See [companion-integration.md](../../../voodbuilder/docs/developer/companion-integration.md) in voodbuilder.

## Plugin vault folders

Each companion can register a root **folder** + `Library` album (`PluginVaultRootGroup`, `PluginVaultLibraryGallery`). Selecting **Builder** in the editor uploads to `Builder/Library`, not the site-wide default.

Ensure roots exist:

```bash
php artisan vmedia:ensure-plugin-roots
```

## User-facing copy

Lang keys: `vmedia::admin.picker.upload_destination_help`, `vmedia::admin.editor.upload_*`. Operator guide: [manual/user/index.md](../manual/user/index.md).
