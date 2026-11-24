# VinMail Official SDKs

Official lightweight client libraries for the [VinMail](https://vinmail.vinworlddev.com) transactional email API (`https://vinmail-api.vinworlddev.com`).

| Language / Framework | Package | Install | Folder |
| :--- | :--- | :--- | :--- |
| **Node.js / TypeScript** | `@vinworld/vinmail` | `npm install @vinworld/vinmail` | [`node/`](./node) |
| **PHP / Laravel** | `vinworld/vinmail` | `composer require vinworld/vinmail` | [`php/`](./php) |
| **Python 3.9+** | `vinworld-vinmail` | `pip install vinworld-vinmail` | [`python/`](./python) |
| **Dart (Server-side)** | `vinmail` | `dart pub add vinmail` | [`dart/`](./dart) |

## Shared Features Across All SDKs

- **Zero or minimal dependencies** (native `fetch` in Node 18+, native `cURL` in PHP, stdlib `urllib` in Python, `package:http` in Dart).
- **Automatic retry + idempotency** on HTTP `429` (respecting `Retry-After`) and transient `5xx` errors, paired with `Idempotency-Key`.
- **Business-only attachment validation** matching VinMail's backend policy (`PDF, DOC/DOCX, XLS/XLSX, CSV, PPT/PPTX, TXT, RTF, PNG, JPG/JPEG, WEBP, GIF`; max **5 files**, **5 MB** per file, **7.5 MB** combined).
- **Laravel drop-in driver:** `vinworld/vinmail` auto-registers a Laravel `MAIL_MAILER=vinmail` transport so existing `Mail::to(...)->send(...)` calls work unchanged.

## Security Note for Mobile & Frontend Apps

Never embed a `vm_live_...` project API key inside a mobile app (Flutter, React Native) or frontend bundle (React, Vue). Always call VinMail from your backend server.

## Publishing

See [PUBLISHING.md](./PUBLISHING.md) for complete step-by-step instructions to register your free publisher accounts and publish all four packages to npm, Packagist, PyPI, and pub.dev.
