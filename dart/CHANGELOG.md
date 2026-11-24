## 0.1.0

- Initial release of the official VinMail Dart SDK.
- Supports `vinmail.emails.send(...)` and `vinmail.emails.get(id)`.
- Built-in business file attachment allowlist & size validation (max 5 files, 5 MB each, 7.5 MB total).
- Automatic retry with exponential backoff on HTTP 429 and 5xx responses plus `Idempotency-Key` support.
