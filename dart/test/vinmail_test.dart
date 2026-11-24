import 'dart:convert';
import 'dart:typed_data';

import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:test/test.dart';
import 'package:vinmail/vinmail.dart';

void main() {
  test('sends email with Bearer auth, Idempotency-Key, and PDF attachment', () async {
    late http.Request captured;
    final mock = MockClient((req) async {
      captured = req;
      return http.Response(
        jsonEncode({'id': 'em_dart_1', 'status': 'queued'}),
        202,
        headers: {'content-type': 'application/json'},
      );
    });

    final client = VinMail(
      apiKey: 'vm_live_dart_key',
      baseUrl: 'https://api.example.test',
      httpClient: mock,
    );

    final res = await client.emails.send(
      SendEmailRequest(
        from: 'Billing <billing@acme.com>',
        to: ['buyer@example.com'],
        subject: 'Invoice #42',
        text: 'Attached.',
        attachments: [
          VinMailAttachment.fromBytes(
            filename: 'invoice-42.pdf',
            bytes: Uint8List.fromList(utf8.encode('%PDF-1.4 dart invoice')),
          ),
        ],
        idempotencyKey: 'inv-42',
      ),
    );

    expect(res.id, 'em_dart_1');
    expect(res.status, 'queued');
    expect(captured.url.toString(), 'https://api.example.test/v1/emails');
    expect(captured.headers['Authorization'], 'Bearer vm_live_dart_key');
    expect(captured.headers['Idempotency-Key'], 'inv-42');

    final body = jsonDecode(captured.body) as Map<String, dynamic>;
    final atts = body['attachments'] as List<dynamic>;
    expect(atts, hasLength(1));
    expect((atts.first as Map)['filename'], 'invoice-42.pdf');
    expect((atts.first as Map)['contentType'], 'application/pdf');
  });

  test('rejects non-business file extensions', () {
    expect(
      () => VinMailAttachment.fromBytes(
        filename: 'installer.apk',
        bytes: Uint8List.fromList([1, 2, 3]),
      ),
      throwsA(
        isA<VinMailException>()
            .having((e) => e.code, 'code', 'validation_error')
            .having((e) => e.message, 'message', contains('not permitted')),
      ),
    );
  });

  test('retries transient 503 and surfaces 422 API errors', () async {
    var attempts = 0;
    final mock = MockClient((req) async {
      attempts += 1;
      if (attempts == 1) {
        return http.Response('{"error":{"code":"unavailable","message":"retry"}}', 503);
      }
      if (attempts == 2) {
        return http.Response('{"id":"em_retry","status":"queued"}', 202);
      }
      return http.Response(
        '{"error":{"code":"domain_not_verified","message":"Verify domain first"}}',
        422,
      );
    });

    final client = VinMail(apiKey: 'vm_live_dart_key', maxRetries: 2, httpClient: mock);
    final res = await client.emails.send(
      const SendEmailRequest(
        to: ['ada@example.com'],
        template: 'otp',
        variables: {'code': '123456'},
      ),
    );
    expect(res.id, 'em_retry');
    expect(attempts, 2);

    await expectLater(
      () => client.emails.send(
        const SendEmailRequest(to: ['ada@example.com'], subject: 'Hi', text: 'Hi'),
      ),
      throwsA(
        isA<VinMailException>()
            .having((e) => e.status, 'status', 422)
            .having((e) => e.code, 'code', 'domain_not_verified'),
      ),
    );
  });

  test('fetches email delivery status via emails.get()', () async {
    final mock = MockClient((req) async {
      expect(req.url.path, '/v1/emails/em_99');
      return http.Response(
        jsonEncode({
          'id': 'em_99',
          'status': 'delivered',
          'to': ['ada@example.com'],
          'subject': 'Receipt',
          'templateName': null,
          'recipientCount': 1,
          'error': null,
          'createdAt': '2026-11-24T10:00:00.000Z',
          'sentAt': '2026-11-24T10:00:01.000Z',
          'events': [
            {
              'type': 'delivery',
              'recipient': 'ada@example.com',
              'detail': null,
              'createdAt': '2026-11-24T10:00:02.000Z',
            }
          ],
        }),
        200,
      );
    });

    final client = VinMail(apiKey: 'vm_live_dart_key', httpClient: mock);
    final details = await client.emails.get('em_99');
    expect(details.status, 'delivered');
    expect(details.events, hasLength(1));
  });
}
