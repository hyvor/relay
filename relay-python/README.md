# Relay Python - Email Transacional de Produção

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Python 3.12+](https://img.shields.io/badge/python-3.12+-blue.svg)](https://www.python.org/downloads/)
[![FastAPI](https://img.shields.io/badge/FastAPI-0.109+-green.svg)](https://fastapi.tiangolo.com/)
[![Code style: black](https://img.shields.io/badge/code%20style-black-000000.svg)](https://github.com/psf/black)

Sistema de e-mail transacional de alta disponibilidade, implementado em Python com arquitetura limpa, suporte a múltiplos provedores e observabilidade nativa.

## 🚀 Funcionalidades

- **API Unificada**: Endpoint REST para envio de e-mails transacionais
- **Multi-Provider**: Suporte a SMTP direto, SendGrid, SES, Mailgun com failover automático
- **Idempotência**: Garantia de não-duplicação via chaves únicas
- **Retry Inteligente**: Backoff exponencial com handling de greylisting
- **Webhooks**: Callbacks normalizados para eventos de entrega
- **Activity Feed**: Rastreamento completo de tentativas e estados
- **Multi-Tenancy**: Isolamento por organização/projeto
- **Observabilidade**: Logs estruturados, métricas Prometheus, tracing OpenTelemetry
- **Alta Disponibilidade**: Circuit breaker, DLQ, health checks

## 📋 Requisitos

- Docker & Docker Compose
- Python 3.12+ (para desenvolvimento local sem Docker)

## 🏃‍♂️ Quick Start

### Com Docker (Recomendado)

```bash
# Clonar repositório
cd relay-python

# Copiar ambiente
cp .env.example .env

# Subir serviços
docker compose up -d

# Ver logs
docker compose logs -f api worker

# Acessar API
curl http://localhost:8000/health
```

### Desenvolvimento Local

```bash
# Criar ambiente virtual
python -m venv .venv
source .venv/bin/activate

# Instalar dependências
pip install -e ".[dev]"

# Rodar migrations
alembic upgrade head

# Iniciar API
uvicorn app.main:app --reload

# Iniciar worker
celery -A app.workers.celery_app worker --loglevel=info
```

## 📚 Documentação

- [Arquitetura](docs/architecture.md)
- [ADRs](docs/adr-*.md)
- [API Reference](docs/api-reference.md)
- [Roadmap](ROADMAP.md)

## 🔧 Configuração

Variáveis de ambiente principais:

```bash
# Database
DATABASE_URL=postgresql+asyncpg://relay:relay@postgres:5432/relay

# Redis
REDIS_URL=redis://redis:6379/0

# Celery
CELERY_BROKER_URL=redis://redis:6379/0
CELERY_RESULT_BACKEND=redis://redis:6379/0

# Providers
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_USER=user
SMTP_PASS=password

SENDGRID_API_KEY=SG.xxx
SES_REGION=us-east-1
SES_ACCESS_KEY=xxx
SES_SECRET_KEY=xxx

# App
APP_ENV=development
SECRET_KEY=your-secret-key-here
LOG_LEVEL=INFO
```

Veja [.env.example](.env.example) para todas as opções.

## 📡 Endpoints da API

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/v1/emails` | Enviar e-mail |
| GET | `/v1/emails/{message_id}` | Detalhes do envio |
| GET | `/v1/emails/{message_id}/events` | Activity feed |
| GET | `/v1/emails/search` | Busca avançada |
| POST | `/v1/webhooks/{provider}` | Receber webhooks |
| GET | `/health` | Health check |
| GET | `/ready` | Readiness probe |
| GET | `/metrics` | Métricas Prometheus |

### Exemplo de Envio

```bash
curl -X POST http://localhost:8000/v1/emails \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -d '{
    "idempotency_key": "unique-key-123",
    "tenant_id": "acme-prod",
    "message": {
      "from": {"email": "noreply@example.com", "name": "Acme"},
      "to": [{"email": "user@example.org", "name": "User"}],
      "subject": "Pedido Confirmado",
      "html": "<h1>Obrigado pela compra!</h1>",
      "text": "Obrigado pela compra!"
    }
  }'
```

## 🏗️ Arquitetura

```
relay-python/
├── app/
│   ├── core/           # Config, segurança, database
│   ├── domain/         # Entidades e regras de negócio
│   ├── application/    # Casos de uso e serviços
│   ├── infrastructure/ # Implementações concretas
│   ├── interfaces/     # HTTP, gRPC, CLI
│   ├── workers/        # Tarefas assíncronas Celery
│   ├── providers/      # Adaptadores de provedores
│   ├── repositories/   # Acesso a dados
│   ├── schemas/        # Pydantic models
│   └── observability/  # Logs, métricas, tracing
├── migrations/         # Alembic migrations
├── tests/              # Testes unitários e integração
├── docs/               # Documentação
└── docker/             # Configs Docker
```

### Fluxo de Envio

```
POST /v1/emails
    ↓
Validação (Pydantic + Regras)
    ↓
Verificar Idempotência (Redis)
    ↓
Criar Send (PostgreSQL)
    ↓
Publicar na Fila (Celery + Redis)
    ↓
Worker Processa
    ↓
Selecionar Provider (Failover)
    ↓
Enviar SMTP/API
    ↓
Atualizar Estado
    ↓
Trigger Webhooks
```

## 🧪 Testes

```bash
# Unitários
pytest tests/unit -v

# Integração (requer Docker)
docker compose up -d postgres redis
pytest tests/integration -v

# Cobertura
pytest --cov=app --cov-report=html
```

## 📊 Métricas

Acesse `http://localhost:8000/metrics` para métricas Prometheus:

- `relay_emails_total`: Total de e-mails enviados
- `relay_emails_by_status`: Contagem por status
- `relay_provider_calls_total`: Chamadas por provider
- `relay_webhook_deliveries_total`: Webhooks entregues
- `request_duration_seconds`: Latência de requests

## 🔒 Segurança

- API Keys com scopes granulares
- Validação rigorosa de input
- Rate limiting (a implementar)
- TLS obrigatório em produção
- Secrets via variáveis de ambiente

## 🛣️ Roadmap

Veja [ROADMAP.md](ROADMAP.md) para funcionalidades planejadas:

- [ ] Tracking de opens/clicks
- [ ] Rate limiting avançado
- [ ] Dashboard administrativo
- [ ] Templates versionados
- [ ] DKIM/SPF automation
- [ ] Incoming email handling

## 🤝 Contribuindo

Veja [CONTRIBUTING.md](CONTRIBUTING.md) para diretrizes.

## 📄 Licença

MIT License - veja [LICENSE](LICENSE) para detalhes.

## 🙏 Créditos

Implementação baseada no projeto original [relay](https://github.com/marcuscabrera/relay) de Marcus Cabrera.
