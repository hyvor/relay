"""
Configuração de logging estruturado com structlog.
"""
import logging
import sys
from typing import Any

import structlog
from structlog.types import Processor

from app.core.config import get_settings


def setup_logging() -> None:
    """Configura logging estruturado para a aplicação."""
    settings = get_settings()

    # Mapeamento de nível de log
    log_level = getattr(logging, settings.log_level.upper())

    # Configurar handler de saída
    if settings.log_output == "file":
        handler = logging.FileHandler("relay.log")
    elif settings.log_output == "stderr":
        handler = logging.StreamHandler(sys.stderr)
    else:
        handler = logging.StreamHandler(sys.stdout)

    handler.setLevel(log_level)

    # Configurar formatter baseado no formato
    if settings.log_format == "json":
        # Para JSON, usamos o processador JSONRenderer do structlog
        pass  # O renderer é configurado no configure_call
    else:
        # Para texto, usamos ConsoleRenderer
        handler.setFormatter(logging.Formatter("%(message)s"))

    # Configurar logging padrão
    logging.basicConfig(
        format="%(message)s",
        level=log_level,
        handlers=[handler],
    )

    # Processadores comuns
    common_processors: list[Processor] = [
        structlog.contextvars.merge_contextvars,
        structlog.processors.add_log_level,
        structlog.processors.TimeStamper(fmt="iso"),
        structlog.stdlib.PositionalArgumentsFormatter(),
        structlog.processors.StackInfoRenderer(),
        structlog.processors.UnicodeDecoder(),
    ]

    # Processadores específicos por formato
    if settings.log_format == "json":
        output_processors: list[Processor] = [
            structlog.processors.dict_tracebacks,
            structlog.processors.JSONRenderer(),
        ]
    else:
        output_processors = [
            structlog.dev.ConsoleRenderer(colors=True),
        ]

    # Configurar structlog
    structlog.configure(
        processors=common_processors + output_processors,
        wrapper_class=structlog.make_filtering_bound_logger(log_level),
        context_class=dict,
        logger_factory=structlog.stdlib.LoggerFactory(),
        cache_logger_on_first_use=True,
    )


def get_logger(name: str | None = None) -> Any:
    """
    Retorna um logger estruturado.

    Args:
        name: Nome opcional do logger (geralmente __name__)

    Returns:
        Logger configurado com structlog
    """
    logger = structlog.get_logger(name)
    return logger


def bind_context(**kwargs: Any) -> None:
    """
    Adiciona contexto ao logger atual (thread-safe).

    Args:
        **kwargs: Pares chave-valor para adicionar ao contexto
    """
    structlog.contextvars.bind_contextvars(**kwargs)


def clear_context() -> None:
    """Limpa o contexto do logger atual."""
    structlog.contextvars.clear_contextvars()
