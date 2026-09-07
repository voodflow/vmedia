# Changelog

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

