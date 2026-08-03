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
- Exactly one gallery is **default**. Frontend / editor uploads always land there.
- Choose / browse can still open any gallery.
- The default gallery cannot be deleted until another gallery is marked default.

## Library

- Files are stored once in a **vault**; galleries only hold memberships.
- A file can belong to many galleries.
- After upload you can refine human-readable titles and captions (storage filename stays hashed).
- Bulk actions: edit meta, assign/replace galleries, create a gallery from the selection.

## Photos and videos

Use the type filter (photos / videos) or the icon column. Accepted types and size limits come from package config.
