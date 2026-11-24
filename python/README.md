# vinworld-vinmail (Python SDK)

Official zero-dependency **Python 3.9+ SDK** for the [VinMail](https://vinmail.vinworlddev.com) transactional email API.

## Installation

```bash
pip install vinworld-vinmail
```

## Quick Start

```python
import os
from vinmail import Attachment, VinMail, VinMailError

client = VinMail(os.environ["VINMAIL_API_KEY"])

result = client.emails.send(
    from_="YourApp <no-reply@yourdomain.com>",
    reply_to="contact@yourdomain.com",
    to="customer@example.com",
    subject="Payment Receipt #1042",
    html="<p>Thank you for your payment!</p>",
    text="Thank you for your payment!",
    attachments=[Attachment.from_path("./receipt-1042.pdf")],
    idempotency_key="receipt-1042",
)

print(result)  # {'id': '...', 'status': 'queued'}
```

## Using Templates

```python
client.emails.send(
    to="customer@example.com",
    template="otp",
    variables={"appName": "YourApp", "code": "482913", "minutes": 10},
)
```

## Check Delivery Status

```python
details = client.emails.get("em_...")
print(details["status"], details["events"])
```

## Business Attachment Policy

Allowed formats: `PDF`, `DOC/DOCX`, `XLS/XLSX`, `CSV`, `PPT/PPTX`, `TXT`, `RTF`, `PNG`, `JPG/JPEG`, `WEBP`, `GIF`.  
Limits: up to **5 files**, **5 MB** per file, **7.5 MB** combined per email.
