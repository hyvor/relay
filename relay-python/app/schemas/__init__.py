"""
Schemas Pydantic - exports.
"""
from app.schemas.email import (
    ActivityFeedResponse,
    AttachmentInput,
    DeliveryPolicyInput,
    DomainCreateRequest,
    DomainResponse,
    EmailAddressInput,
    EventResponse,
    HealthResponse,
    MessageInput,
    ReadyResponse,
    RecipientResponse,
    SendDetailResponse,
    SendEmailRequest,
    SendResponse,
    SendSearchResponse,
    SuppressionCreateRequest,
    SuppressionResponse,
    TemplateConfigInput,
)

__all__ = [
    "ActivityFeedResponse",
    "AttachmentInput",
    "DeliveryPolicyInput",
    "DomainCreateRequest",
    "DomainResponse",
    "EmailAddressInput",
    "EventResponse",
    "HealthResponse",
    "MessageInput",
    "ReadyResponse",
    "RecipientResponse",
    "SendDetailResponse",
    "SendEmailRequest",
    "SendResponse",
    "SendSearchResponse",
    "SuppressionCreateRequest",
    "SuppressionResponse",
    "TemplateConfigInput",
]
