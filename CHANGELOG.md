# Changelog

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
