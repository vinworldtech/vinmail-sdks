import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:vinmail/vinmail.dart';

Future<void> main() async {
  final apiKey = Platform.environment['VINMAIL_API_KEY'] ?? 'vm_live_your_key';
  final vinmail = VinMail(apiKey: apiKey);

  try {
    final res = await vinmail.emails.send(
      SendEmailRequest(
        from: 'YourApp <no-reply@yourdomain.com>',
        replyTo: 'contact@yourdomain.com',
        to: ['customer@example.com'],
        subject: 'Invoice #1042',
        text: 'Please see your attached invoice.',
        attachments: [
          VinMailAttachment.fromBytes(
            filename: 'invoice-1042.pdf',
            bytes: Uint8List.fromList(utf8.encode('%PDF-1.4 demo')),
          ),
        ],
        idempotencyKey: 'invoice-1042',
      ),
    );

    print('Queued email: ${res.id} (${res.status})');
  } on VinMailException catch (err) {
    stderr.writeln('VinMail error (${err.status} / ${err.code}): ${err.message}');
  } finally {
    vinmail.close();
  }
}
