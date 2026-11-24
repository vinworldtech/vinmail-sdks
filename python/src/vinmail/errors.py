from __future__ import annotations

from typing import Optional


class VinMailError(Exception):
    """Raised when a VinMail API request or client-side validation fails."""

    def __init__(
        self,
        message: str,
        status: int = 0,
        code: str = "vinmail_error",
        retry_after: Optional[int] = None,
    ) -> None:
        super().__init__(message)
        self.message = message
        self.status = status
        self.code = code
        self.retry_after = retry_after

    def __repr__(self) -> str:
        return (
            f"VinMailError(status={self.status!r}, code={self.code!r}, "
            f"message={self.message!r})"
        )
