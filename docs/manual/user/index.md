# Vmedia user manual

Media library and galleries for the Filament admin.

## Navigation

Vmedia registers its own **Media** navigation group (configurable). It is not nested under page-builder settings.

| Item | Purpose |
|------|---------|
| Media library | Browse, upload, edit titles/captions, assign galleries, delete |
| Galleries | Create albums, set default gallery, browse items in a gallery |

## Galleries

- Each gallery has a name, unique slug (auto from name), description, public flag, and sort order.
- Exactly one gallery is **default** — used when no folder/album is selected (e.g. “All media” in the page builder).
- **Browse** any folder or album; **upload** goes to the one you selected. Selecting a **folder** stores files in its `Library` album (created automatically for plugin roots such as Builder, Events, …).
- The default gallery cannot be deleted until another gallery is marked default.

### Page builder

When inserting images in VoodBuilder, pick **Builder** (or another folder) in the sidebar before uploading so files land in the right place. See the builder manual: *Media library in the builder*.

## Library

- Files are stored once in a **vault**; galleries only hold memberships.
- A file can belong to many galleries.
- After upload you can refine human-readable titles and captions (storage filename stays hashed).
- Bulk actions: edit meta, assign/replace galleries, create a gallery from the selection.

## Photos and videos

Use the type filter (photos / videos) or the icon column. Accepted types and size limits come from package config.
