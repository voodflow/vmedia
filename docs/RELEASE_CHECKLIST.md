# VoodMedia — release checklist

## Release gate

- [x] README quick start + plugin vault registration guide
- [x] Install command + migration story for fresh hosts
- [x] Plugin vault roots owned by companions (`Vmedia::registerPluginVault`) — no hardcoded product list / editor route aliases
- [x] Promo + UI screenshots in `docs/images/`

## Shipped (sprints A–C)

- [x] Media Picker Filament (`VmediaPicker`)
- [x] Alt / credits / focal / poster metadata
- [x] Thumb conversions (config-gated)
- [x] Usage count + protect delete
- [x] MIT LICENSE + README quick start
- [x] Files UX (documents collection)
- [x] Laravel events
- [x] Stats widget + `vmedia:stats`
- [x] Orphan prune command
- [x] Public gallery page
- [x] Video poster metadata
- [x] Soft delete / trash
- [x] Duplicate detection (SHA-256 reuse)
- [x] ZIP import

## Nice-to-have later

- [ ] ffmpeg-based auto video poster frames
- [ ] Livewire interactive public gallery

## Test status

Run from package root:

```bash
./vendor/bin/phpunit
```

## Security & vulnerability review

| Area | Finding | Severity |
|------|---------|----------|
| Authz | auth + policies + optional Gate ability | OK |
| Uploads | MIME allow-list, path traversal checks, ZIP entry sanitization | OK |
| Mass assignment | Controlled via library helpers | OK |
| XSS | Admin Filament; public URLs sanitized relative paths | OK |
| CSRF | Filament/web middleware on mutating routes | OK |
| Secrets | None | OK |
| Filament exposure | Library only for authenticated panel/API users | OK |

**Critical fixes applied this audit:** none.
