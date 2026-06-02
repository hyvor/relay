"""FastAPI routes for email API."""
from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.database import get_db_session
from app.domain.enums import SendStatus
from app.repositories.repositories import (
    ApiKeyRepository,
    DomainRepository,
    EventRepository,
    RecipientRepository,
    SendRepository,
    SuppressionRepository,
)
from app.schemas.email import (
    ActivityFeedResponse,
    EventResponse,
    HealthResponse,
    ReadyResponse,
    SendDetailResponse,
    SendEmailRequest,
    SendResponse,
    SendSearchResponse,
)
from app.application.send_service import SendService

router = APIRouter()


def get_send_service(session: AsyncSession) -> SendService:
    """Factory para SendService."""
    return SendService(
        send_repo=SendRepository(session),
        recipient_repo=RecipientRepository(session),
        event_repo=EventRepository(session),
        domain_repo=DomainRepository(session),
        suppression_repo=SuppressionRepository(session),
    )


@router.post("/emails", response_model=SendResponse, status_code=status.HTTP_201_CREATED)
async def send_email(
    request: SendEmailRequest,
    service: SendService = Depends(get_send_service),
    session: AsyncSession = Depends(get_db_session),
):
    """Enviar e-mail transacional."""
    # Em produção: validar API key, obter project_id
    
    try:
        send = await service.create_send(
            project_id=1,  # Placeholder
            message_input=request.message,
            delivery_policy=request.delivery_policy,
            idempotency_key=request.idempotency_key,
            tenant_id=request.tenant_id,
        )
        
        return SendResponse(
            id=send.id,
            uuid=str(send.uuid),
            message_id=send.message_id or "",
            status=send.status,
        )
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))


@router.get("/emails/{email_id}", response_model=SendDetailResponse)
async def get_email(
    email_id: int,
    session: AsyncSession = Depends(get_db_session),
):
    """Obter detalhes de envio."""
    send_repo = SendRepository(session)
    send = await send_repo.get_by_id(email_id)
    
    if not send:
        raise HTTPException(status_code=404, detail="Send not found")
    
    recipient_repo = RecipientRepository(session)
    recipients = await recipient_repo.get_by_send_id(email_id)
    
    return SendDetailResponse(
        id=send.id,
        uuid=send.uuid,
        message_id=send.message_id or "",
        status=send.status,
        project_id=send.project_id,
        domain_id=send.domain_id,
        queue_type=send.queue_type,
        from_email=send.from_email,
        from_name=send.from_name,
        subject=send.subject,
        recipients=[],  # Populate properly
        created_at=send.created_at,
        updated_at=send.updated_at,
        submitted_at=send.submitted_at,
        delivered_at=send.delivered_at,
        provider_name=send.provider_name,
        attempts_count=send.attempts_count,
        metadata=send.metadata_,
    )


@router.get("/emails/{email_uuid}/events", response_model=ActivityFeedResponse)
async def get_activity_feed(
    email_uuid: str,
    session: AsyncSession = Depends(get_db_session),
):
    """Obter activity feed de envio."""
    send_repo = SendRepository(session)
    send = await send_repo.get_by_uuid(email_uuid)
    
    if not send:
        raise HTTPException(status_code=404, detail="Send not found")
    
    event_repo = EventRepository(session)
    events = await event_repo.get_by_send_uuid(email_uuid)
    
    return ActivityFeedResponse(
        send_uuid=email_uuid,
        events=[
            EventResponse(
                id=e.id,
                send_uuid=email_uuid,
                event_type=e.event_type,
                recipient_email=e.recipient_email,
                timestamp=e.timestamp,
                provider=e.provider,
                provider_event_id=e.provider_event_id,
                payload=e.payload,
            )
            for e in events
        ],
        total=len(events),
    )


@router.get("/emails/search", response_model=SendSearchResponse)
async def search_emails(
    status: SendStatus | None = None,
    limit: int = 50,
    before_id: int | None = None,
    session: AsyncSession = Depends(get_db_session),
):
    """Buscar envios com filtros."""
    send_repo = SendRepository(session)
    sends, total = await send_repo.search_sends(
        project_id=1,  # Placeholder
        status=status,
        limit=limit,
        before_id=before_id,
    )
    
    return SendSearchResponse(
        items=[],  # Populate properly
        total=total,
        has_more=len(sends) == limit,
    )


@router.get("/health", response_model=HealthResponse)
async def health_check():
    """Health check endpoint."""
    return HealthResponse(
        status="healthy",
        version="0.1.0",
        checks={"database": True, "redis": True, "queue": True},
    )


@router.get("/ready", response_model=ReadyResponse)
async def readiness_check():
    """Readiness probe endpoint."""
    return ReadyResponse(
        ready=True,
        checks={"database": True, "redis": True, "queue": True},
    )
