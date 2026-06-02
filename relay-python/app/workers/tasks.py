"""Celery tasks for async email processing."""
import asyncio
from datetime import datetime

from celery.exceptions import Retry
from structlog import get_logger

from app.core.database import db_manager
from app.domain.enums import BounceType, RecipientStatus, SendStatus
from app.providers.provider_factory import provider_router
from app.repositories.repositories import (
    EventRepository,
    RecipientRepository,
    SendRepository,
)
from app.workers.celery_app import celery_app

logger = get_logger(__name__)


@celery_app.task(bind=True, max_retries=5)
def process_send_task(self, send_id: int) -> dict:
    """Processa envio de e-mail assíncrono."""
    try:
        loop = asyncio.new_event_loop()
        asyncio.set_event_loop(loop)
        result = loop.run_until_complete(_process_send_async(send_id))
        loop.close()
        return result
    except Exception as exc:
        logger.exception("Erro no process_send_task", send_id=send_id)
        raise self.retry(exc=exc, countdown=60 * (2 ** self.request.retries))


async def _process_send_async(send_id: int) -> dict:
    """Lógica assíncrona de processamento."""
    async with db_manager.get_session() as session:
        send_repo = SendRepository(session)
        recipient_repo = RecipientRepository(session)
        event_repo = EventRepository(session)
        
        send_model = await send_repo.get_by_id(send_id)
        if not send_model:
            return {"error": "Send not found"}
        
        # Atualizar status para accepted
        await send_repo.update_status(send_id, SendStatus.ACCEPTED)
        
        # Obter recipients
        recipients = await recipient_repo.get_by_send_id(send_id)
        
        # Enviar via provider com failover
        from app.domain.entities import Send as SendEntity
        
        # Converter para entity (simplificado)
        result = await provider_router.send_with_failover(
            None,  # send entity - simplificação
            []  # recipients - simplificação
        )
        
        if result.success:
            await send_repo.update_status(
                send_id,
                SendStatus.PROVIDER_ACCEPTED,
                provider_name="smtp",
                provider_response=result.raw_response,
            )
            
            # Atualizar recipients
            for r in recipients:
                await recipient_repo.update_status(
                    r.id,
                    RecipientStatus.ACCEPTED,
                    provider_message_id=result.provider_message_id,
                )
            
            # Evento de sucesso
            await event_repo.create_event({
                "send_id": send_id,
                "event_type": "send.submitted",
                "timestamp": datetime.utcnow(),
            })
            
            logger.info("Email processado com sucesso", send_id=send_id)
            return {"success": True, "send_id": send_id}
        else:
            logger.warning("Falha no envio", send_id=send_id, error=result.error_message)
            return {"success": False, "error": result.error_message}


@celery_app.task(bind=True, max_retries=3)
def deliver_webhook_task(self, webhook_url: str, payload: dict) -> bool:
    """Entrega webhook para URL externa."""
    import httpx
    
    try:
        loop = asyncio.new_event_loop()
        asyncio.set_event_loop(loop)
        
        async def do_post():
            async with httpx.AsyncClient(timeout=10.0) as client:
                resp = await client.post(webhook_url, json=payload)
                return resp.status_code < 400
        
        result = loop.run_until_complete(do_post())
        loop.close()
        return result
    except Exception as exc:
        logger.exception("Erro na entrega de webhook")
        raise self.retry(exc=exc, countdown=60)
