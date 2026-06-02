"""
Repositórios para acesso a dados.
"""
from collections.abc import AsyncGenerator
from datetime import datetime
from typing import Any
from uuid import UUID

from sqlalchemy import and_, func, or_, select
from sqlalchemy.ext.asyncio import AsyncSession

from app.domain.enums import RecipientStatus, SendStatus, SuppressionReason
from app.repositories.models import (
    ApiKey,
    Domain,
    Event,
    Organization,
    Project,
    Send,
    SendRecipient,
    Suppression,
)


class BaseRepository:
    """Classe base para repositórios."""

    def __init__(self, session: AsyncSession) -> None:
        self.session = session


class SendRepository(BaseRepository):
    """Repositório para envios de e-mail."""

    async def create_send(self, send_data: dict[str, Any]) -> Send:
        """Cria um novo envio."""
        send = Send(**send_data)
        self.session.add(send)
        await self.session.flush()
        return send

    async def get_by_id(self, send_id: int) -> Send | None:
        """Busca envio por ID."""
        result = await self.session.execute(select(Send).where(Send.id == send_id))
        return result.scalar_one_or_none()

    async def get_by_uuid(self, uuid: str) -> Send | None:
        """Busca envio por UUID."""
        result = await self.session.execute(select(Send).where(Send.uuid == uuid))
        return result.scalar_one_or_none()

    async def get_by_message_id(self, message_id: str) -> Send | None:
        """Busca envio por Message-ID."""
        result = await self.session.execute(
            select(Send).where(Send.message_id == message_id)
        )
        return result.scalar_one_or_none()

    async def get_by_idempotency_key(
        self, project_id: int, idempotency_key: str
    ) -> Send | None:
        """Busca envio por chave de idempotência."""
        result = await self.session.execute(
            select(Send).where(
                and_(
                    Send.project_id == project_id,
                    Send.idempotency_key == idempotency_key,
                )
            )
        )
        return result.scalar_one_or_none()

    async def get_pending_sends(self, limit: int = 10) -> list[Send]:
        """Busca envios pendentes para processamento."""
        result = await self.session.execute(
            select(Send)
            .where(
                and_(
                    Send.status.in_([SendStatus.QUEUED, SendStatus.ACCEPTED]),
                    or_(
                        Send.scheduled_for.is_(None),
                        Send.scheduled_for <= datetime.utcnow(),
                    ),
                )
            )
            .order_by(Send.created_at)
            .limit(limit)
        )
        return list(result.scalars().all())

    async def update_status(self, send_id: int, status: SendStatus, **kwargs: Any) -> None:
        """Atualiza status do envio."""
        await self.session.execute(
            select(Send).where(Send.id == send_id).with_for_update()
        )
        send = await self.get_by_id(send_id)
        if send:
            send.status = status
            for key, value in kwargs.items():
                setattr(send, key, value)
            send.updated_at = datetime.utcnow()
            await self.session.flush()

    async def search_sends(
        self,
        project_id: int,
        status: SendStatus | None = None,
        from_search: str | None = None,
        to_search: str | None = None,
        subject_search: str | None = None,
        date_from: datetime | None = None,
        date_to: datetime | None = None,
        limit: int = 50,
        before_id: int | None = None,
    ) -> tuple[list[Send], int]:
        """Busca envios com filtros."""
        conditions = [Send.project_id == project_id]

        if status:
            conditions.append(Send.status == status)
        if from_search:
            conditions.append(Send.from_email.ilike(f"%{from_search}%"))
        if subject_search:
            conditions.append(Send.subject.ilike(f"%{subject_search}%"))
        if date_from:
            conditions.append(Send.created_at >= date_from)
        if date_to:
            conditions.append(Send.created_at <= date_to)
        if before_id:
            conditions.append(Send.id < before_id)

        query = select(Send).where(and_(*conditions))
        count_query = select(func.count()).select_from(Send).where(and_(*conditions))

        if to_search:
            # Busca em recipients separadamente
            recipient_subquery = (
                select(SendRecipient.send_id)
                .where(SendRecipient.email.ilike(f"%{to_search}%"))
                .distinct()
            )
            query = query.where(Send.id.in_(recipient_subquery))
            count_query = count_query.where(Send.id.in_(recipient_subquery))

        query = query.order_by(Send.created_at.desc()).limit(limit)

        result = await self.session.execute(query)
        sends = list(result.scalars().all())

        count_result = await self.session.execute(count_query)
        total = count_result.scalar() or 0

        return sends, total


class RecipientRepository(BaseRepository):
    """Repositório para destinatários."""

    async def create_recipients(
        self, send_id: int, recipients_data: list[dict[str, Any]]
    ) -> list[SendRecipient]:
        """Cria múltiplos destinatários."""
        recipients = [
            SendRecipient(send_id=send_id, **data) for data in recipients_data
        ]
        self.session.add_all(recipients)
        await self.session.flush()
        return recipients

    async def get_by_send_id(self, send_id: int) -> list[SendRecipient]:
        """Busca destinatários por envio."""
        result = await self.session.execute(
            select(SendRecipient).where(SendRecipient.send_id == send_id)
        )
        return list(result.scalars().all())

    async def update_status(
        self, recipient_id: int, status: RecipientStatus, **kwargs: Any
    ) -> None:
        """Atualiza status do destinatário."""
        recipient = await self.session.get(SendRecipient, recipient_id)
        if recipient:
            recipient.status = status
            for key, value in kwargs.items():
                setattr(recipient, key, value)
            recipient.last_attempt_at = datetime.utcnow()
            await self.session.flush()


class EventRepository(BaseRepository):
    """Repositório para eventos."""

    async def create_event(self, event_data: dict[str, Any]) -> Event:
        """Cria um evento."""
        event = Event(**event_data)
        self.session.add(event)
        await self.session.flush()
        return event

    async def get_by_send_uuid(self, send_uuid: str) -> list[Event]:
        """Busca eventos por UUID do envio."""
        result = await self.session.execute(
            select(Event)
            .join(Send)
            .where(Send.uuid == send_uuid)
            .order_by(Event.timestamp.desc())
        )
        return list(result.scalars().all())


class SuppressionRepository(BaseRepository):
    """Repositório para supressões."""

    async def is_suppressed(self, email: str) -> bool:
        """Verifica se e-mail está suprimido."""
        result = await self.session.execute(
            select(Suppression).where(
                and_(
                    Suppression.email == email.lower(),
                    or_(
                        Suppression.expires_at.is_(None),
                        Suppression.expires_at > datetime.utcnow(),
                    ),
                )
            )
        )
        return result.scalar_one_or_none() is not None

    async def add_suppression(
        self, email: str, reason: SuppressionReason, source: str = "automatic"
    ) -> Suppression:
        """Adiciona supressão."""
        suppression = Suppression(
            email=email.lower(), reason=reason, source=source
        )
        self.session.add(suppression)
        await self.session.flush()
        return suppression


class DomainRepository(BaseRepository):
    """Repositório para domínios."""

    async def get_by_name(self, name: str) -> Domain | None:
        """Busca domínio por nome."""
        result = await self.session.execute(
            select(Domain).where(Domain.name == name.lower())
        )
        return result.scalar_one_or_none()

    async def get_by_project_and_name(
        self, project_id: int, domain_name: str
    ) -> Domain | None:
        """Busca domínio por projeto e nome."""
        result = await self.session.execute(
            select(Domain)
            .join(Organization)
            .join(Project)
            .where(
                and_(
                    Project.id == project_id,
                    Domain.name == domain_name.lower(),
                )
            )
        )
        return result.scalar_one_or_none()


class ApiKeyRepository(BaseRepository):
    """Repositório para API keys."""

    async def get_by_key_hash(self, key_hash: str) -> ApiKey | None:
        """Busca API key por hash."""
        result = await self.session.execute(
            select(ApiKey).where(
                and_(
                    ApiKey.key_hash == key_hash,
                    ApiKey.active == True,
                    or_(
                        ApiKey.expires_at.is_(None),
                        ApiKey.expires_at > datetime.utcnow(),
                    ),
                )
            )
        )
        return result.scalar_one_or_none()

    async def update_last_used(self, key_id: int) -> None:
        """Atualiza último uso da chave."""
        key = await self.session.get(ApiKey, key_id)
        if key:
            key.last_used_at = datetime.utcnow()
            await self.session.flush()
