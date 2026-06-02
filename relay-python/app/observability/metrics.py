"""Prometheus metrics setup."""
from prometheus_client import Counter, Gauge, Histogram

# Counters
emails_total = Counter(
    "relay_emails_total",
    "Total number of emails sent",
    ["status", "provider"],
)

provider_calls_total = Counter(
    "relay_provider_calls_total",
    "Total provider API calls",
    ["provider", "status"],
)

webhook_deliveries_total = Counter(
    "relay_webhook_deliveries_total",
    "Total webhook deliveries",
    ["status", "event_type"],
)

# Gauges
queue_size_gauge = Gauge(
    "relay_queue_size",
    "Current queue size",
    ["queue_type"],
)

active_connections = Gauge(
    "relay_active_db_connections",
    "Active database connections",
)

# Histograms
send_duration = Histogram(
    "relay_send_duration_seconds",
    "Time spent sending emails",
    ["provider"],
    buckets=[0.1, 0.5, 1.0, 2.5, 5.0, 10.0],
)

request_duration = Histogram(
    "relay_request_duration_seconds",
    "HTTP request duration",
    ["method", "endpoint", "status"],
    buckets=[0.01, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0],
)


def setup_metrics() -> None:
    """Initialize metrics with default values."""
    # Initialize counters
    emails_total.labels(status="queued", provider="smtp").inc(0)
    emails_total.labels(status="sent", provider="smtp").inc(0)
    emails_total.labels(status="failed", provider="smtp").inc(0)
    
    logger = __import__("structlog").get_logger(__name__)
    logger.info("Metrics initialized")
