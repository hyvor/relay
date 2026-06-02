"""
Serviço de aplicação para envio de e-mails.
"""
import base64
from datetime import datetime
from typing import Any
from uuid import UUID, uuid4

from structlog import get_logger

from app.core.config import get_settings
from app.domain.entities import (
    Attachment,
    DeliveryPolicy,
    EmailAddress,
    Message,
    Send,
    SendRecipient,
    TemplateConfig,
)
from app.domain.enums import BounceType, QueueType, RecipientStatus, SendStatus
from app.providers.provider_factory import provider_router
from app.repositories.repositories import (
    DomainRepository,
    EventRepository,
    RecipientRepository,
    SendRepository,
    SuppressionRepository,
)

logger = get_logger(__name__)


class SendService:
    """Serviço para criação e processamento de envios."""

    def __init__(
        self,
        send_repo: SendRepository,
        recipient_repo: RecipientRepository,
        event_repo: EventRepository,
        domain_repo: DomainRepository,
        suppression_repo: SuppressionRepository,
    ) -> None:
        self.send_repo = send_repo
        self.recipient_repo = recipient_repo
        self.event_repo = event_repo
        self.domain_repo = domain_repo
        self.suppression_repo = suppression_repo
        self.settings = get_settings()

    async def create_send(
        self,
        project_id: int,
        message_input: Any,
        delivery_policy: DeliveryPolicy,
        idempotency_key: str | None = None,
        tenant_id: str | None = None,
    ) -> Send:
        """Cria um novo envio de e-mail."""
        # Verificar idempotência
        if idempotency_key:
            existing = await self.send_repo.get_by_idempotency_key(
                project_id, idempotency_key
            )
            if existing:
                logger.info(
                    "Send duplicado detectado via idempotency_key",
                    key=idempotency_key,
                    existing_id=existing.id,
                )
                return existing

        # Validar domínio
        from_email = message_input.from_address.email
        domain_name = from_email.split("@")[-1]
        domain = await self.domain_repo.get_by_project_and_name(
            project_id, domain_name
        )
        if not domain:
            raise ValueError(f"Domain {domain_name} not registered for project")
        if not domain.status.can_send_emails():
            raise ValueError(f"Domain {domain_name} cannot send emails")

        # Verificar supressões
        all_recipients = (
            message_input.to + message_input.cc + message_input.bcc
        )
        for rcpt in all_recipients:
            if await self.suppression_repo.is_suppressed(rcpt.email):
                logger.warning(
                    "Recipient suprimido",
                    email=rcpt.email,
                )
                raise ValueError(f"Recipient {rcpt.email} is suppressed")

        # Construir entidade Send
        message = self._build_message(message_input)
        queue_type = QueueType.TRANSACTIONAL  # Simplificado

        send = Send(
            project_id=project_id,
            domain_id=domain.id,
            queue_type=queue_type,
            message=message,
            delivery_policy=delivery_policy,
            idempotency_key=idempotency_key,
            tenant_id=tenant_id,
            uuid=uuid4(),
            message_id=self._generate_message_id(domain_name),
            status=SendStatus.QUEUED,
        )

        # Validar tamanho
        size = self._estimate_size(send)
        if size > self.settings.max_email_size_bytes:
            raise ValueError("Email size exceeds limit")
        send.size_bytes = size

        # Persistir
        send_data = {
            "uuid": str(send.uuid),
            "project_id": send.project_id,
            "domain_id": send.domain_id,
            "queue_type": send.queue_type,
            "idempotency_key": send.idempotency_key,
            "tenant_id": send.tenant_id,
            "from_email": str(send.message.from_address.email),
            "from_name": send.message.from_address.name,
            "subject": send.message.subject,
            "message_id": send.message_id,
            "status": send.status,
            "size_bytes": send.size_bytes,
            "tags": send.message.tags,
            "categories": send.message.categories,
            "metadata_": send.message.metadata,
            "scheduled_for": send.message.schedule_at,
        }
        created_send = await self.send_repo.create_send(send_data)

        # Criar recipients
        recipients_data = self._build_recipients_data(created_send.id, message_input)
        await self.recipient_repo.create_recipients(created_send.id, recipients_data)

        # Evento inicial
        await self.event_repo.create_event({
            "send_id": created_send.id,
            "event_type": "send.queued",
            "timestamp": datetime.utcnow(),
        })

        logger.info(
            "Send criado com sucesso",
            send_id=created_send.id,
            uuid=str(created_send.uuid),
        )

        return created_send

    def _build_message(self, message_input: Any) -> Message:
        """Constrói entidade Message a partir do input."""
        attachments = []
        for att_input in getattr(message_input, "attachments", []):
            content = base64.b64decode(att_input.content_b64)
            attachments.append(Attachment(
                filename=att_input.filename,
                content_type=att_input.content_type,
                content=content,
                disposition=att_input.disposition,
            ))

        template = None
        if message_input.template:
            template = TemplateConfig(
                template_id=message_input.template.template_id,
                variables=message_input.template.variables,
            )

        return Message(
            from_address=EmailAddress(
                email=message_input.from_address.email,
                name=message_input.from_address.name,
            ),
            to=[
                EmailAddress(email=r.email, name=r.name)
                for r in message_input.to
            ],
            cc=[
                EmailAddress(email=r.email, name=r.name)
                for r in getattr(message_input, "cc", [])
            ],
            bcc=[
                EmailAddress(email=r.email, name=r.name)
                for r in getattr(message_input, "bcc", [])
            ],
            reply_to=EmailAddress(
                email=message_input.reply_to.email,
                name=message_input.reply_to.name,
            ) if message_input.reply_to else None,
            subject=message_input.subject,
            text=message_input.text,
            html=message_input.html,
            headers=message_input.headers,
            tags=message_input.tags,
            categories=message_input.categories,
            metadata=message_input.metadata,
            attachments=attachments,
            template=template,
            schedule_at=message_input.schedule_at,
            sandbox_mode=message_input.sandbox_mode,
            track_opens=message_input.track_opens,
            track_clicks=message_input.track_clicks,
        )

    def _build_recipients_data(
        self, send_id: int, message_input: Any
    ) -> list[dict[str, Any]]:
        """Constrói dados de destinatários."""
        recipients = []

        for r in message_input.to:
            recipients.append({
                "send_id": send_id,
                "email": r.email,
                "name": r.name,
                "recipient_type": "to",
                "status": RecipientStatus.PENDING,
            })

        for r in getattr(message_input, "cc", []):
            recipients.append({
                "send_id": send_id,
                "email": r.email,
                "name": r.name,
                "recipient_type": "cc",
                "status": RecipientStatus.PENDING,
            })

        for r in getattr(message_input, "bcc", []):
            recipients.append({
                "send_id": send_id,
                "email": r.email,
                "name": r.name,
                "recipient_type": "bcc",
                "status": RecipientStatus.PENDING,
            })

        return recipients

    def _generate_message_id(self, domain_name: str) -> str:
        """Gera Message-ID único."""
        import socket
        hostname = socket.gethostname()
        timestamp = datetime.utcnow().strftime("%Y%m%d%H%M%S.%f")
        unique_id = uuid4().hex[:8]
        return f"<{timestamp}.{unique_id}@{domain_name}>"

    def _estimate_size(self, send: Send) -> int:
        """Estima tamanho do e-mail em bytes."""
        size = 0
        if send.message.subject:
            size += len(send.message.subject.encode("utf-8"))
        if send.message.text:
            size += len(send.message.text.encode("utf-8"))
        if send.message.html:
            size += len(send.message.html.encode("utf-8"))
        for att in send.message.attachments:
            size += len(att.content)
        return size
