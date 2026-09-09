# Changelog

## [Unreleased]

### Changed

- Plugin vault roots are **registered by companions** (`Vmedia::registerPluginVault` / `RegistersPluginVault`); VoodMedia no longer hardcodes Builder/Vtuts/… folders
- Removed `VMEDIA_VOODBUILDER_EDITOR_ROUTES` and `/voodbuilder/editor/media*` compatibility aliases — Asset Manager uses `/vmedia/*` only
- Shared **Logos** folder remains owned by VoodMedia

### Docs

- Document PHP `gd` with JPEG (and recommended WebP/FreeType) as a hard runtime requirement for Spatie thumb conversions
- README branding as VoodMedia + admin screenshots

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

