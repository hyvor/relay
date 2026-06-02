"""
Modelos ORM SQLAlchemy para persistência.
"""
from datetime import datetime
from typing import TYPE_CHECKING, Any

from sqlalchemy import (
    Boolean,
    DateTime,
    Enum,
    ForeignKey,
    Index,
    Integer,
    String,
    Text,
    UniqueConstraint,
)
from sqlalchemy.dialects.postgresql import JSONB, UUID as PG_UUID
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.core.database import Base
from app.domain.enums import (
    BounceType,
    DomainStatus,
    EventType,
    QueueType,
    RecipientStatus,
    SendStatus,
    SuppressionReason,
)


class Organization(Base):
    """Modelo ORM para organização."""

    __tablename__ = "organizations"

    id: Mapped[int] = mapped_column(primary_key=True, autoincrement=True)
    name: Mapped[str] = mapped_column(String(255), nullable=False)
    external_id: Mapped[str | None] = mapped_column(String(64), unique=True, index=True)
    settings: Mapped[dict[str, Any]] = mapped_column(JSONB, default=dict)
    active: Mapped[bool] = mapped_column(Boolean, default=True)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow)
    updated_at: Mapped[datetime] = mapped_column(
        DateTime, default=datetime.utcnow, onupdate=datetime.utcnow
    )

    projects: Mapped[list["Project"]] = relationship(
        "Project", back_populates="organization", lazy="select"
    )
    domains: Mapped[list["Domain"]] = relationship(
        "Domain", back_populates="organization", lazy="select"
    )

    __table_args__ = (Index("ix_organizations_active", "active"),)


class Project(Base):
    """Modelo ORM para projeto."""

    __tablename__ = "projects"

    id: Mapped[int] = mapped_column(primary_key=True, autoincrement=True)
    organization_id: Mapped[int] = mapped_column(
        ForeignKey("organizations.id"), nullable=False, index=True
    )
    name: Mapped[str] = mapped_column(String(255), nullable=False)
    external_id: Mapped[str | None] = mapped_column(String(64), index=True)
    send_type: Mapped[QueueType] = mapped_column(
        Enum(QueueType), default=QueueType.TRANSACTIONAL
    )
    settings: Mapped[dict[str, Any]] = mapped_column(JSONB, default=dict)
    active: Mapped[bool] = mapped_column(Boolean, default=True)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow)
    updated_at: Mapped[datetime] = mapped_column(
        DateTime, default=datetime.utcnow, onupdate=datetime.utcnow
    )

    organization: Mapped["Organization"] = relationship(
        "Organization", back_populates="projects"
    )
    sends: Mapped[list["Send"]] = relationship(
        "Send", back_populates="project", lazy="select"
    )
    api_keys: Mapped[list["ApiKey"]] = relationship(
        "ApiKey", back_populates="project", lazy="select"
    )

    __table_args__ = (
        Index("ix_projects_org_active", "organization_id", "active"),
        UniqueConstraint("organization_id", "external_id", name="uq_project_org_external"),
    )


class Domain(Base):
    """Modelo ORM para domínio remetente."""

    __tablename__ = "domains"

    id: Mapped[int] = mapped_column(primary_key=True, autoincrement=True)
    organization_id: Mapped[int] = mapped_column(
        ForeignKey("organizations.id"), nullable=False, index=True
    )
    name: Mapped[str] = mapped_column(String(255), nullable=False, unique=True)
    status: Mapped[DomainStatus] = mapped_column(
        Enum(DomainStatus), default=DomainStatus.PENDING
    )
    dkim_selector: Mapped[str | None] = mapped_column(String(64))
    dkim_public_key: Mapped[str | None] = mapped_column(Text)
    spf_record: Mapped[str | None] = mapped_column(Text)
    dmarc_record: Mapped[str | None] = mapped_column(Text)
    verified_at: Mapped[datetime | None] = mapped_column(DateTime)
    disabled_at: Mapped[datetime | None] = mapped_column(DateTime)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow)
    updated_at: Mapped[datetime] = mapped_column(
        DateTime, default=datetime.utcnow, onupdate=datetime.utcnow
    )

    organization: Mapped["Organization"] = relationship(
        "Organization", back_populates="domains"
    )
    sends: Mapped[list["Send"]] = relationship(
        "Send", back_populates="domain", lazy="select"
    )

    __table_args__ = (Index("ix_domains_status", "status"),)


class Send(Base):
    """Modelo ORM para envio de e-mail."""

    __tablename__ = "sends"

    id: Mapped[int] = mapped_column(primary_key=True, autoincrement=True)
    uuid: Mapped[str] = mapped_column(PG_UUID(as_uuid=False), unique=True, index=True)
    project_id: Mapped[int] = mapped_column(
        ForeignKey("projects.id"), nullable=False, index=True
    )
    domain_id: Mapped[int] = mapped_column(
        ForeignKey("domains.id"), nullable=False, index=True
    )
    queue_type: Mapped[QueueType] = mapped_column(Enum(QueueType))
    idempotency_key: Mapped[str | None] = mapped_column(String(128), index=True)
    tenant_id: Mapped[str | None] = mapped_column(String(64))

    # Message fields (denormalized for query performance)
    from_email: Mapped[str] = mapped_column(String(255), nullable=False)
    from_name: Mapped[str | None] = mapped_column(String(255))
    subject: Mapped[str | None] = mapped_column(Text)
    message_id: Mapped[str | None] = mapped_column(String(255), unique=True, index=True)

    # Status
    status: Mapped[SendStatus] = mapped_column(
        Enum(SendStatus), default=SendStatus.QUEUED, index=True
    )
    provider_name: Mapped[str | None] = mapped_column(String(64))
    provider_response: Mapped[str | None] = mapped_column(Text)
    attempts_count: Mapped[int] = mapped_column(Integer, default=0)

    # Timestamps
    created_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow, index=True)
    updated_at: Mapped[datetime] = mapped_column(
        DateTime, default=datetime.utcnow, onupdate=datetime.utcnow
    )
    scheduled_for: Mapped[datetime | None] = mapped_column(DateTime, index=True)
    submitted_at: Mapped[datetime | None] = mapped_column(DateTime)
    delivered_at: Mapped[datetime | None] = mapped_column(DateTime)

    # Raw email and size
    raw_email: Mapped[bytes | None] = mapped_column(Text)  # Stored as base64 or text
    size_bytes: Mapped[int] = mapped_column(Integer, default=0)

    # Metadata
    tags: Mapped[list[str]] = mapped_column(JSONB, default=list)
    categories: Mapped[list[str]] = mapped_column(JSONB, default=list)
    metadata_: Mapped[dict[str, Any]] = mapped_column("metadata", JSONB, default=dict)

    # Relationships
    project: Mapped["Project"] = relationship("Project", back_populates="sends")
    domain: Mapped["Domain"] = relationship("Domain", back_populates="sends")
    recipients: Mapped[list["SendRecipient"]] = relationship(
        "SendRecipient", back_populates="send", lazy="select", cascade="all, delete-orphan"
    )
    events: Mapped[list["Event"]] = relationship(
        "Event", back_populates="send", lazy="select", cascade="all, delete-orphan"
    )

    __table_args__ = (
        Index("ix_sends_project_status_created", "project_id", "status", "created_at"),
        Index("ix_sends_status_created", "status", "created_at"),
    )


class SendRecipient(Base):
    """Modelo ORM para destinatário individual."""

    __tablename__ = "send_recipients"

    id: Mapped[int] = mapped_column(primary_key=True, autoincrement=True)
    send_id: Mapped[int] = mapped_column(
        ForeignKey("sends.id", ondelete="CASCADE"), nullable=False, index=True
    )
    email: Mapped[str] = mapped_column(String(255), nullable=False, index=True)
    name: Mapped[str | None] = mapped_column(String(255))
    recipient_type: Mapped[str] = mapped_column(String(10), default="to")  # to, cc, bcc

    # Status
    status: Mapped[RecipientStatus] = mapped_column(
        Enum(RecipientStatus), default=RecipientStatus.PENDING, index=True
    )
    message_id: Mapped[str | None] = mapped_column(String(255))
    provider_message_id: Mapped[str | None] = mapped_column(String(255))

    # Timestamps
    sent_at: Mapped[datetime | None] = mapped_column(DateTime)
    delivered_at: Mapped[datetime | None] = mapped_column(DateTime)
    bounced_at: Mapped[datetime | None] = mapped_column(DateTime)
    complained_at: Mapped[datetime | None] = mapped_column(DateTime)
    suppressed_at: Mapped[datetime | None] = mapped_column(DateTime)

    # Bounce info
    bounce_type: Mapped[BounceType | None] = mapped_column(Enum(BounceType))
    bounce_reason: Mapped[str | None] = mapped_column(Text)
    error_message: Mapped[str | None] = mapped_column(Text)

    # Retry info
    attempts_count: Mapped[int] = mapped_column(Integer, default=0)
    last_attempt_at: Mapped[datetime | None] = mapped_column(DateTime)

    # Relationships
    send: Mapped["Send"] = relationship("Send", back_populates="recipients")

    __table_args__ = (
        Index("ix_recipients_send_status", "send_id", "status"),
        Index("ix_recipients_email_status", "email", "status"),
    )


class Event(Base):
    """Modelo ORM para eventos de webhook."""

    __tablename__ = "events"

    id: Mapped[int] = mapped_column(primary_key=True, autoincrement=True)
    send_id: Mapped[int] = mapped_column(
        ForeignKey("sends.id", ondelete="CASCADE"), nullable=False, index=True
    )
    event_type: Mapped[EventType] = mapped_column(Enum(EventType), index=True)
    recipient_email: Mapped[str | None] = mapped_column(String(255), index=True)

    # Provider info
    provider: Mapped[str | None] = mapped_column(String(64))
    provider_event_id: Mapped[str | None] = mapped_column(String(255))

    # Payload
    payload: Mapped[dict[str, Any]] = mapped_column(JSONB, default=dict)
    metadata_: Mapped[dict[str, Any]] = mapped_column("metadata", JSONB, default=dict)

    # Processing
    processed: Mapped[bool] = mapped_column(Boolean, default=False)
    timestamp: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow, index=True)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow)

    # Relationships
    send: Mapped["Send"] = relationship("Send", back_populates="events")

    __table_args__ = (
        Index("ix_events_send_type_timestamp", "send_id", "event_type", "timestamp"),
    )


class Suppression(Base):
    """Modelo ORM para supressões de e-mail."""

    __tablename__ = "suppressions"

    id: Mapped[int] = mapped_column(primary_key=True, autoincrement=True)
    email: Mapped[str] = mapped_column(String(255), nullable=False, unique=True, index=True)
    reason: Mapped[SuppressionReason] = mapped_column(Enum(SuppressionReason))
    source: Mapped[str] = mapped_column(String(32), default="automatic")
    expires_at: Mapped[datetime | None] = mapped_column(DateTime, index=True)
    metadata_: Mapped[dict[str, Any]] = mapped_column("metadata", JSONB, default=dict)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow)

    __table_args__ = (Index("ix_suppressions_email_active", "email", "expires_at"),)


class ApiKey(Base):
    """Modelo ORM para chaves de API."""

    __tablename__ = "api_keys"

    id: Mapped[int] = mapped_column(primary_key=True, autoincrement=True)
    project_id: Mapped[int] = mapped_column(
        ForeignKey("projects.id"), nullable=False, index=True
    )
    key_hash: Mapped[str] = mapped_column(String(255), nullable=False, unique=True, index=True)
    name: Mapped[str | None] = mapped_column(String(255))
    scopes: Mapped[list[str]] = mapped_column(JSONB, default=list)
    active: Mapped[bool] = mapped_column(Boolean, default=True)
    expires_at: Mapped[datetime | None] = mapped_column(DateTime)
    last_used_at: Mapped[datetime | None] = mapped_column(DateTime)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow)

    # Relationships
    project: Mapped["Project"] = relationship("Project", back_populates="api_keys")

    __table_args__ = (Index("ix_api_keys_project_active", "project_id", "active"),)
