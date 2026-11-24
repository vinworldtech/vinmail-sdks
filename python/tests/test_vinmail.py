from __future__ import annotations

import base64
import json
import tempfile
import unittest
from pathlib import Path
from typing import Dict, List, Optional, Tuple

from vinmail import Attachment, VinMail, VinMailError


class FakeTransport:
    def __init__(self, responses: List[Tuple[int, Dict[str, str], bytes]]) -> None:
        self.responses = list(responses)
        self.requests: List[Dict[str, object]] = []

    def __call__(
        self,
        method: str,
        url: str,
        headers: Dict[str, str],
        body: Optional[bytes],
        timeout: float,
    ) -> Tuple[int, Dict[str, str], bytes]:
        self.requests.append(
            {"method": method, "url": url, "headers": headers, "body": body}
        )
        return self.responses.pop(0)


class VinMailPythonSdkTest(unittest.TestCase):
    def test_send_with_attachment_and_idempotency(self) -> None:
        transport = FakeTransport([(202, {}, b'{"id":"em_py_1","status":"queued"}')])
        client = VinMail("vm_live_py_key", base_url="https://api.example.test", transport=transport)

        with tempfile.TemporaryDirectory() as tmp:
            pdf = Path(tmp) / "invoice.pdf"
            pdf.write_bytes(b"%PDF-1.4 py invoice")

            res = client.emails.send(
                from_="Billing <billing@acme.com>",
                to="buyer@example.com",
                subject="Invoice #9",
                text="Attached.",
                attachments=[Attachment.from_path(pdf)],
                idempotency_key="inv-9",
            )

        self.assertEqual(res["id"], "em_py_1")
        req = transport.requests[0]
        headers = req["headers"]
        assert isinstance(headers, dict)
        self.assertEqual(headers["Authorization"], "Bearer vm_live_py_key")
        self.assertEqual(headers["Idempotency-Key"], "inv-9")

        body_bytes = req["body"]
        assert isinstance(body_bytes, bytes)
        payload = json.loads(body_bytes.decode("utf-8"))
        self.assertEqual(payload["attachments"][0]["filename"], "invoice.pdf")
        self.assertEqual(payload["attachments"][0]["contentType"], "application/pdf")
        self.assertEqual(
            base64.b64decode(payload["attachments"][0]["content"]),
            b"%PDF-1.4 py invoice",
        )

    def test_rejects_disallowed_file_type(self) -> None:
        with self.assertRaises(VinMailError) as ctx:
            Attachment.from_bytes("archive.zip", b"PK\x03\x04")
        self.assertEqual(ctx.exception.code, "validation_error")

    def test_retries_on_503_and_raises_on_422(self) -> None:
        transport = FakeTransport(
            [
                (503, {}, b'{"error":{"code":"unavailable","message":"retry"}}'),
                (202, {}, b'{"id":"em_retry","status":"queued"}'),
                (422, {}, b'{"error":{"code":"domain_not_verified","message":"Not verified"}}'),
            ]
        )
        client = VinMail("vm_live_py_key", max_retries=2, transport=transport)
        ok = client.emails.send(to="a@example.com", template="otp", variables={"code": "123456"})
        self.assertEqual(ok["id"], "em_retry")
        self.assertEqual(len(transport.requests), 2)

        with self.assertRaises(VinMailError) as ctx:
            client.emails.send(to="a@example.com", subject="Hi", text="Hi")
        self.assertEqual(ctx.exception.status, 422)
        self.assertEqual(ctx.exception.code, "domain_not_verified")

    def test_get_email_status(self) -> None:
        transport = FakeTransport(
            [(200, {}, b'{"id":"em_55","status":"delivered","events":[]}')]
        )
        client = VinMail("vm_live_py_key", transport=transport)
        details = client.emails.get("em_55")
        self.assertEqual(details["status"], "delivered")


if __name__ == "__main__":
    unittest.main()
