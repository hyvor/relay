"""FastAPI application entry point."""
from contextlib import asynccontextmanager

from fastapi import FastAPI
from prometheus_client import make_asgi_app
from structlog import get_logger

from app.core.config import get_settings
from app.core.database import db_manager
from app.core.logging import setup_logging
from app.interfaces.http.routes import router as api_router
from app.observability.metrics import setup_metrics

logger = get_logger(__name__)


@asynccontextmanager
async def lifespan(app: FastAPI):
    """Application lifespan manager."""
    # Startup
    setup_logging()
    settings = get_settings()
    
    logger.info("Starting Relay API", version="0.1.0", env=settings.app_env)
    
    # Initialize database
    db_manager.init()
    
    # Setup metrics
    setup_metrics()
    
    yield
    
    # Shutdown
    logger.info("Shutting down Relay API")
    await db_manager.close()


def create_app() -> FastAPI:
    """Create FastAPI application instance."""
    settings = get_settings()
    
    app = FastAPI(
        title="Relay Email API",
        description="Sistema de e-mail transacional de alta disponibilidade",
        version="0.1.0",
        docs_url="/docs" if settings.app_debug else None,
        redoc_url="/redoc" if settings.app_debug else None,
        lifespan=lifespan,
    )
    
    # Include routers
    app.include_router(api_router, prefix="/v1")
    
    # Prometheus metrics endpoint
    if settings.prometheus_enabled:
        metrics_app = make_asgi_app()
        app.mount("/metrics", metrics_app)
    
    return app


app = create_app()


if __name__ == "__main__":
    import uvicorn
    settings = get_settings()
    uvicorn.run(
        "app.main:app",
        host=settings.host,
        port=settings.port,
        reload=settings.app_debug,
    )
