## [0.3.19] - 2026-10-07

### Fixed

- Caption colour/opacity no longer triggers “Compiling styles…” (that remount wiped the red paint back to black); bake rgba into Grapes attrs instead
- Drop stale `caption_bg` from saved config (was stuck on default black while `caption_bg_color` was `vp-brand-1`)
- Editor canvas forces author column count (iframe phone-width media query no longer collapses the grid)
- Re-bake caption colours after dynamic refresh / CSS compile / boot

## [0.3.18] - 2026-10-07

### Fixed

- Caption `vp-*` swatches resolve the canvas theme colour (not editor-chrome indigo defaults)
- After gallery remount, caption backgrounds are re-baked to concrete `rgba(...)` so theme tokens no longer flash/stick black in the editor

## [0.3.17] - 2026-10-07

### Fixed

- Caption background colour/opacity: live-paint while dragging, then one dynamic-block refresh on commit so Grapes HTML stores the real `--vmedia-caption-bg` (silent paint alone left black captions after Save while config kept the custom colour)

## [0.3.16] - 2026-10-07

### Fixed

- Gallery block settings survive editor save/reload: plugin encodes `data-voodbuilder-config` like VoodBuilder (entity-escaped JSON); Blade no longer stamps `data-voodbuilder-block` alone so the adapter attaches block+config together
- Caption background colour list includes theme `vp-*` tokens (CSS variables / color-mix)

## [0.3.15] - 2026-10-07

### Fixed

- Gallery caption opacity uses the same Decorations gradient range chrome; live CSS paint while dragging (no dynamic-block refresh / page CSS rebuild on every move)

## [0.3.14] - 2026-10-07

### Added

- `PATCH/POST vmedia/media/meta` updates vault `caption` / `alt` / `credits` for a MediaItem (editor gallery IMAGE panel + Filament)
- Gallery slide images expose `data-vb-media-caption|alt|credits` for the editor panel

### Changed

- Filament caption help: vault caption is shared by gallery blocks (one per file)

## [0.3.13] - 2026-10-07

### Fixed

- Stronger credits / caption contrast (opaque strip, text-shadow, darker default caption bg)
- Gallery settings hint: vault captions + gallery position override per-image Grapes caption display

## [0.3.12] - 2026-10-07

### Fixed

- Grid credits open as a slim top strip on the image (no large floating tooltip over the photo / next row)
- Lightbox uses more of the desktop viewport; on mobile nav overlays the image and sizing follows `dvh`

## [0.3.11] - 2026-10-07

### Changed

- Caption background uses the Tailwind colour select (with swatches) plus an opacity slider — no raw CSS field

## [0.3.10] - 2026-10-07

### Fixed

- Lightbox no longer flashes the previous photo when opening another image (clear on close; reveal only after the new frame loads)

## [0.3.9] - 2026-10-07

### Added

- Caption position **on image** (overlay) plus configurable caption background colour (CSS colour)
- Gallery slide images expose `data-vb-media-uuid` so in-canvas “Save” replaces the vault file instead of uploading a duplicate into the album

### Fixed

- Column count no longer collapses to 2 columns after save / before Tailwind JIT (uses `--vmedia-columns`)
- Credits control is a plain **i** button (no native `<details>` disclosure triangle)
- Credits popover opens over the image (top-right), not into the row below
- Lightbox credits no longer insert a gap between image and caption bar

### Changed

- Vault caption / credits remain the source of truth for gallery blocks (same media → same meta in every gallery). Grapes “IMAGE” caption trait is not wired to the vault.

## [0.3.8] - 2026-10-07

### Added

- Gallery block setting: caption above or below the image (grid / masonry / featured)
- Credits “i” control on grid items and in the lightbox when media has credits

### Fixed

- Lightbox closes when clicking the dimmed area outside the image (not only the ×)

## [0.3.7] - 2026-10-07

### Fixed

- Newly dropped gallery blocks keep grid/masonry/rounded layout in the editor canvas (inject layout CSS into the Grapes iframe + force page Tailwind rebuild; first drop used to wait until columns were toggled)

## [0.3.6] - 2026-10-07

### Fixed

- Lightbox caption bar shrink-wraps to the image width (portrait images no longer get a landscape-wide caption)

## [0.3.5] - 2026-10-07

### Fixed

- Lightbox slide payload uses a hidden `<div>` instead of `<script type="application/json">` (DOM hydrate strips script nodes, so the modal never opened)
- Avoid matching the layout script tag as a gallery root (`data-vmedia-lightbox-src`)

## [0.3.4] - 2026-10-07

### Fixed

- Gallery gap / rounded / aspect use Tailwind utility classes so Grapes canvas keeps them (`<style>` tags are stripped in the editor)
- Lightbox JS loads from layout assets (`vendor/vmedia/gallery-lightbox.js`) instead of `@push` inside block HTML (never reached the public page after dynamic hydrate)

## [0.3.3] - 2026-10-07

### Added

- Four VoodBuilder gallery blocks (simple grid, masonry, featured hero, scrolling marquee) with right-sidebar settings: gallery pick, columns, gap, aspect, limit, lightbox, captions, marquee speed/direction/pause
- Soft integration via `editorServerBlock` + editor plugin (`vendor/vmedia/editor-plugin.js`) when `voodflow/voodbuilder` is present

## [0.3.2] - 2026-09-25

### Changed

- Package health: security policy, Dependabot with update cooldown, pinned GitHub Actions, lean Composer dist (`export-ignore`)
- Laravel Pint added (`composer format`) and applied — formatting only, no behaviour change

## [0.3.1] - 2026-09-24

### Fixed

- Media library table thumbs use request-aware URLs (`url(/storage/...)`) instead of Filament `disk()->url()` / `APP_URL`, so previews load behind Docker ports and reverse proxies

## [0.3.0] - 2026-09-24

### Added

- Configurable image size ladder (thumb / sm / md / lg / xl by default) via Filament **VoodMedia → Settings** repeater
- API asset payload fields: `display`, `variants`, `srcset`, `sizes` for responsive delivery
- `vmedia:regenerate-conversions` Artisan command (also from Settings UI)
- `ConversionLadder` + `VmediaSettings` (JSON settings table)

### Changed

- `MediaVault` registers Spatie conversions from the ladder (not only fixed thumb/preview)
- Default display conversion (`lg`, ~2048px) is preferred for backgrounds / hero fills over the raw original

## [0.2.12] - 2026-09-17

### Fixed

- `vmedia:install` now adds missing `media.deleted_at` when Spatie `create_media_table` is published with a later timestamp than the package soft-deletes migrations (common fresh install race)
- Late ensure migration `2099_01_01_000000_ensure_media_soft_deletes_column` so a plain `php artisan migrate` also repairs the column

## [0.2.11] - 2026-09-16

### Changed

- Remove in-repo work documentation (checklists, sales/developer stubs, internal notes); keep public README assets under `docs/images/` (and product academy/manual where applicable)


# Changelog

## [0.2.10] - 2026-09-14

### Fixed

- Public/thumb browser URLs for the local `public` disk are root-relative (`/storage/...`) so library previews and markdown embeds keep working when `APP_URL` host/port differs from the active request (common in Docker)
- Companion plugin vault registrations survive `Vmedia::reset()` during application boot

### Changed

- Markdown editor “media library” toolbar icon: custom photo + stack mark (was a plain folder); RichEditor tool uses `Photo` (clearer than `RectangleStack`)
- VoodMedia reserves its configured route prefixes with Voodbuilder when both packages are installed

## [0.2.9] - 2026-09-09

### Changed

- Filament navigation group default label is **VoodMedia** (was `Media`; still overridable via `VMEDIA_NAV_GROUP`)
- Plugin vault roots are **registered by companions** (`Vmedia::registerPluginVault` / `RegistersPluginVault`); VoodMedia no longer hardcodes Builder/Vtuts/… folders
- Removed `VMEDIA_VOODBUILDER_EDITOR_ROUTES` and `/voodbuilder/editor/media*` compatibility aliases — Asset Manager uses `/vmedia/*` only
- Shared **Logos** folder remains owned by VoodMedia
- Public/thumb URLs use `Storage::disk(...)->url(...)` so `VMEDIA_DISK=s3` (or any Laravel disk) works beyond local `/storage`

### Docs

- Document PHP `gd` with JPEG (and recommended WebP/FreeType) as a hard runtime requirement for Spatie thumb conversions
- README branding as VoodMedia + admin screenshots; S3 / disk setup; plugin vault registration guide
- Trimmed `docs/` to images + release checklist only

## [0.2.8] - 2026-09-07

### Fixed

- Media library Galleries column shows parent context (e.g. `Builder › Library`) plus a tooltip with the gallery count

## [0.2.7] - 2026-09-07

### Fixed

- Bulk “Assign tags” toggle no longer reuses the galleries wording (“Remove from other galleries”); it now says remove/keep other tags

## [0.2.6] - 2026-09-07

### Changed

- Bulk “Assign galleries / tags” toggle wording clarifies Off (add, keep current) vs On (remove from other galleries/tags)

## [0.2.5] - 2026-09-07

### Fixed

- Bulk “Assign galleries” and the library gallery filter show parent context (e.g. `Builder › Library`) so duplicate album names are distinguishable

## [0.2.4] - 2026-09-07

### Fixed

- Upload / ZIP gallery pickers list **albums only** (folders cannot hold media)
- Storing media against a folder resolves to that folder’s Library album instead of orphaning files with no gallery membership

## [0.2.3] - 2026-09-07

### Fixed

- Media authorization no longer denies uploads when Filament has no enumerable panels during editor requests (Shield-less installs)
- Ensure SoftDeletes column on `media` even when the original soft-deletes migration ran before Spatie created the table (fixes `/admin/vmedia/library` 1054 on `media.deleted_at`)
- MediaItem temporarily disables SoftDeletingScope when `deleted_at` is missing so the admin stays usable until migrate

## [0.2.2] - 2026-09-07

### Fixed

- Shield-less Filament installs can upload/list media (policies use panel access when Shield is absent)
