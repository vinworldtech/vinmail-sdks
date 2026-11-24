from .attachments import (
    ALLOWED_BUSINESS_EXTENSIONS,
    MAX_ATTACHMENTS_PER_EMAIL,
    MAX_SINGLE_ATTACHMENT_BYTES,
    MAX_TOTAL_ATTACHMENTS_BYTES,
    Attachment,
    prepare_attachments,
)
from .client import DEFAULT_BASE_URL, SDK_VERSION, EmailsResource, VinMail
from .errors import VinMailError

__all__ = [
    "ALLOWED_BUSINESS_EXTENSIONS",
    "Attachment",
    "DEFAULT_BASE_URL",
    "EmailsResource",
    "MAX_ATTACHMENTS_PER_EMAIL",
    "MAX_SINGLE_ATTACHMENT_BYTES",
    "MAX_TOTAL_ATTACHMENTS_BYTES",
    "SDK_VERSION",
    "VinMail",
    "VinMailError",
    "prepare_attachments",
]
