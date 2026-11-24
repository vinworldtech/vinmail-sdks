# vinmail (Dart SDK)

Official **Dart SDK** for the [VinMail](https://vinmail.vinworlddev.com) transactional email API — built for server-side Dart (**Dart Frog**, **Serverpod**, **Shelf**, backend workers, and CLI tools).

> **Security Warning for Flutter Apps:** Never bundle a `vm_live_...` API key inside a client-side Flutter mobile or web app. Anyone can extract embedded keys from a compiled APK/IPA. For Flutter apps, call your own backend endpoint (built with Node.js, Laravel, Python, or server-side Dart) which then calls VinMail.

## Installation

```bash
dart pub add vinmail
```

## Quick Start

```dart
import 'dart:io';
import 'package:vinmail/vinmail.dart';

Future<void> main() async {
  final vinmail = VinMail(apiKey: Platform.environment['VINMAIL_API_KEY']!);

  final result = await vinmail.emails.send(
    SendEmailRequest(
      from: 'YourApp <no-reply@yourdomain.com>',
      to: ['customer@example.com'],
      subject: 'Your verification code',
      text: 'Your code is 482913.',
      html: '<p>Your code is <strong>482913</strong>.</p>',
      idempotencyKey: 'otp-customer-482913',
    ),
  );

  print('Queued: ${result.id}');
  vinmail.close();
}
```

## Business File Attachments

```dart
final pdfBytes = await File('invoice-1042.pdf').readAsBytes();

await vinmail.emails.send(
  SendEmailRequest(
    from: 'Billing <billing@yourdomain.com>',
    to: ['customer@example.com'],
    subject: 'Invoice #1042',
    text: 'Please see your attached invoice.',
    attachments: [
      VinMailAttachment.fromBytes(
        filename: 'invoice-1042.pdf',
        bytes: pdfBytes,
      ),
    ],
  ),
);
```

**Allowed business formats:** `PDF`, `DOC/DOCX`, `XLS/XLSX`, `CSV`, `PPT/PPTX`, `TXT`, `RTF`, `PNG`, `JPG/JPEG`, `WEBP`, `GIF`.  
**Limits:** up to **5 files**, **5 MB** per file, **7.5 MB** combined per email.

## Check Delivery Status

```dart
final details = await vinmail.emails.get('em_...');
print('${details.status} (${details.events.length} events)');
```
