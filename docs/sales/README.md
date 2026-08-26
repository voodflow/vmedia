# Vmedia — product overview

Reusable **media library and galleries** for Filament 5, backed by Spatie Media Library. One vault storage copy, many gallery memberships. Works standalone; optional page-builder Asset Manager integration.

**Free MIT pilot** for the Voodflow ecosystem.

## Who it is for

- Sites that need a shared photo/video/document library across products
- Cosmolab hosts with Voodbuilder/Vpress editors that pick media
- Teams replacing ad-hoc upload fields with a central vault

## Key features

- Media vault + galleries (default gallery for uploads)
- Filament library and gallery admin (own **Media** nav group)
- `VmediaPicker` + `VmediaFileUpload` for other plugins
- Metadata: alt, caption, credits; video poster (videos only)
- Thumb conversions, duplicate reuse, soft delete / trash
- Usage protection + orphan prune + stats widget / `vmedia:stats`
- ZIP import; public gallery page (`/galleries/{slug}`)
- Authenticated HTTP API: list, upload, delete
- MIME allow-list and path-traversal guards (`UploadGuard`)
- Laravel events for attach/store/delete lifecycle

## High-level requirements

| Requirement | Value |
|-------------|--------|
| PHP | 8.4+ |
| Laravel | 12 or 13 |
| Filament | 5 |
| Spatie Media Library | 11 |

## Fit in Cosmolab / Voodflow

Shared media backbone for builders and content packages. Table/morph names keep Voodbuilder compatibility while owning `vmedia.*` routes.

## Learn more

- [User manual](../manual/user/index.md)
- [Developer guide](../developer/README.md)
