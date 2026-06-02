"""
Fábrica de provedores com failover.
"""
from typing import Any

from structlog import get_logger

from app.core.config import get_settings
from app.domain.entities import Send, SendRecipient
from app.providers.base import EmailProvider, SendResult
from app.providers.sendgrid_provider import SendGridProvider
from app.providers.smtp_provider import SMTPProvider

logger = get_logger(__name__)


class ProviderRouter:
    """Roteador de provedores com failover."""

    def __init__(self) -> None:
        self.settings = get_settings()
        self._providers: dict[str, EmailProvider] = {}
        self._init_providers()

    def _init_providers(self) -> None:
        """Inicializa provedores disponíveis."""
        if SMTPProvider().is_available:
            self._providers["smtp"] = SMTPProvider()
        if SendGridProvider().is_available:
            self._providers["sendgrid"] = SendGridProvider()
        # Adicionar SES, Mailgun conforme necessário

    async def send_with_failover(
        self, send: Send, recipients: list[SendRecipient]
    ) -> SendResult:
        """Envia e-mail com failover entre provedores."""
        providers_to_try = self._get_provider_order(send.delivery_policy.provider_preference)

        last_error: str | None = None
        for provider_name in providers_to_try:
            provider = self._providers.get(provider_name)
            if not provider:
                logger.warning(f"Provedor {provider_name} não disponível")
                continue

            logger.info(
                f"Tentando enviar via {provider_name}",
                send_uuid=str(send.uuid),
            )

            result = await provider.send_email(send, recipients)

            if result.success:
                logger.info(
                    f"Email enviado com sucesso via {provider_name}",
                    send_uuid=str(send.uuid),
                )
                return result

            last_error = result.error_message
            if not result.is_transient:
                # Erro permanente, não tentar failover
                logger.warning(
                    f"Erro permanente no {provider_name}, sem failover",
                    send_uuid=str(send.uuid),
                    error=last_error,
                )
                return result

            logger.warning(
                f"Falha no {provider_name}, tentando failover",
                send_uuid=str(send.uuid),
                error=last_error,
            )

        return SendResult(
            success=False,
            error_message=f"All providers failed. Last error: {last_error}",
            is_transient=False,
        )

    def _get_provider_order(self, preference: list[str]) -> list[str]:
        """Determina ordem de tentativas dos provedores."""
        if preference:
            return preference
        return self.settings.providers_enabled or ["smtp"]

    async def health_check_all(self) -> dict[str, bool]:
        """Verifica saúde de todos os provedores."""
        results = {}
        for name, provider in self._providers.items():
            results[name] = await provider.health_check()
        return results


# Singleton
provider_router = ProviderRouter()
