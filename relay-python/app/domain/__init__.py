"""
Módulo de domínio - exports.
"""
from app.domain.entities import (
    ApiKey,
    Attachment,
    DeliveryPolicy,
    Domain,
    EmailAddress,
    Event,
    Message,
    Organization,
    Project,
    Send,
    SendRecipient,
    Suppression,
    TemplateConfig,
)
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

__all__ = [
    # Entities
    "ApiKey",
    "Attachment",
    "DeliveryPolicy",
    "Domain",
    "EmailAddress",
    "Event",
    "Message",
    "Organization",
    "Project",
    "Send",
    "SendRecipient",
    "Suppression",
    "TemplateConfig",
    # Enums
    "BounceType",
    "DomainStatus",
    "EventType",
    "Priority",
    "QueueType",
    "RecipientStatus",
    "SendStatus",
    "SuppressionReason",
]
