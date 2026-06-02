"""
Schemas Pydantic para validação de entrada/saída da API.
"""
from datetime import datetime
from typing import Any

from pydantic import BaseModel, ConfigDict, EmailStr, Field, field_validator

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


# --- Schemas auxiliares ---


class EmailAddressInput(BaseModel):
    """Endereço de e-mail com nome."""

    email: EmailStr
    name: str | None = None


class AttachmentInput(BaseModel):
    """Anexo de e-mail em base64."""

    filename: str
    content_type: str
    content_b64: str = Field(..., description="Conteúdo do anexo em base64")
    disposition: str = "attachment"


class TemplateConfigInput(BaseModel):
    """Configuração de template."""

    template_id: str
    variables: dict[str, Any] = Field(default_factory=dict)


class DeliveryPolicyInput(BaseModel):
    """Política de entrega."""

    provider_preference: list[str] = Field(default_factory=list)
    fallback_enabled: bool = True
    max_provider_attempts: int = 2
    priority: Priority = Priority.NORMAL
    region: str | None = None


# --- Schema principal de envio ---


class MessageInput(BaseModel):
    """Mensagem de e-mail a ser enviada."""

    from_address: EmailAddressInput = Field(..., alias="from")
    to: list[EmailAddressInput] = Field(default_factory=list)
    cc: list[EmailAddressInput] = Field(default_factory=list)
    bcc: list[EmailAddressInput] = Field(default_factory=list)
    reply_to: EmailAddressInput | None = None
    subject: str | None = None
    text: str | None = None
    html: str | None = None
    headers: dict[str, str] = Field(default_factory=dict)
    tags: list[str] = Field(default_factory=list)
    categories: list[str] = Field(default_factory=list)
    metadata: dict[str, Any] = Field(default_factory=dict)
    attachments: list[AttachmentInput] = Field(default_factory=list)
    template: TemplateConfigInput | None = None
    schedule_at: datetime | None = None
    sandbox_mode: bool = False
    track_opens: bool = False
    track_clicks: bool = False

    model_config = ConfigDict(populate_by_name=True)

    @field_validator("to", "cc", "bcc")
    @classmethod
    def validate_recipients(cls, v: list[EmailAddressInput]) -> list[EmailAddressInput]:
        """Valida lista de destinatários."""
        if not v:
            return v
        # Limite máximo de recipientes será validado no service
        return v


class SendEmailRequest(BaseModel):
    """Requisição de envio de e-mail."""

    idempotency_key: str | None = Field(
        None,
        max_length=128,
        description="Chave única para idempotência por projeto",
    )
    tenant_id: str | None = Field(None, max_length=64)
    message: MessageInput
    delivery_policy: DeliveryPolicyInput = Field(default_factory=DeliveryPolicyInput)


# --- Respostas ---


class SendResponse(BaseModel):
    """Resposta de envio aceito."""

    id: int
    uuid: str
    message_id: str
    status: SendStatus = SendStatus.QUEUED


class RecipientResponse(BaseModel):
    """Resposta de destinatário."""

    id: int
    email: str
    name: str | None = None
    recipient_type: str
    status: RecipientStatus
    sent_at: datetime | None = None
    delivered_at: datetime | None = None
    bounced_at: datetime | None = None
    bounce_type: BounceType | None = None
    error_message: str | None = None


class SendDetailResponse(BaseModel):
    """Detalhes completos de um envio."""

    id: int
    uuid: str
    message_id: str
    status: SendStatus
    project_id: int
    domain_id: int
    queue_type: QueueType
    from_email: str
    from_name: str | None = None
    subject: str | None = None
    recipients: list[RecipientResponse]
    created_at: datetime
    updated_at: datetime
    submitted_at: datetime | None = None
    delivered_at: datetime | None = None
    provider_name: str | None = None
    attempts_count: int
    metadata: dict[str, Any] = Field(default_factory=dict)


class EventResponse(BaseModel):
    """Evento normalizado."""

    id: int
    send_uuid: str
    event_type: EventType
    recipient_email: str | None = None
    timestamp: datetime
    provider: str | None = None
    provider_event_id: str | None = None
    payload: dict[str, Any] = Field(default_factory=dict)


class ActivityFeedResponse(BaseModel):
    """Activity feed de um envio."""

    send_uuid: str
    events: list[EventResponse]
    total: int


class SendSearchResult(BaseModel):
    """Resultado de busca de envios."""

    id: int
    uuid: str
    message_id: str
    status: SendStatus
    from_email: str
    to_emails: list[str]
    subject: str | None = None
    created_at: datetime
    delivered_at: datetime | None = None


class SendSearchResponse(BaseModel):
    """Resposta de busca paginada."""

    items: list[SendSearchResult]
    total: int
    has_more: bool


# --- Domínios ---


class DomainCreateRequest(BaseModel):
    """Criação de domínio."""

    name: str = Field(..., min_length=1, max_length=255)
    organization_id: int


class DomainResponse(BaseModel):
    """Resposta de domínio."""

    id: int
    organization_id: int
    name: str
    status: DomainStatus
    dkim_selector: str | None = None
    spf_record: str | None = None
    dmarc_record: str | None = None
    verified_at: datetime | None = None
    created_at: datetime
    updated_at: datetime


# --- Suppressions ---


class SuppressionCreateRequest(BaseModel):
    """Criação manual de supressão."""

    email: EmailStr
    reason: SuppressionReason = SuppressionReason.MANUAL
    expires_at: datetime | None = None
    metadata: dict[str, Any] = Field(default_factory=dict)


class SuppressionResponse(BaseModel):
    """Resposta de supressão."""

    id: int
    email: str
    reason: SuppressionReason
    source: str
    expires_at: datetime | None = None
    is_active: bool
    created_at: datetime


# --- Health ---


class HealthResponse(BaseModel):
    """Resposta de health check."""

    status: str
    version: str
    checks: dict[str, bool] = Field(default_factory=dict)


class ReadyResponse(BaseModel):
    """Resposta de readiness probe."""

    ready: bool
    checks: dict[str, bool] = Field(default_factory=dict)
