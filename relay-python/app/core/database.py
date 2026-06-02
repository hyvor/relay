"""
Configuração de banco de dados com SQLAlchemy 2.x e asyncpg.
"""
from collections.abc import AsyncGenerator
from typing import Any

from sqlalchemy.ext.asyncio import (
    AsyncSession,
    async_sessionmaker,
    create_async_engine,
)
from sqlalchemy.orm import DeclarativeBase, Mapped, mapped_column
from sqlalchemy.pool import NullPool

from app.core.config import get_settings
from app.core.logging import get_logger

logger = get_logger(__name__)


class Base(DeclarativeBase):
    """Classe base para todos os modelos ORM."""

    id: Mapped[int] = mapped_column(primary_key=True, autoincrement=True)

    def to_dict(self) -> dict[str, Any]:
        """Converte entidade em dicionário."""
        return {
            column.name: getattr(self, column.name)
            for column in self.__table__.columns
        }


class DatabaseManager:
    """Gerenciador de conexões de banco de dados."""

    def __init__(self) -> None:
        self._engine = None
        self._async_session_maker = None

    def init(self) -> None:
        """Inicializa o engine e session factory."""
        settings = get_settings()

        logger.info(
            "Inicializando conexão com banco de dados",
            database_url=settings.database_url.split("@")[-1],  # Hide credentials
            pool_min_size=settings.db_pool_min_size,
            pool_max_size=settings.db_pool_max_size,
        )

        self._engine = create_async_engine(
            settings.database_url,
            pool_pre_ping=True,
            pool_size=settings.db_pool_min_size,
            max_overflow=settings.db_pool_max_size - settings.db_pool_min_size,
            pool_recycle=3600,
            echo=settings.app_debug,
        )

        self._async_session_maker = async_sessionmaker(
            self._engine,
            class_=AsyncSession,
            expire_on_commit=False,
            autocommit=False,
            autoflush=False,
        )

    async def close(self) -> None:
        """Fecha todas as conexões do engine."""
        if self._engine:
            await self._engine.dispose()
            logger.info("Conexões com banco de dados fechadas")

    async def get_session(self) -> AsyncGenerator[AsyncSession, None]:
        """
        Gera sessões de banco de dados.

        Yields:
            AsyncSession: Sessão assíncrona do SQLAlchemy
        """
        if not self._async_session_maker:
            raise RuntimeError("Database not initialized. Call init() first.")

        async with self._async_session_maker() as session:
            try:
                yield session
                await session.commit()
            except Exception:
                await session.rollback()
                raise
            finally:
                await session.close()

    @property
    def engine(self):
        """Retorna o engine assíncrono."""
        if not self._engine:
            raise RuntimeError("Database not initialized. Call init() first.")
        return self._engine


# Singleton global
db_manager = DatabaseManager()


async def get_db_session() -> AsyncGenerator[AsyncSession, None]:
    """
    Dependência do FastAPI para obter sessão de banco de dados.

    Yields:
        AsyncSession: Sessão do banco de dados
    """
    async for session in db_manager.get_session():
        yield session
