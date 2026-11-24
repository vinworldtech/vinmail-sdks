from __future__ import annotations

import json
import socket
import time
import urllib.error
import urllib.parse
import urllib.request
from typing import Any, Callable, Dict, Iterable, Mapping, Optional, Sequence, Tuple, Union

from .attachments import AttachmentLike, prepare_attachments
from .errors import VinMailError

DEFAULT_BASE_URL = "https://vinmail-api.vinworlddev.com"
SDK_VERSION = "0.1.0"

TransportCallable = Callable[
    [str, str, Dict[str, str], Optional[bytes], float],
    Tuple[int, Dict[str, str], bytes],
]


def _default_transport(
    method: str,
    url: str,
    headers: Dict[str, str],
    body: Optional[bytes],
    timeout: float,
) -> Tuple[int, Dict[str, str], bytes]:
    req = urllib.request.Request(url=url, data=body, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            resp_headers = {k.lower(): v for k, v in resp.headers.items()}
            return int(resp.status), resp_headers, resp.read()
    except urllib.error.HTTPError as err:
        resp_headers = {k.lower(): v for k, v in err.headers.items()} if err.headers else {}
        return int(err.code), resp_headers, err.read()
    except urllib.error.URLError as err:
        raise VinMailError(
            f"Network error contacting VinMail: {err.reason}",
            status=0,
            code="network_error",
        ) from err
    except (TimeoutError, socket.timeout) as err:
        raise VinMailError(
            f"VinMail request timed out after {timeout}s.",
            status=0,
            code="timeout",
        ) from err


class EmailsResource:
    def __init__(self, client: "VinMail") -> None:
        self._client = client

    def send(
        self,
        *,
        to: Union[str, Sequence[str]],
        from_: Optional[str] = None,
        reply_to: Optional[str] = None,
        subject: Optional[str] = None,
        html: Optional[str] = None,
        text: Optional[str] = None,
        template: Optional[str] = None,
        variables: Optional[Mapping[str, Any]] = None,
        tags: Optional[Mapping[str, str]] = None,
        attachments: Optional[Iterable[AttachmentLike]] = None,
        idempotency_key: Optional[str] = None,
    ) -> Dict[str, Any]:
        """Queue a transactional email for immediate delivery (`POST /v1/emails`)."""
        payload: Dict[str, Any] = {
            "to": to if isinstance(to, str) else list(to),
        }
        if from_ is not None:
            payload["from"] = from_
        if reply_to is not None:
            payload["replyTo"] = reply_to
        if subject is not None:
            payload["subject"] = subject
        if html is not None:
            payload["html"] = html
        if text is not None:
            payload["text"] = text
        if template is not None:
            payload["template"] = template
        if variables is not None:
            payload["variables"] = dict(variables)
        if tags is not None:
            payload["tags"] = dict(tags)
        if attachments is not None:
            prepared = prepare_attachments(attachments)
            if prepared:
                payload["attachments"] = prepared

        extra_headers: Dict[str, str] = {}
        if idempotency_key and idempotency_key.strip():
            extra_headers["Idempotency-Key"] = idempotency_key.strip()

        return self._client.request("POST", "/v1/emails", payload=payload, extra_headers=extra_headers)

    def get(self, email_id: str) -> Dict[str, Any]:
        """Fetch delivery status and event timeline (`GET /v1/emails/:id`)."""
        clean_id = email_id.strip()
        if not clean_id:
            raise VinMailError("Email id is required.", status=422, code="validation_error")
        return self._client.request("GET", f"/v1/emails/{urllib.parse.quote(clean_id, safe='')}")


class VinMail:
    """Official synchronous VinMail API client."""

    def __init__(
        self,
        api_key: str,
        *,
        base_url: str = DEFAULT_BASE_URL,
        timeout: float = 15.0,
        max_retries: int = 2,
        transport: Optional[TransportCallable] = None,
    ) -> None:
        trimmed = api_key.strip()
        if not trimmed:
            raise VinMailError(
                "A valid VinMail API key (vm_live_...) is required.",
                status=401,
                code="missing_api_key",
            )
        self._api_key = trimmed
        self._base_url = base_url.rstrip("/")
        self._timeout = max(1.0, float(timeout))
        self._max_retries = max(0, int(max_retries))
        self._transport = transport or _default_transport
        self.emails = EmailsResource(self)

    def request(
        self,
        method: str,
        path: str,
        *,
        payload: Optional[Dict[str, Any]] = None,
        extra_headers: Optional[Dict[str, str]] = None,
    ) -> Dict[str, Any]:
        url = f"{self._base_url}{path}"
        headers: Dict[str, str] = {
            "Authorization": f"Bearer {self._api_key}",
            "Accept": "application/json",
            "User-Agent": f"vinmail-python/{SDK_VERSION}",
        }
        if extra_headers:
            headers.update(extra_headers)

        encoded_body: Optional[bytes] = None
        if payload is not None:
            headers["Content-Type"] = "application/json"
            encoded_body = json.dumps(payload).encode("utf-8")

        attempt = 0
        while True:
            try:
                status, resp_headers, raw_body = self._transport(
                    method, url, headers, encoded_body, self._timeout
                )
            except VinMailError as exc:
                if attempt < self._max_retries and exc.status == 0:
                    attempt += 1
                    time.sleep(min(0.3 * (2 ** (attempt - 1)), 3.0))
                    continue
                raise

            decoded: Optional[Dict[str, Any]] = None
            text = raw_body.decode("utf-8", errors="replace") if raw_body else ""
            if text:
                try:
                    parsed = json.loads(text)
                    if isinstance(parsed, dict):
                        decoded = parsed
                except json.JSONDecodeError:
                    decoded = None

            if 200 <= status < 300:
                return decoded or {}

            retry_after_raw = resp_headers.get("retry-after")
            retry_after: Optional[int] = None
            if retry_after_raw and retry_after_raw.isdigit():
                retry_after = int(retry_after_raw)

            if attempt < self._max_retries and (status == 429 or status >= 500):
                attempt += 1
                delay = (
                    min(float(retry_after), 5.0)
                    if retry_after is not None
                    else min(0.3 * (2 ** (attempt - 1)), 3.0)
                )
                time.sleep(delay)
                continue

            err_obj = decoded.get("error") if decoded else None
            if isinstance(err_obj, dict):
                code = str(err_obj.get("code") or f"http_{status}")
                message = str(err_obj.get("message") or f"VinMail HTTP {status}")
            else:
                code = f"http_{status}"
                message = text[:300] if text else f"VinMail request failed with HTTP {status}."

            raise VinMailError(message, status=status, code=code, retry_after=retry_after)
