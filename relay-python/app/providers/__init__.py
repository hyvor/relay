"""
Provedores de e-mail - exports.
"""
from app.providers.base import EmailProvider, SendResult
from app.providers.sendgrid_provider import SendGridProvider
from app.providers.smtp_provider import SMTPProvider

__all__ = [
    "EmailProvider",
    "SendResult",
    "SMTPProvider",
    "SendGridProvider",
]
