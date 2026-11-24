# vinworld/vinmail (PHP & Laravel SDK)

Official **PHP 8.1+ & Laravel SDK** for the [VinMail](https://vinmail.vinworlddev.com) transactional email API.

Includes both a standalone PHP client (zero Composer dependencies, uses native `cURL`) and **automatic Laravel Mail driver integration** (`MAIL_MAILER=vinmail`).

## Installation

```bash
composer require vinworld/vinmail
```

## Quick Start (Plain PHP or Laravel)

```php
use VinWorld\VinMail\Attachment;
use VinWorld\VinMail\VinMailClient;

$vinmail = new VinMailClient(getenv('VINMAIL_API_KEY'));

$result = $vinmail->emails->send([
    'from' => 'YourApp <no-reply@yourdomain.com>',
    'replyTo' => 'contact@yourdomain.com',
    'to' => 'customer@example.com',
    'subject' => 'Your Invoice #1042',
    'html' => '<p>Thank you for your business!</p>',
    'text' => 'Thank you for your business!',
    'attachments' => [
        Attachment::fromPath(__DIR__ . '/invoice-1042.pdf'),
    ],
], idempotencyKey: 'invoice-1042');

echo $result['id'];
```

## Laravel Drop-In Mail Driver (`MAIL_MAILER=vinmail`)

1. Add your API key to `.env`:
   ```env
   MAIL_MAILER=vinmail
   VINMAIL_API_KEY=vm_live_your_key_here
   MAIL_FROM_ADDRESS=no-reply@yourdomain.com
   MAIL_FROM_NAME="YourApp"
   ```
2. Add the mailer entry in `config/mail.php` under `mailers`:
   ```php
   'vinmail' => [
       'transport' => 'vinmail',
   ],
   ```
3. Add to `config/services.php`:
   ```php
   'vinmail' => [
       'key' => env('VINMAIL_API_KEY'),
   ],
   ```
4. Keep using standard Laravel `Mail::to($user)->send(new WelcomeMail())` — or use the `VinMail` facade directly:
   ```php
   use VinWorld\VinMail\Laravel\Facades\VinMail;

   VinMail::emails()->send([
       'to' => $user->email,
       'template' => 'otp',
       'variables' => ['appName' => 'YourApp', 'code' => '482913', 'minutes' => 10],
   ]);
   ```

## Check Delivery Status

```php
$details = $vinmail->emails->get('em_...');
```

## Business Attachment Policy

Allowed formats: `PDF`, `DOC/DOCX`, `XLS/XLSX`, `CSV`, `PPT/PPTX`, `TXT`, `RTF`, `PNG`, `JPG/JPEG`, `WEBP`, `GIF`.  
Limits: up to **5 files**, **5 MB** per file, **7.5 MB** combined per email.
