"""
Enumerações de domínio para estados e tipos.
"""
from enum import Enum


class SendStatus(str, Enum):
    """Estados canônicos de um envio de e-mail."""

    ACCEPTED = "accepted"
    QUEUED = "queued"
    PROVIDER_SUBMITTED = "provider_submitted"
    PROVIDER_ACCEPTED = "provider_accepted"
    DELIVERED = "delivered"
    DELAYED = "delayed"
    BOUNCED_SOFT = "bounced_soft"
    BOUNCED_HARD = "bounced_hard"
    BLOCKED = "blocked"
    DROPPED = "dropped"
    COMPLAINED = "complained"
    UNSUBSCRIBED = "unsubscribed"
    OPENED = "opened"
    CLICKED = "clicked"
    FAILED_TERMINAL = "failed_terminal"


class RecipientStatus(str, Enum):
    """Estados de destinatário individual."""

    PENDING = "pending"
    SENT = "sent"
    ACCEPTED = "accepted"
    DEFERRED = "deferred"
    BOUNCED_SOFT = "bounced_soft"
    BOUNCED_HARD = "bounced_hard"
    BLOCKED = "blocked"
    COMPLAINED = "complained"
    SUPPRESSED = "suppressed"
    FAILED = "failed"


class EventType(str, Enum):
    """Tipos de eventos de webhook."""

    SEND_ACCEPTED = "send.accepted"
    SEND_QUEUED = "send.queued"
    SEND_SUBMITTED = "send.submitted"
    SEND_DELIVERED = "send.delivered"
    SEND_DELAYED = "send.delayed"
    SEND_BOUNCED = "send.bounced"
    SEND_BLOCKED = "send.blocked"
    SEND_DROPPED = "send.dropped"
    SEND_OPENED = "send.opened"
    SEND_CLICKED = "send.clicked"
    SEND_COMPLAINED = "send.complained"
    SEND_UNSUBSCRIBED = "send.unsubscribed"
    SEND_FAILED = "send.failed"
    RECIPIENT_ACCEPTED = "recipient.accepted"
    RECIPIENT_DEFERRED = "recipient.deferred"
    RECIPIENT_BOUNCED = "recipient.bounced"
    RECIPIENT_SUPPRESSED = "recipient.suppressed"
    SUPPRESSION_CREATED = "suppression.created"
    SUPPRESSION_DELETED = "suppression.deleted"
    DOMAIN_CREATED = "domain.created"
    DOMAIN_STATUS_CHANGED = "domain.status.changed"
    DOMAIN_DELETED = "domain.deleted"


class BounceType(str, Enum):
    """Tipos de bounce."""

    SOFT = "soft"
    HARD = "hard"
    UNSPECIFIED = "unspecified"


class SuppressionReason(str, Enum):
    """Motivos de supressão."""

    BOUNCE_HARD = "bounce_hard"
    BOUNCE_SOFT_REPEATED = "bounce_soft_repeated"
    COMPLAINT = "complaint"
    UNSUBSCRIBE = "unsubscribe"
    MANUAL = "manual"
    SPAM_TRAP = "spam_trap"


class DomainStatus(str, Enum):
    """Status de domínio remetente."""

    PENDING = "pending"
    VERIFIED = "verified"
    UNVERIFIED = "unverified"
    DISABLED = "disabled"
    SUSPENDED = "suspended"

    def can_send_emails(self) -> bool:
        """Verifica se o domínio pode enviar e-mails."""
        return self in (self.VERIFIED,)


class QueueType(str, Enum):
    """Tipos de fila."""

    TRANSACTIONAL = "transactional"
    DISTRIBUTIONAL = "distributional"


class Priority(str, Enum):
    """Prioridades de envio."""

    LOW = "low"
    NORMAL = "normal"
    HIGH = "high"
    URGENT = "urgent"
