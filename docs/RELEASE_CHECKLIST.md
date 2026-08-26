# Vmedia — release checklist

## Missing for release

- [ ] Marketplace listing from `docs/sales/README.md`
- [ ] Confirm install command + migration story for fresh hosts
- [ ] Document deprecation timeline for `voodbuilder/editor` aliases

## Nice-to-have

- [ ] More feature tests beyond security suite
- [ ] Video poster / thumbnail UX polish
- [ ] External storage (S3) buyer guide

## Test status

**Result (2026-08-25, Docker PHP 8.4 / package phpunit|pest):** PASS

19 tests, 52 assertions.

## Code quality vs Filament 5

- Own navigation group; Filament 5 plugin registration
- Spatie media integration aligned with Filament 5 plugin

## Security & vulnerability review

| Area | Finding | Severity |
|------|---------|----------|
| Authz | auth + policies + optional Gate ability | OK |
| Uploads | MIME allow-list, path traversal checks | OK |
| Mass assignment | Controlled via library helpers | OK |
| XSS | Admin Filament; public URLs sanitized relative paths | OK |
| CSRF | Filament/web middleware on mutating routes | OK |
| Secrets | None | OK |
| Filament exposure | Library only for authenticated panel/API users | OK |

**Critical fixes applied this audit:** none.
