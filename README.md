# DocuSeal Integration for Nextcloud

[![Nextcloud](https://img.shields.io/badge/Nextcloud-28--34-blue?logo=nextcloud)](https://nextcloud.com)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple?logo=php)](https://php.net)
[![Vue 3](https://img.shields.io/badge/Vue-3-green?logo=vue.js)](https://vuejs.org)
[![License](https://img.shields.io/badge/License-AGPL--3.0-red)](LICENSE)

Full integration of **DocuSeal Enterprise** (self-hosted) with Nextcloud for electronic document signing directly from the Files app.

---

## Features

### Document Signing
- **Direct signing** - Right-click on PDF, DOCX or image -> send for signature
- **Template-based signing** - Use pre-configured DocuSeal templates with field/role preview
- **Embedded signing** - Signers can sign directly within Nextcloud (iframe)
- **Email signing** - DocuSeal sends emails to signers with signing links
- **Multiple recipients** - Nextcloud users (autocomplete) or external email addresses

### Tracking & Management
- **Real-time status** - File sidebar with per-signer status and progress bar
- **Nextcloud notifications** - Signed, declined, completed, expired
- **Automatic download** - Signed PDF is saved automatically in the same folder
- **Audit trail** - Full timeline with PDF audit log download
- **Send reminders** - Resend notification to signers who haven't signed yet
- **Cancellation** - Cancel pending signature requests
- **Expiry** - Set expiration date on requests

### Nextcloud Integrations
- **Unified Search** - Search signature requests from the search bar
- **Dashboard Widget** - Widget showing pending signature status
- **Activity** - Integrated activity log with the Activity app
- **CSP Policy** - Automatic Content Security Policy for iframe embedding

### Security
- **Encrypted API Key** - Stored using Nextcloud's ICrypto
- **Webhook HMAC-SHA256** - Webhook validation with shared secret
- **File access control** - Permission checks before every operation

### Multi-language
Full translations: **Italian**, **English**, **German**, **French**, **Spanish**

Language is selected automatically based on the user's Nextcloud settings.

---

## Requirements

| Component | Version |
|---|---|
| Nextcloud | 28 - 34 |
| PHP | 8.1+ |
| DocuSeal Enterprise | Self-hosted |
| Node.js | 20+ (build only) |

---

## Installation

### 1. Copy the app

```bash
cp -r integration_docuseal /path/to/nextcloud/apps/
chown -R www-data:www-data /path/to/nextcloud/apps/integration_docuseal
```

### 2. Build frontend

```bash
cd /path/to/nextcloud/apps/integration_docuseal
npm install --legacy-peer-deps
npm run build
```

### 3. Enable the app

```bash
sudo -u www-data php occ app:enable integration_docuseal
```

### 4. Configure

1. Go to **Settings -> Connected accounts -> DocuSeal**
2. Enter your DocuSeal server URL (e.g. `https://docuseal.example.com`)
3. Enter the API Key (found in DocuSeal: Settings -> API)
4. Click **Save** - the connection is tested automatically

### 5. Configure Webhook (optional but recommended)

In the DocuSeal admin panel:
1. Go to **Settings -> Webhook**
2. Enter the URL: `https://yournextcloud.com/apps/integration_docuseal/webhook`
3. (Optional) Set a shared secret for HMAC validation

---

## Usage

### Requesting a signature

1. Go to the Nextcloud **Files** app
2. Right-click on a PDF, DOCX or image file
3. Select **"Request signature with DocuSeal"**
4. Choose the mode:
   - **Direct upload** - The file is sent as-is
   - **DocuSeal template** - Use a pre-configured template
5. Add recipients (Nextcloud users or email addresses)
6. Options: email notification, embedded signing, expiry, custom message
7. Click **Request signature**

### Monitoring signatures

- Open the file **sidebar** to see signature status
- Use the **search bar** to find signature requests
- Check the **Dashboard** for an overview
- Receive real-time **notifications**

### Audit Trail

From the file sidebar, click **"Audit trail"** to view:
- Full timeline (sent, opened, signed, declined)
- PDF audit log download from DocuSeal

---

## Architecture

```
integration_docuseal/
├── appinfo/           App metadata + 18 API routes
├── lib/
│   ├── Activity/      Activity app integration
│   ├── BackgroundJob/ Status polling every 15 min
│   ├── Controller/    3 controllers (Config, DocuSeal, Webhook)
│   ├── Dashboard/     Dashboard widget
│   ├── Db/            2 entities + 2 mappers (requests + submitters)
│   ├── Listener/      CSP for iframes
│   ├── Migration/     Database schema
│   ├── Notification/  4 notification types
│   ├── Search/        Unified Search provider
│   ├── Service/       API service + utilities
│   └── Settings/      Admin panel
├── src/               Vue 3 components
├── l10n/              5 languages
└── tests/             PHPUnit tests
```

### Database

Two tables:
- `oc_docuseal_requests` - Signature requests (user, file, submission, status)
- `oc_docuseal_submitters` - Signers (email, status, embed URL)

### API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | `/info` | Check configuration |
| GET | `/templates` | List DocuSeal templates |
| GET | `/templates/{id}` | Template details |
| POST | `/sign/direct/{fileId}` | Direct file signing |
| POST | `/sign/template` | Template-based signing |
| GET | `/requests` | List user requests |
| GET | `/requests/{id}` | Request details |
| GET | `/requests/file/{fileId}` | Requests for a file |
| POST | `/requests/{id}/resend/{submitterId}` | Resend reminder |
| POST | `/requests/{id}/cancel` | Cancel request |
| GET | `/requests/{id}/audit` | Audit trail |
| GET | `/embed/{requestId}` | Embedded signing URL |
| POST | `/webhook` | DocuSeal webhook |

---

## Development

```bash
# Setup
make dev-setup

# Development build
npm run dev

# Watch mode
npm run watch

# Lint
make lint

# Test
make test

# Production build
npm run build

# Package for distribution
make appstore
```

---

## Supported file types

| Type | Extensions | Direct signing | Template |
|---|---|---|---|
| PDF | .pdf | Yes | Yes |
| Word | .docx, .doc | Yes | Yes |
| Images | .png, .jpg, .jpeg | Yes | Yes |

---

## License

AGPL-3.0-or-later

Developed by **GEST CE** for integration with DocuSeal Enterprise.
