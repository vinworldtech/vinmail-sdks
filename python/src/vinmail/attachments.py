from __future__ import annotations

import base64
import re
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Dict, Iterable, List, Mapping, Optional, Union

from .errors import VinMailError

MAX_ATTACHMENTS_PER_EMAIL = 5
MAX_SINGLE_ATTACHMENT_BYTES = 5 * 1024 * 1024
MAX_TOTAL_ATTACHMENTS_BYTES = int(7.5 * 1024 * 1024)

ALLOWED_BUSINESS_EXTENSIONS: Dict[str, str] = {
    ".pdf": "application/pdf",
    ".doc": "application/msword",
    ".docx": "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    ".xls": "application/vnd.ms-excel",
    ".xlsx": "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    ".csv": "text/csv",
    ".ppt": "application/vnd.ms-powerpoint",
    ".pptx": "application/vnd.openxmlformats-officedocument.presentationml.presentation",
    ".txt": "text/plain",
    ".rtf": "application/rtf",
    ".png": "image/png",
    ".jpg": "image/jpeg",
    ".jpeg": "image/jpeg",
    ".webp": "image/webp",
    ".gif": "image/gif",
}

_DATA_URL_RE = re.compile(r"^data:[^;]+;base64,(.+)$", re.IGNORECASE | re.DOTALL)


def _validate_filename_and_mime(filename: str) -> str:
    clean = filename.strip()
    if not clean:
        raise VinMailError(
            "Each attachment must include a valid filename.",
            status=422,
            code="validation_error",
        )

    dot = clean.rfind(".")
    ext = clean[dot:].lower() if 0 < dot < len(clean) - 1 else ""
    canonical = ALLOWED_BUSINESS_EXTENSIONS.get(ext)
    if canonical is None:
        raise VinMailError(
            f'File "{clean}" is not permitted. Only business documents and images are allowed '
            "(PDF, DOC/DOCX, XLS/XLSX, CSV, PPT/PPTX, TXT, RTF, PNG, JPG, WEBP, GIF).",
            status=422,
            code="validation_error",
        )
    return canonical


def _validate_single_size(filename: str, size_bytes: int) -> None:
    if size_bytes <= 0:
        raise VinMailError(
            f'Attachment "{filename}" cannot be empty.',
            status=422,
            code="validation_error",
        )
    if size_bytes > MAX_SINGLE_ATTACHMENT_BYTES:
        raise VinMailError(
            f'Attachment "{filename}" exceeds the 5 MB per-file size limit.',
            status=422,
            code="validation_error",
        )


@dataclass(frozen=True)
class Attachment:
    """Validated business file attachment ready for VinMail."""

    filename: str
    base64_content: str
    content_type: str
    size_bytes: int

    @classmethod
    def from_path(
        cls,
        file_path: Union[str, Path],
        filename: Optional[str] = None,
    ) -> "Attachment":
        path_obj = Path(file_path)
        if not path_obj.is_file():
            raise VinMailError(
                f"Attachment file not found: {path_obj}",
                status=422,
                code="validation_error",
            )
        raw = path_obj.read_bytes()
        return cls.from_bytes(filename or path_obj.name, raw)

    @classmethod
    def from_bytes(cls, filename: str, data: bytes) -> "Attachment":
        clean_name = filename.strip()
        mime = _validate_filename_and_mime(clean_name)
        _validate_single_size(clean_name, len(data))
        encoded = base64.b64encode(data).decode("ascii")
        return cls(
            filename=clean_name,
            base64_content=encoded,
            content_type=mime,
            size_bytes=len(data),
        )

    @classmethod
    def from_base64(cls, filename: str, content_b64: str) -> "Attachment":
        clean_name = filename.strip()
        mime = _validate_filename_and_mime(clean_name)
        trimmed = content_b64.strip()
        match = _DATA_URL_RE.match(trimmed)
        clean_b64 = re.sub(r"\s+", "", match.group(1) if match else trimmed)
        try:
            decoded = base64.b64decode(clean_b64, validate=True)
        except Exception as exc:
            raise VinMailError(
                f'Attachment "{clean_name}" contains invalid base64 content.',
                status=422,
                code="validation_error",
            ) from exc
        _validate_single_size(clean_name, len(decoded))
        return cls(
            filename=clean_name,
            base64_content=clean_b64,
            content_type=mime,
            size_bytes=len(decoded),
        )

    def to_payload(self) -> Dict[str, str]:
        return {
            "filename": self.filename,
            "content": self.base64_content,
            "contentType": self.content_type,
        }


AttachmentLike = Union[Attachment, Mapping[str, Any]]


def prepare_attachments(items: Iterable[AttachmentLike]) -> List[Dict[str, str]]:
    item_list = list(items)
    if not item_list:
        return []
    if len(item_list) > MAX_ATTACHMENTS_PER_EMAIL:
        raise VinMailError(
            f"At most {MAX_ATTACHMENTS_PER_EMAIL} file attachments are allowed per email.",
            status=422,
            code="validation_error",
        )

    total_bytes = 0
    payload: List[Dict[str, str]] = []

    for item in item_list:
        if isinstance(item, Attachment):
            att = item
        elif isinstance(item, Mapping):
            if "path" in item:
                att = Attachment.from_path(item["path"], filename=item.get("filename"))
            elif "filename" in item and "content" in item:
                content = item["content"]
                if isinstance(content, (bytes, bytearray)):
                    att = Attachment.from_bytes(str(item["filename"]), bytes(content))
                elif isinstance(content, str):
                    att = Attachment.from_base64(str(item["filename"]), content)
                else:
                    raise VinMailError(
                        "Attachment content must be bytes or a base64 string.",
                        status=422,
                        code="validation_error",
                    )
            else:
                raise VinMailError(
                    'Each attachment dict must include "path" or ("filename" and "content").',
                    status=422,
                    code="validation_error",
                )
        else:
            raise VinMailError(
                "Invalid attachment item.",
                status=422,
                code="validation_error",
            )

        total_bytes += att.size_bytes
        if total_bytes > MAX_TOTAL_ATTACHMENTS_BYTES:
            raise VinMailError(
                "Combined attachment size exceeds the 7.5 MB limit per email.",
                status=422,
                code="validation_error",
            )
        payload.append(att.to_payload())

    return payload
