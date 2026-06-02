"""
Entidades de domínio do sistema de e-mail.
"""
from dataclasses import dataclass, field
from datetime import datetime
from typing import Any
from uuid import UUID, uuid4

from app.domain.enums import (
    BounceType,
    DomainStatus,
    EventType,
    Priority,
    QueueType,
    RecipientStatus,
    SendStatus,
    SuppressionReason,
)


@dataclass
class EmailAddress:
    """Representa um endereço de e-mail com nome."""

    email: str
    name: str | None = None

    def __str__(self) -> str:
        if self.name:
            return f"{self.name} <{self.email}>"
        return self.email


@dataclass
class Attachment:
    """Anexo de e-mail."""

    filename: str
    content_type: str
    content: bytes
    disposition: str = "attachment"  # ou "inline"


@dataclass
class TemplateConfig:
    """Configuração de template dinâmico."""

    template_id: str
    variables: dict[str, Any] = field(default_factory=dict)


@dataclass
class DeliveryPolicy:
    """Política de entrega de e-mail."""

    provider_preference: list[str] = field(default_factory=list)
    fallback_enabled: bool = True
    max_provider_attempts: int = 2
    priority: Priority = Priority.NORMAL
    region: str | None = None


@dataclass
class Message:
    """Mensagem de e-mail a ser enviada."""

    from_address: EmailAddress
    to: list[EmailAddress]
    subject: str | None = None
    text: str | None = None
    html: str | None = None
    reply_to: EmailAddress | None = None
    cc: list[EmailAddress] = field(default_factory=list)
    bcc: list[EmailAddress] = field(default_factory=list)
    headers: dict[str, str] = field(default_factory=dict)
    tags: list[str] = field(default_factory=list)
    categories: list[str] = field(default_factory=list)
    metadata: dict[str, Any] = field(default_factory=dict)
    attachments: list[Attachment] = field(default_factory=list)
    template: TemplateConfig | None = None
    schedule_at: datetime | None = None
    sandbox_mode: bool = False
    track_opens: bool = False
    track_clicks: bool = False


@dataclass
class Send:
    """Entidade principal de envio de e-mail."""

    project_id: int
    domain_id: int
    queue_type: QueueType
    message: Message
    delivery_policy: DeliveryPolicy
    idempotency_key: str | None = None
    tenant_id: str | None = None
    uuid: UUID = field(default_factory=uuid4)
    message_id: str | None = None
    status: SendStatus = SendStatus.QUEUED
    created_at: datetime = field(default_factory=datetime.utcnow)
    updated_at: datetime = field(default_factory=datetime.utcnow)
    scheduled_for: datetime | None = None
    submitted_at: datetime | None = None
    delivered_at: datetime | None = None
    provider_name: str | None = None
    provider_response: str | None = None
    attempts_count: int = 0
    raw_email: bytes | None = None
    size_bytes: int = 0

    def accept(self) -> None:
        """Marca o send como aceito."""
        self.status = SendStatus.ACCEPTED

    def submit_to_provider(self, provider_name: str) -> None:
        """Marca como submetido ao provider."""
        self.status = SendStatus.PROVIDER_SUBMITTED
        self.provider_name = provider_name
        self.submitted_at = datetime.utcnow()
        self.attempts_count += 1

    def mark_delivered(self) -> None:
        """Marca como entregue."""
        self.status = SendStatus.DELIVERED
        self.delivered_at = datetime.utcnow()

    def mark_failed(self) -> None:
        """Marca como falha terminal."""
        self.status = SendStatus.FAILED_TERMINAL

    def can_retry(self, max_attempts: int) -> bool:
        """Verifica se pode ser retentado."""
        return self.attempts_count < max_attempts


@dataclass
class SendRecipient:
    """Destinatário individual de um envio."""

    send_id: int
    email: str
    name: str | None = None
    recipient_type: str = "to"  # to, cc, bcc
    status: RecipientStatus = RecipientStatus.PENDING
    message_id: str | None = None
    provider_message_id: str | None = None
    sent_at: datetime | None = None
    delivered_at: datetime | None = None
    bounced_at: datetime | None = None
    bounce_type: BounceType | None = None
    bounce_reason: str | None = None
    complained_at: datetime | None = None
    suppressed_at: datetime | None = None
    error_message: str | None = None
    attempts_count: int = 0
    last_attempt_at: datetime | None = None

    def mark_accepted(self, provider_message_id: str) -> None:
        """Marca como aceito pelo provider."""
        self.status = RecipientStatus.ACCEPTED
        self.provider_message_id = provider_message_id
        self.sent_at = datetime.utcnow()

    def mark_deferred(self, error_message: str) -> None:
        """Marca como adiado (greylisting ou erro temporário)."""
        self.status = RecipientStatus.DEFERRED
        self.error_message = error_message
        self.last_attempt_at = datetime.utcnow()
        self.attempts_count += 1

    def mark_bounced(
        self, bounce_type: BounceType, reason: str | None = None
    ) -> None:
        """Marca como bounced."""
        self.status = RecipientStatus.BOUNCED_HARD if bounce_type == BounceType.HARD else RecipientStatus.BOUNCED_SOFT
        self.bounce_type = bounce_type
        self.bounce_reason = reason
        self.bounced_at = datetime.utcnow()

    def mark_complained(self) -> None:
        """Marca como spam complaint."""
        self.status = RecipientStatus.COMPLAINED
        self.complained_at = datetime.utcnow()

    def mark_suppressed(self) -> None:
        """Marca como suprimido."""
        self.status = RecipientStatus.SUPPRESSED
        self.suppressed_at = datetime.utcnow()


@dataclass
class Event:
    """Evento de webhook normalizado."""

    send_uuid: UUID
    event_type: EventType
    recipient_email: str | None = None
    timestamp: datetime = field(default_factory=datetime.utcnow)
    provider: str | None = None
    provider_event_id: str | None = None
    payload: dict[str, Any] = field(default_factory=dict)
    metadata: dict[str, Any] = field(default_factory=dict)
    processed: bool = False
    created_at: datetime = field(default_factory=datetime.utcnow)


@dataclass
class Domain:
    """Domínio remetente."""

    organization_id: int
    name: str
    status: DomainStatus = DomainStatus.PENDING
    dkim_selector: str | None = None
    dkim_public_key: str | None = None
    spf_record: str | None = None
    dmarc_record: str | None = None
    verified_at: datetime | None = None
    disabled_at: datetime | None = None
    created_at: datetime = field(default_factory=datetime.utcnow)
    updated_at: datetime = field(default_factory=datetime.utcnow)

    def verify(self) -> None:
        """Marca domínio como verificado."""
        self.status = DomainStatus.VERIFIED
        self.verified_at = datetime.utcnow()

    def disable(self) -> None:
        """Desabilita o domínio."""
        self.status = DomainStatus.DISABLED
        self.disabled_at = datetime.utcnow()


@dataclass
class Suppression:
    """Registro de supressão de e-mail."""

    email: str
    reason: SuppressionReason
    source: str = "automatic"  # automatic, manual, api
    expires_at: datetime | None = None
    metadata: dict[str, Any] = field(default_factory=dict)
    created_at: datetime = field(default_factory=datetime.utcnow)

    def is_active(self) -> bool:
        """Verifica se a supressão está ativa."""
        if self.expires_at is None:
            return True
        return datetime.utcnow() < self.expires_at


@dataclass
class Organization:
    """Organização (tenant de mais alto nível)."""

    name: str
    external_id: str | None = None
    settings: dict[str, Any] = field(default_factory=dict)
    active: bool = True
    created_at: datetime = field(default_factory=datetime.utcnow)
    updated_at: datetime = field(default_factory=datetime.utcnow)


@dataclass
class Project:
    """Projeto dentro de uma organização."""

    organization_id: int
    name: str
    external_id: str | None = None
    send_type: QueueType = QueueType.TRANSACTIONAL
    settings: dict[str, Any] = field(default_factory=dict)
    active: bool = True
    created_at: datetime = field(default_factory=datetime.utcnow)
    updated_at: datetime = field(default_factory=datetime.utcnow)


@dataclass
class ApiKey:
    """Chave de API para autenticação."""

    project_id: int
    key_hash: str
    name: str | None = None
    scopes: list[str] = field(default_factory=list)
    active: bool = True
    expires_at: datetime | None = None
    last_used_at: datetime | None = None
    created_at: datetime = field(default_factory=datetime.utcnow)

    def has_scope(self, scope: str) -> bool:
        """Verifica se a chave tem um escopo específico."""
        return "*" in self.scopes or scope in self.scopes
