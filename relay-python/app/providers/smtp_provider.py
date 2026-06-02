"""
Provedor SMTP direto.
"""
import base64
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from email.mime.base import MIMEBase
from email import encoders

import aiosmtplib
from structlog import get_logger

from app.core.config import get_settings
from app.domain.entities import Send, SendRecipient
from app.domain.enums import BounceType
from app.providers.base import EmailProvider, SendResult

logger = get_logger(__name__)


class SMTPProvider(EmailProvider):
    """Provedor de e-mail via SMTP direto."""

    name = "smtp"

    def __init__(self) -> None:
        self.settings = get_settings()

    @property
    def is_available(self) -> bool:
        """Verifica se SMTP está configurado."""
        return self.settings.smtp_host is not None

    async def send_email(
        self, send: Send, recipients: list[SendRecipient]
    ) -> SendResult:
        """Envia e-mail via SMTP."""
        if not self.settings.smtp_host:
            return SendResult(
                success=False,
                error_message="SMTP host not configured",
            )

        try:
            # Construir mensagem MIME
            msg = self._build_message(send, recipients)

            # Obter lista de destinatários SMTP
            smtp_recipients = [r.email for r in recipients]

            # Conectar e enviar
            await aiosmtplib.send(
                msg,
                hostname=self.settings.smtp_host,
                port=self.settings.smtp_port,
                username=self.settings.smtp_user,
                password=self.settings.smtp_pass,
                start_tls=self.settings.smtp_tls,
                timeout=self.settings.smtp_timeout,
            )

            logger.info(
                "Email enviado via SMTP",
                send_uuid=str(send.uuid),
                recipients=len(smtp_recipients),
            )

            return SendResult(
                success=True,
                provider_message_id=send.message_id,
                raw_response="250 OK",
            )

        except aiosmtplib.SMTPRecipientsRefused as e:
            logger.warning(
                "Destinatários recusados pelo SMTP",
                send_uuid=str(send.uuid),
                error=str(e),
            )
            return SendResult(
                success=False,
                error_message=f"Recipients refused: {str(e)}",
                is_transient=False,
            )

        except aiosmtplib.SMTPServerDisconnected as e:
            logger.warning(
                "Servidor SMTP desconectado",
                send_uuid=str(send.uuid),
                error=str(e),
            )
            return SendResult(
                success=False,
                error_message=f"Server disconnected: {str(e)}",
                is_transient=True,
            )

        except aiosmtplib.SMTPConnectionError as e:
            logger.error(
                "Erro de conexão SMTP",
                send_uuid=str(send.uuid),
                error=str(e),
            )
            return SendResult(
                success=False,
                error_message=f"Connection error: {str(e)}",
                is_transient=True,
            )

        except Exception as e:
            logger.exception(
                "Erro inesperado no envio SMTP",
                send_uuid=str(send.uuid),
            )
            return SendResult(
                success=False,
                error_message=f"Unexpected error: {str(e)}",
                is_transient=True,
            )

    def _build_message(
        self, send: Send, recipients: list[SendRecipient]
    ) -> MIMEMultipart:
        """Constrói mensagem MIME."""
        msg = MIMEMultipart("alternative")

        # Headers básicos
        msg["From"] = str(send.message.from_address)
        msg["To"] = ", ".join([r.email for r in recipients])
        msg["Subject"] = send.message.subject or ""
        msg["Message-ID"] = send.message_id

        # Reply-To
        if send.message.reply_to:
            msg["Reply-To"] = str(send.message.reply_to)

        # Headers customizados
        for key, value in send.message.headers.items():
            msg[key] = value

        # Corpo do e-mail
        if send.message.text:
            msg.attach(MIMEText(send.message.text, "plain", "utf-8"))
        if send.message.html:
            msg.attach(MIMEText(send.message.html, "html", "utf-8"))

        # Anexos
        for attachment in send.message.attachments:
            part = MIMEBase(*attachment.content_type.split("/"))
            part.set_payload(attachment.content)
            encoders.encode_base64(part)
            part.add_header(
                "Content-Disposition",
                f'{attachment.disposition}; filename="{attachment.filename}"',
            )
            msg.attach(part)

        return msg

    async def health_check(self) -> bool:
        """Verifica conexão com servidor SMTP."""
        if not self.settings.smtp_host:
            return False

        try:
            await aiosmtplib.ehlo(
                hostname=self.settings.smtp_host,
                port=self.settings.smtp_port,
                timeout=5,
            )
            return True
        except Exception:
            logger.warning("Health check SMTP falhou")
            return False
