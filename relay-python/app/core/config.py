"""
Configuração centralizada da aplicação usando pydantic-settings.
"""
from functools import lru_cache
from typing import Literal

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    """Configurações da aplicação carregadas de variáveis de ambiente."""

    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        case_sensitive=False,
        extra="ignore",
    )

    # Application
    app_env: Literal["development", "staging", "production"] = "development"
    app_debug: bool = False
    secret_key: str = "change-me-in-production"
    host: str = "0.0.0.0"
    port: int = 8000

    # Database
    database_url: str = "postgresql+asyncpg://relay:relay@localhost:5432/relay"
    db_pool_min_size: int = 5
    db_pool_max_size: int = 20

    # Redis
    redis_url: str = "redis://localhost:6379/0"
    redis_max_connections: int = 20

    # Celery
    celery_broker_url: str = "redis://localhost:6379/0"
    celery_result_backend: str = "redis://localhost:6379/0"
    celery_task_serializer: str = "json"
    celery_accept_content: list[str] = ["json"]
    celery_timezone: str = "UTC"
    celery_worker_prefetch_multiplier: int = 1
    celery_task_acks_late: bool = True

    # SMTP Provider
    smtp_host: str | None = None
    smtp_port: int = 587
    smtp_user: str | None = None
    smtp_pass: str | None = None
    smtp_tls: bool = True
    smtp_timeout: int = 30

    # SendGrid
    sendgrid_api_key: str | None = None
    sendgrid_enabled: bool = False

    # AWS SES
    ses_region: str = "us-east-1"
    ses_access_key: str | None = None
    ses_secret_key: str | None = None
    ses_enabled: bool = False

    # Mailgun
    mailgun_api_key: str | None = None
    mailgun_domain: str | None = None
    mailgun_enabled: bool = False

    # Provider Failover
    provider_failover_enabled: bool = True
    provider_preference_order: str = "smtp,sendgrid,ses,mailgun"
    max_provider_attempts: int = 2

    # Logging
    log_level: Literal["DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL"] = "INFO"
    log_format: Literal["json", "text"] = "json"
    log_output: Literal["stdout", "stderr", "file"] = "stdout"

    # OpenTelemetry
    otel_enabled: bool = False
    otel_exporter_otlp_endpoint: str = "http://localhost:4317"
    otel_service_name: str = "relay-python"

    # Prometheus
    prometheus_enabled: bool = True
    prometheus_port: int = 9090

    # Security
    api_key_header: str = "X-API-Key"
    jwt_algorithm: str = "HS256"
    access_token_expire_minutes: int = 30

    # Rate Limiting
    rate_limit_enabled: bool = False
    rate_limit_default: str = "100/minute"

    # Webhooks
    webhook_timeout: int = 10
    webhook_max_retries: int = 3
    webhook_retry_delay: int = 60

    # Email Limits
    max_recipients_per_send: int = 100
    max_email_size_mb: int = 10
    max_attachment_size_mb: int = 5

    # Queue
    queue_poll_interval: float = 1.0
    queue_batch_size: int = 10

    # Health Checks
    health_check_db: bool = True
    health_check_redis: bool = True
    health_check_queue: bool = True

    @property
    def providers_enabled(self) -> list[str]:
        """Retorna lista de provedores habilitados na ordem de preferência."""
        preference = self.provider_preference_order.split(",")
        enabled = []

        provider_status = {
            "smtp": self.smtp_host is not None,
            "sendgrid": self.sendgrid_enabled and self.sendgrid_api_key is not None,
            "ses": self.ses_enabled and self.ses_access_key is not None,
            "mailgun": self.mailgun_enabled and self.mailgun_api_key is not None,
        }

        for provider in preference:
            if provider_status.get(provider, False):
                enabled.append(provider)

        return enabled

    @property
    def max_email_size_bytes(self) -> int:
        """Retorna tamanho máximo do e-mail em bytes."""
        return self.max_email_size_mb * 1024 * 1024

    @property
    def max_attachment_size_bytes(self) -> int:
        """Retorna tamanho máximo do anexo em bytes."""
        return self.max_attachment_size_mb * 1024 * 1024


@lru_cache
def get_settings() -> Settings:
    """Retorna instância singleton das configurações."""
    return Settings()
