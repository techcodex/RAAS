import hashlib
import hmac
import time

from fastapi import Header, HTTPException, Request, status

from app.config import get_settings

_MAX_SKEW_SECONDS = 300


def _sign(secret: str, timestamp: str, body: bytes) -> str:
    mac = hmac.new(secret.encode(), f"{timestamp}.".encode() + body, hashlib.sha256)
    return mac.hexdigest()


async def verify_signature(
    request: Request,
    x_rag_timestamp: str = Header(...),
    x_rag_signature: str = Header(...),
) -> None:
    """Validate the HMAC signature Laravel attaches to internal calls."""
    settings = get_settings()
    body = await request.body()

    try:
        skew = abs(time.time() - float(x_rag_timestamp))
    except ValueError as exc:
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Invalid timestamp") from exc

    if skew > _MAX_SKEW_SECONDS:
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Stale request")

    expected = _sign(settings.internal_secret, x_rag_timestamp, body)
    if not hmac.compare_digest(expected, x_rag_signature):
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Bad signature")
