# Relay SMTP — Neogrid 🚀

![Python](https://img.shields.io/badge/Python-3.10%2B-blue)
![Status](https://img.shields.io/badge/status-ativo-success)
![License](https://img.shields.io/badge/license-MIT-yellow)
![Neogrid](https://img.shields.io/badge/Neogrid-%23FF5A00.svg?style=flat&logo=data:image/svg%2bxml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI4MCIgaGVpZ2h0PSI4MCIgdmlld0JveD0iMCAwIDgwIDgwIj48Y2lyY2xlIGN4PSI0MCIgY3k9IjQwIiByPSI0MCIgZmlsbD0iI0ZGNUEwMCIvPjwvc3ZnPg==)

## 📋 Descrição

**Relay SMTP** é um serviço assíncrono em Python para receber e-mails via SMTP local e encaminhar para um smarthost remoto (SendGrid, Postmark, Mailgun, etc.). Projetado para operação confiável com proteção anti-open relay, fila com backpressure, retries exponenciais, métricas Prometheus, logs estruturados e interfaces operacionais via **Web Dashboard** (com identidade visual Neogrid) e **TUI**.

## 📑 Índice

- [⚡ Quick Start](#-quick-start)
- [✨ Funcionalidades Principais](#-funcionalidades-principais)
- [🛠️ Tecnologias Utilizadas](#-tecnologias-utilizadas)
- [🚀 Instalação e Configuração](#-instalação-e-configuração)
- [💻 Exemplos de Uso](#-exemplos-de-uso)
- [📚 Documentação Adicional](#-documentação-adicional)
- [🧪 Testes](#-testes)
- [🤝 Como Contribuir](#-como-contribuir)
- [🗺️ Roadmap](#-roadmap)
- [📄 Licença](#-licença)
- [📞 Contato e Suporte](#-contato-e-suporte)

## ⚡ Quick Start

```bash
cp .env.example .env
cp templates/sendgrid.yaml config.yaml
uv venv
uv pip install -e ".[dev]"
uv run app --config config.yaml web --port 8080
```

Acesse:

- Dashboard: `http://127.0.0.1:8080/`
- Dashboard de Métricas (Gráficos): `http://127.0.0.1:8080/metrics/dashboard`
- Healthcheck: `http://127.0.0.1:8080/health`
- Métricas (Prometheus): `http://127.0.0.1:8080/metrics`

## ✨ Funcionalidades Principais

| Funcionalidade | Descrição |
|---|---|
| **Servidor SMTP assíncrono** | Múltiplas portas simultâneas (25, 587, etc.) com `aiosmtpd` |
| **Encaminhamento remoto** | Conexão assíncrona ao smarthost com `aiosmtplib` |
| **STARTTLS / TLS** | Suporte completo para transporte seguro |
| **Anti-open relay** | Regras por IP, remetente, destinatário e regras granulares |
| **Fila com backpressure** | Limite configurável com resposta de pressão à montante |
| **Retry exponencial** | Reprocessamento automático com backoff |
| **Observabilidade** | Logs JSON com `trace_id`, métricas Prometheus, healthcheck |
| **OpenTelemetry** | Exportação opcional de traces via OTLP/HTTP |
| **Dashboard Web** | Interface com **identidade visual Neogrid** (laranja `#FF5A00` + azul escuro `#001E3C`) |
| **Autenticação AD** | Suporte a **Active Directory** (`ldap3`) e autenticação básica |
| **TUI operacional** | Interface de terminal em tempo real com Textual |
| **Templates SaaS** | Configurações prontas para SendGrid, Postmark, Mailgun e mais |

## 🛠️ Tecnologias Utilizadas

| Categoria | Tecnologias |
|---|---|
| **Linguagem** | Python 3.10+, asyncio |
| **SMTP** | aiosmtpd, aiosmtplib |
| **Web** | FastAPI, uvicorn, HTMX, SSE |
| **Identidade visual** | Tema Neogrid (laranja `#FF5A00`, azul `#001E3C`) |
| **Autenticação** | ldap3 (Active Directory), HTTP Basic Auth |
| **Observabilidade** | prometheus_client, structlog, OpenTelemetry |
| **Terminal** | Textual |
| **Configuração** | PyYAML |
| **Testes** | pytest, pytest-asyncio |
| **Empacotamento** | uv, pip |
| **Containers** | Docker, Docker Compose |

## 🚀 Instalação e Configuração

### Pré-requisitos

- Python 3.10+
- `uv` (recomendado) ou `pip`
- Docker e Docker Compose (opcional)
- Credenciais de provedor SMTP remoto

### Instalação com `uv` (recomendado)

```bash
uv venv
uv pip install -e ".[dev]"
uv run app --config config.yaml serve
```

### Instalação com `pip`

```bash
python -m venv .venv
# Linux/macOS: source .venv/bin/activate
# Windows: .\.venv\Scripts\Activate.ps1
pip install -e ".[dev]"
app --config config.yaml serve
```

### Configuração inicial

#### 1. Variáveis de ambiente

```bash
cp .env.example .env
# Edite .env com suas credenciais
```

#### 2. Smarthost remoto

```yaml
remote:
  host: "smtp.sendgrid.net"
  port: 587
  username: "apikey"
  password: "${RELAY_REMOTE_PASSWORD}"
  starttls: true
  tls: false
  allow_insecure: false
```

#### 3. Anti-open relay

```yaml
security:
  allowed_source_ips: ["127.0.0.1", "::1"]
  allowed_sender_domains: ["example.com"]
  allowed_recipient_domains: []
```

#### 4. OpenTelemetry (opcional)

```yaml
otel:
  enabled: true
  endpoint: "http://otel-collector:4318/v1/traces"
  service_name: "relay-smtp"
  environment: "prod"
  headers:
    - key: "Authorization"
      value: "Bearer ${OTEL_TOKEN}"
```

#### 5. Autenticação do dashboard (opcional)

**Autenticação básica:**

```yaml
web_auth:
  enabled: true
  provider: "basic"
  username: "admin"
  password: "${RELAY_WEB_AUTH_PASSWORD}"
```

**Active Directory:**

```yaml
web_auth:
  enabled: true
  provider: "active_directory"
  ad_server: "ad.exemplo.com"
  ad_port: 389
  ad_use_ssl: false
  ad_domain: "exemplo.com"
  ad_base_dn: "DC=exemplo,DC=com"
  ad_user_attribute: "sAMAccountName"
  ad_bind_dn: "CN=svc-relay,OU=Services,DC=exemplo,DC=com"
  ad_bind_password: "${RELAY_WEB_AUTH_AD_BIND_PASSWORD}"
```

Variáveis de ambiente:

```bash
RELAY_WEB_AUTH_ENABLED=true
RELAY_WEB_AUTH_PROVIDER=active_directory
RELAY_WEB_AUTH_AD_SERVER=ad.exemplo.com
RELAY_WEB_AUTH_AD_PORT=389
RELAY_WEB_AUTH_AD_USE_SSL=false
RELAY_WEB_AUTH_AD_DOMAIN=exemplo.com
RELAY_WEB_AUTH_AD_BASE_DN=DC=exemplo,DC=com
RELAY_WEB_AUTH_AD_USER_ATTRIBUTE=sAMAccountName
RELAY_WEB_AUTH_AD_BIND_DN='CN=svc-relay,OU=Services,DC=exemplo,DC=com'
RELAY_WEB_AUTH_AD_BIND_PASSWORD='***'
```

#### 6. Template de provedor (opcional)

```bash
cp templates/sendgrid.yaml config.yaml
```

### Execução com Docker

```bash
cp .env.example .env
docker compose up --build
```

## 💻 Exemplos de Uso

### Modos de execução

```bash
# Relay SMTP + endpoints web (health + metrics)
uv run app --config config.yaml serve

# Relay + Dashboard Web completo (Neogrid)
uv run app --config config.yaml web --port 8080

# Relay + TUI operacional (terminal)
uv run app --config config.yaml tui
```

### Endpoints web

| Endpoint | Descrição |
|---|---|
| `http://127.0.0.1:8080/` | Dashboard Web com identidade visual Neogrid |
| `http://127.0.0.1:8080/health` | Healthcheck + snapshot de estado |
| `http://127.0.0.1:8080/metrics` | Métricas Prometheus |
| `http://127.0.0.1:8080/events` | SSE (Server-Sent Events) para atualizações em tempo real |

### Atalhos do Dashboard Web

| Ação | Descrição |
|---|---|
| **Pausar** | Interrompe encaminhamento (confirmação visual) |
| **Retomar** | Reativa o encaminhamento |
| **Reprocessar Falhas** | Reenvia mensagens com falha (com confirmação) |
| **Limpar Fila** | Remove mensagens pendentes (com confirmação) |
| **Filtro de logs** | Busca por `trace_id`, `message_id` ou texto |

### Atalhos TUI

| Tecla | Ação |
|---|---|
| `p` | Pausar encaminhamento |
| `r` | Retomar encaminhamento |
| `f` | Limpar fila |
| `e` | Reprocessar falhas |
| `l` | Reload de configuração |
| `q` | Sair |

### Envio de e-mails de teste

**Com swaks:**

```bash
swaks --server 127.0.0.1:587 \
  --from sender@example.com \
  --to recipient@example.com \
  --header "Subject: relay test" \
  --body "hello"
```

**Com Python:**

```python
import smtplib
from email.message import EmailMessage

msg = EmailMessage()
msg["From"] = "sender@example.com"
msg["To"] = "recipient@example.com"
msg["Subject"] = "relay test"
msg.set_content("hello")

with smtplib.SMTP("127.0.0.1", 587) as smtp:
    smtp.send_message(msg)
```

### Scripts auxiliares

```bash
# Teste em lote
python scripts/batch_relay_test.py --host 127.0.0.1 --port 587 --batches 5

# Benchmark de carga e latência
python scripts/benchmark.py --messages 2000 --concurrency 10 --output docs/benchmark.md
```

## 📚 Documentação Adicional

- Benchmark detalhado: [docs/benchmark.md](docs/benchmark.md)
- Scripts auxiliares: [scripts/README.md](scripts/README.md)
- Templates de configuração: [templates/README.md](templates/README.md)
- Catálogo de relays SaaS: [docs/saas-relays/index.md](docs/saas-relays/index.md)

## 🧪 Testes

```bash
uv run --extra dev pytest
# ou com venv ativo:
pytest
```

## 🤝 Como Contribuir

### Fluxo

1. Crie uma branch a partir da principal
2. Instale dependências de dev
3. Faça alterações pequenas e objetivas
4. Adicione/atualize testes
5. Execute `pytest`
6. Atualize a documentação
7. Abra Pull Request com descrição clara

### Diretrizes

- **Estilo**: PEP 8, type hints, funções curtas e assíncronas
- **Logs**: estruturados com `trace_id` e `message_id`
- **Dependências**: evitar sem justificativa técnica
- **Pull Request**: título objetivo, problema + solução + impacto, comandos de validação

## 🗺️ Roadmap

### ✅ Implementado

- [x] Spool persistente opcional em SQLite
- [x] Hot reload real de configuração
- [x] Autenticação para dashboard web (Basic + Active Directory)
- [x] Identidade visual Neogrid no Dashboard Web
- [x] Políticas avançadas por domínio/remetente/destinatário
- [x] Exportação de traces para OpenTelemetry
- [x] Templates de configuração por provedor SaaS
- [x] Benchmarks de carga e latência
- [x] Scripts de teste em lote

### 🔜 Planejado

- [ ] Suporte a múltiplos smarthosts com roteamento por domínio
- [ ] Webhook de notificação de falhas
- [ ] Painel de métricas com gráficos (HTMX + Chart.js)
- [ ] Modo cluster com Redis como fila compartilhada
- [ ] Interface de administração de regras de segurança via dashboard

## 📄 Licença

Distribuído sob a licença MIT. Consulte `LICENSE` para termos completos.

## 📞 Contato e Suporte

- Abra uma **issue** no repositório
- Contate o time mantenedor responsável pelo ambiente de deploy

---
