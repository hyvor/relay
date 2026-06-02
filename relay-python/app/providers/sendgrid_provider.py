"""
Provedor SendGrid.
"""
import httpx
from structlog import get_logger

from app.core.config import get_settings
from app.domain.entities import Send, SendRecipient
from app.providers.base import EmailProvider, SendResult

logger = get_logger(__name__)


class SendGridProvider(EmailProvider):
    """Provedor de e-mail via SendGrid API."""

    name = "sendgrid"

    def __init__(self) -> None:
        self.settings = get_settings()
        self.base_url = "https://api.sendgrid.com/v3/mail/send"
        self.timeout = httpx.Timeout(30.0)

    @property
    def is_available(self) -> bool:
        """Verifica se SendGrid está configurado."""
        return (
            self.settings.sendgrid_enabled
            and self.settings.sendgrid_api_key is not None
        )

    async def send_email(
        self, send: Send, recipients: list[SendRecipient]
    ) -> SendResult:
        """Envia e-mail via SendGrid API."""
        if not self.settings.sendgrid_api_key:
            return SendResult(
                success=False,
                error_message="SendGrid API key not configured",
            )

        headers = {
            "Authorization": f"Bearer {self.settings.sendgrid_api_key}",
            "Content-Type": "application/json",
        }

        payload = self._build_payload(send, recipients)

        try:
            async with httpx.AsyncClient(timeout=self.timeout) as client:
                response = await client.post(
                    self.base_url,
                    headers=headers,
                    json=payload,
                )

                if response.status_code in (200, 202):
                    # SendGrid não retorna message_id no response
                    logger.info(
                        "Email enviado via SendGrid",
                        send_uuid=str(send.uuid),
                        status_code=response.status_code,
                    )
                    return SendResult(
                        success=True,
                        provider_message_id=send.message_id,
                        raw_response=response.text,
                    )
                elif response.status_code == 429:
                    logger.warning(
                        "Rate limit do SendGrid",
                        send_uuid=str(send.uuid),
                    )
                    return SendResult(
                        success=False,
                        error_message="Rate limit exceeded",
                        is_transient=True,
                        raw_response=response.text,
                    )
                elif response.status_code >= 500:
                    logger.error(
                        "Erro interno do SendGrid",
                        send_uuid=str(send.uuid),
                        status_code=response.status_code,
                    )
                    return SendResult(
                        success=False,
                        error_message=f"SendGrid error: {response.status_code}",
                        is_transient=True,
                        raw_response=response.text,
                    )
                else:
                    logger.warning(
                        "Erro no envio SendGrid",
                        send_uuid=str(send.uuid),
                        status_code=response.status_code,
                        body=response.text[:200],
                    )
                    return SendResult(
                        success=False,
                        error_message=f"SendGrid error: {response.status_code}",
                        is_transient=False,
                        raw_response=response.text,
                    )

        except httpx.TimeoutException as e:
            logger.error(
                "Timeout no SendGrid",
                send_uuid=str(send.uuid),
            )
            return SendResult(
                success=False,
                error_message=f"Timeout: {str(e)}",
                is_transient=True,
            )

        except httpx.ConnectError as e:
            logger.error(
                "Erro de conexão com SendGrid",
                send_uuid=str(send.uuid),
            )
            return SendResult(
                success=False,
                error_message=f"Connection error: {str(e)}",
                is_transient=True,
            )

        except Exception as e:
            logger.exception(
                "Erro inesperado no SendGrid",
                send_uuid=str(send.uuid),
            )
            return SendResult(
                success=False,
                error_message=f"Unexpected error: {str(e)}",
                is_transient=True,
            )

    def _build_payload(
        self, send: Send, recipients: list[SendRecipient]
    ) -> dict:
        """Constrói payload para API do SendGrid."""
        # Destinatários
        to_emails = [
            {"email": r.email, "name": r.name or ""} for r in recipients
        ]

        payload = {
            "from": {
                "email": send.message.from_address.email,
                "name": send.message.from_address.name or "",
            },
            "to": to_emails,
            "subject": send.message.subject or "",
        }

        # Reply-To
        if send.message.reply_to:
            payload["reply_to"] = {
                "email": send.message.reply_to.email,
                "name": send.message.reply_to.name or "",
            }

        # Conteúdo
        contents = []
        if send.message.text:
            contents.append({
                "type": "text/plain",
                "value": send.message.text,
            })
        if send.message.html:
            contents.append({
                "type": "text/html",
                "value": send.message.html,
            })
        payload["content"] = contents

        # Headers customizados
        if send.message.headers:
            payload["headers"] = send.message.headers

        # Anexos (SendGrid requer base64)
        if send.message.attachments:
            attachments = []
            for att in send.message.attachments:
                attachments.append({
                    "content": base64.b64encode(att.content).decode("utf-8"),
                    "filename": att.filename,
                    "type": att.content_type,
                    "disposition": att.disposition,
                })
            payload["attachments"] = attachments

        # Custom args para tracking
        if send.message.metadata:
            payload["custom_args"] = {
                str(k): str(v) for k, v in send.message.metadata.items()
            }

        return payload

    async def health_check(self) -> bool:
        """Verifica saúde da API do SendGrid."""
        if not self.settings.sendgrid_api_key:
            return False

        try:
            async with httpx.AsyncClient(timeout=10.0) as client:
                response = await client.get(
                    "https://api.sendgrid.com/v3/scopes",
                    headers={"Authorization": f"Bearer {self.settings.sendgrid_api_key}"},
                )
                return response.status_code == 200
        except Exception:
            logger.warning("Health check SendGrid falhou")
            return False
