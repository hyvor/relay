"""Celery application configuration."""
import celery
from celery import Celery

from app.core.config import get_settings


def make_celery() -> Celery:
    """Create Celery app instance."""
    settings = get_settings()
    
    celery_app = Celery(
        "relay",
        broker=settings.celery_broker_url,
        backend=settings.celery_result_backend,
    )
    
    celery_app.conf.update(
        task_serializer=settings.celery_task_serializer,
        accept_content=settings.celery_accept_content,
        timezone=settings.celery_timezone,
        worker_prefetch_multiplier=settings.celery_worker_prefetch_multiplier,
        task_acks_late=settings.celery_task_acks_late,
        task_track_started=True,
        task_send_sent_event=True,
        worker_send_task_events=True,
        event_queue_expires=60,
    )
    
    return celery_app


celery_app = make_celery()
