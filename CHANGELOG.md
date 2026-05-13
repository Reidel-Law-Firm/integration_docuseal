# Changelog

## [1.1.11] - 2026-05-13

### Fixed
- Webhook secret validation now accepts the raw value in any of the common
  DocuSeal custom-header names (`X-Auth-Secret`, `X-Webhook-Secret`, etc.),
  `Authorization: Bearer`, or a `?secret=` query param — and still supports
  HMAC-SHA256 in `X-Docuseal-Signature`. Previously only the HMAC mode worked,
  which is not what DocuSeal sends by default.
- Admin "Save" no longer fails when the DocuSeal server is not yet reachable;
  the connection probe is now best-effort and the save always returns 200.
- "Signatures" sidebar tab no longer renders blank on files with no requests:
  it now shows an empty-state message and a hint about the right-click action.
- "Signatures" sidebar tab now shows on PNG/JPEG/DOC files, not only PDF/DOCX.
- `webpack.config.js` now applies `node-polyfill-webpack-plugin` and disables
  `fullySpecified` resolution: required so `axios` 1.x and `@nextcloud/dialogs`
  (which pulls `webdav`) can be bundled without "Can't resolve 'buffer'" or
  "node-stdlib-browser/cjs/proxy/process" errors.

### Added
- "Test connection" button in admin settings to re-check the DocuSeal server
  without re-saving credentials. New `POST /config/test` route.
- Webhook failure log now reports which credential headers were present, to
  make misconfiguration easier to diagnose.
- README: explicit, step-by-step webhook setup guide covering the
  `X-Auth-Secret` custom header DocuSeal sends.

## [1.0.6] - 2026-03-24

### Changed
- Replaced all icons with new DocuSeal branding (monitor + pen design)

### Fixed
- Fixed FileAction API for @nextcloud/files v4 (was not a constructor)
- Fixed null slot props crash in MultiselectWho component
- Fixed NcCheckboxRadioSwitch binding (uses modelValue, not checked)
- Removed taggable from NcSelect to fix null crash on non-email input
- Fixed DocuSeal API 422 error: always include body in message param

## [1.0.0] - 2026-03-23

### Added
- Direct signing of PDF, DOCX and image files
- Template-based signing with DocuSeal template preview
- Embedded signing (iframe) and email-based signing
- Signature status tracking in file sidebar with progress bar
- Automatic download of signed documents
- Real-time webhooks with HMAC-SHA256 validation
- Background polling job every 15 minutes
- Nextcloud notifications (signed, declined, completed, expired)
- Unified Search for signature requests
- Dashboard Widget
- Activity app integration
- Automatic CSP policy for iframes
- Resend reminders to signers
- Cancel pending requests
- Configurable expiry on requests
- Audit trail with timeline and PDF download
- Translations: Italian, English, German, French, Spanish
- API key encryption with ICrypto
- PHPUnit tests
- Support for Nextcloud 28-34, PHP 8.1+, Vue 3
