# @vinworld/vinmail

Official zero-dependency **Node.js & TypeScript SDK** for the [VinMail](https://vinmail.vinworlddev.com) transactional email API.

Works out of the box in **Node.js 18+**, **Next.js**, **NestJS**, **Express**, **Bun**, **Deno**, and **Cloudflare Workers**.

## Installation

```bash
npm install @vinworld/vinmail
```

## Quick Start

```ts
import { VinMail, VinMailError } from '@vinworld/vinmail';

const vinmail = new VinMail(process.env.VINMAIL_API_KEY!);

const { id, status } = await vinmail.emails.send({
  from: 'YourApp <no-reply@yourdomain.com>',
  replyTo: 'contact@yourdomain.com',
  to: 'customer@example.com',
  subject: 'Your verification code',
  html: '<p>Your code is <strong>482913</strong>.</p>',
  text: 'Your code is 482913.',
  idempotencyKey: 'otp-customer-482913',
});

console.log({ id, status }); // { id: '...', status: 'queued' }
```

## Using Templates

```ts
await vinmail.emails.send({
  to: 'customer@example.com',
  template: 'otp', // built-in global templates: "otp", "notification", "notification-action"
  variables: {
    appName: 'YourApp',
    code: '482913',
    minutes: 10,
  },
});
```

## Business File Attachments

Pass either a local `path` (Node.js reads & base64-encodes automatically), a `Buffer`/`Uint8Array`, or a base64 string:

```ts
await vinmail.emails.send({
  from: 'Billing <billing@yourdomain.com>',
  to: 'customer@example.com',
  subject: 'Invoice #1042',
  text: 'Please see your attached invoice.',
  attachments: [
    { path: './invoices/invoice-1042.pdf' },
    { filename: 'receipt.png', content: receiptBuffer },
  ],
});
```

**Allowed business formats:** `PDF`, `DOC/DOCX`, `XLS/XLSX`, `CSV`, `PPT/PPTX`, `TXT`, `RTF`, `PNG`, `JPG/JPEG`, `WEBP`, `GIF`.  
**Limits:** up to **5 files**, **5 MB** per file, **7.5 MB** combined per email.

## Check Delivery Status

```ts
const email = await vinmail.emails.get('em_...');
console.log(email.status, email.events);
```

## Error Handling

```ts
try {
  await vinmail.emails.send({ /* ... */ });
} catch (err) {
  if (err instanceof VinMailError) {
    console.error(err.status, err.code, err.message);
  }
}
```
