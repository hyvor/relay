"""
Interface base para provedores de e-mail.
"""
from abc import ABC, abstractmethod
from dataclasses import dataclass
from typing import Any

from app.domain.entities import Send, SendRecipient


@dataclass
class SendResult:
    """Resultado do envio."""

    success: bool
    provider_message_id: str | None = None
    error_message: str | None = None
    is_transient: bool = False  # True se pode ser retentado
    raw_response: Any = None


class EmailProvider(ABC):
    """Interface base para provedores de e-mail."""

    name: str = "base"

    @abstractmethod
    async def send_email(self, send: Send, recipients: list[SendRecipient]) -> SendResult:
        """
        Envia e-mail para destinatários.

        Args:
            send: Entidade Send com dados do e-mail
            recipients: Lista de destinatários

        Returns:
            SendResult com resultado da operação
        """
        pass

    @abstractmethod
    async def health_check(self) -> bool:
        """Verifica saúde do provedor."""
        pass

    @property
    @abstractmethod
    def is_available(self) -> bool:
        """Verifica se provedor está configurado e disponível."""
        pass
