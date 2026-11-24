import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { test } from 'node:test';

import { VinMail, VinMailError } from '../dist/esm/index.js';

function jsonResponse(status, body, headers = {}) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'content-type': 'application/json', ...headers },
  });
}

test('sends transactional email with Idempotency-Key and Bearer auth', async () => {
  let capturedUrl = '';
  let capturedInit;

  const client = new VinMail('vm_live_test_123', {
    baseUrl: 'https://api.example.test',
    fetch: async (url, init) => {
      capturedUrl = String(url);
      capturedInit = init;
      return jsonResponse(202, { id: 'em_01', status: 'queued' });
    },
  });

  const res = await client.emails.send({
    from: 'Acme <no-reply@acme.com>',
    to: 'ada@example.com',
    subject: 'Your verification code',
    text: 'Code: 123456',
    idempotencyKey: 'otp-ada-123456',
  });

  assert.equal(res.id, 'em_01');
  assert.equal(res.status, 'queued');
  assert.equal(capturedUrl, 'https://api.example.test/v1/emails');
  assert.equal(capturedInit.headers.Authorization, 'Bearer vm_live_test_123');
  assert.equal(capturedInit.headers['Idempotency-Key'], 'otp-ada-123456');

  const body = JSON.parse(capturedInit.body);
  assert.equal(body.from, 'Acme <no-reply@acme.com>');
  assert.equal(body.to, 'ada@example.com');
  assert.equal(body.subject, 'Your verification code');
});

test('reads local business PDF attachment and rejects disallowed extensions', async () => {
  const tmpFile = path.join(os.tmpdir(), `vinmail-sdk-test-${Date.now()}.pdf`);
  await fs.writeFile(tmpFile, Buffer.from('%PDF-1.4 demo invoice'));

  let sentBody;
  const client = new VinMail('vm_live_test_123', {
    fetch: async (_url, init) => {
      sentBody = JSON.parse(init.body);
      return jsonResponse(202, { id: 'em_att_1', status: 'queued' });
    },
  });

  try {
    await client.emails.send({
      to: 'client@example.com',
      subject: 'Invoice',
      text: 'Attached.',
      attachments: [{ path: tmpFile, filename: 'Invoice-2026.pdf' }],
    });

    assert.equal(sentBody.attachments.length, 1);
    assert.equal(sentBody.attachments[0].filename, 'Invoice-2026.pdf');
    assert.equal(sentBody.attachments[0].contentType, 'application/pdf');
    assert.equal(
      Buffer.from(sentBody.attachments[0].content, 'base64').toString('utf8'),
      '%PDF-1.4 demo invoice',
    );
  } finally {
    await fs.rm(tmpFile, { force: true });
  }

  await assert.rejects(
    () =>
      client.emails.send({
        to: 'client@example.com',
        subject: 'Bad file',
        text: 'Nope',
        attachments: [{ filename: 'virus.exe', content: Buffer.from('MZ') }],
      }),
    (err) => err instanceof VinMailError && err.code === 'validation_error' && /not permitted/i.test(err.message),
  );
});

test('retries transient 503 responses and returns parsed VinMailError on 422', async () => {
  let attempts = 0;
  const client = new VinMail('vm_live_test_123', {
    maxRetries: 2,
    fetch: async () => {
      attempts += 1;
      if (attempts === 1) {
        return jsonResponse(503, { error: { code: 'unavailable', message: 'Try again' } });
      }
      return jsonResponse(202, { id: 'em_retry_ok', status: 'queued' });
    },
  });

  const ok = await client.emails.send({
    to: 'ada@example.com',
    template: 'otp',
    variables: { code: '999999' },
  });
  assert.equal(ok.id, 'em_retry_ok');
  assert.equal(attempts, 2);

  const failingClient = new VinMail('vm_live_test_123', {
    maxRetries: 2,
    fetch: async () =>
      jsonResponse(422, {
        error: { code: 'domain_not_verified', message: 'Verify your sending domain first.' },
      }),
  });

  await assert.rejects(
    () => failingClient.emails.send({ to: 'ada@example.com', subject: 'Hi', text: 'Hi' }),
    (err) => err instanceof VinMailError && err.status === 422 && err.code === 'domain_not_verified',
  );
});

test('fetches delivery status via emails.get()', async () => {
  const client = new VinMail('vm_live_test_123', {
    fetch: async (url) => {
      assert.ok(String(url).endsWith('/v1/emails/em_42'));
      return jsonResponse(200, {
        id: 'em_42',
        status: 'delivered',
        to: ['ada@example.com'],
        subject: 'Receipt',
        templateName: null,
        recipientCount: 1,
        error: null,
        createdAt: '2026-11-24T10:00:00.000Z',
        sentAt: '2026-11-24T10:00:01.000Z',
        events: [{ type: 'delivery', recipient: 'ada@example.com', detail: null, createdAt: '2026-11-24T10:00:02.000Z' }],
      });
    },
  });

  const details = await client.emails.get('em_42');
  assert.equal(details.status, 'delivered');
  assert.equal(details.events.length, 1);
});
