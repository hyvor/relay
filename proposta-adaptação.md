## 🧠 Proposta de Adaptação à Stack Atual (Hyvor Relay)

Este estudo avalia a migração e adaptação do serviço **Relay SMTP (Python/FastAPI)** para a stack nativa do **Hyvor Relay (PHP/Symfony + Go + SvelteKit + PostgreSQL)**. A stack do Hyvor Relay oferece maior escalabilidade, suporte a multi-tenancy e gerenciamento avançado de reputação.

### 1. Mapeamento Arquitetural de Componentes

| Funcionalidade (Python - Neogrid) | Equivalente na Stack Atual (Hyvor) | Estratégia de Adaptação / Integração |
| :--- | :--- | :--- |
| **SMTP Inbound** (`aiosmtpd`) | Go SMTP Server (`go-smtp`) | O worker Go já possui um servidor SMTP em `worker/incoming_server.go`. É necessário portar os filtros de IP e domínio para o método `Session.Rcpt()` em Go. |
| **SMTP Outbound** (`aiosmtplib`) | Go Workers Pool (`email_worker.go`) | Utilizar a pool de envio concorrente nativa do Go, que se conecta diretamente aos servidores MX de destino ou a um smarthost remoto. |
| **Queue / Spool** (SQLite local) | PostgreSQL Queue | Substituir o spool local SQLite pela fila PostgreSQL de alta performance (`FOR UPDATE SKIP LOCKED`). Isso elimina gargalos de I/O em disco e permite escalabilidade horizontal. |
| **Autenticação** (LDAP/AD e Basic) | PHP Symfony Auth + OIDC | Delegar a autenticação de SMTP AUTH do worker Go para o backend PHP Symfony através de uma chamada de API local (`/api/local/auth`). O PHP se encarrega de validar contra a base local ou contra o Active Directory (LDAP). |
| **Dashboard Web** (FastAPI + HTMX) | SvelteKit Frontend | Substituir a interface HTMX pelo painel SvelteKit. A interface de administração e visualização de logs será customizada visualmente. |
| **Identidade Visual Neogrid** | Customização de CSS/Tokens | Adaptar o arquivo de estilos globais (`frontend/src/routes/app.css` ou tokens da `@hyvor/design`) para aplicar a paleta de cores da Neogrid (Laranja `#FF5A00` e Azul Escuro `#001E3C`). |
| **Observabilidade** (Prometheus & OTEL) | Prometheus Nativo + OTEL em Go | Reaproveitar o exportador de métricas Prometheus do Go worker e adicionar suporte a OpenTelemetry usando a biblioteca oficial `go.opentelemetry.io/otel`. |
| **Interface CLI / TUI** (Textual) | Go TUI (Bubbletea) | Opcionalmente, pode-se desenvolver um CLI leve em Go utilizando a biblioteca *Bubbletea* (Charm) que consulta a API local do worker (`/state` e `/metrics`) para exibição em tempo real. |

---

### 2. Plano de Implementação Técnica

#### Fase 1: Adaptação do Servidor SMTP em Go (`worker/incoming_server.go`)

1. **Regras de Anti-Open Relay**:
   - Inserir verificações de IP de origem (`security.allowed_source_ips`) e domínios permitidos (`security.allowed_sender_domains`) dentro do manipulador `Rcpt` do servidor SMTP em Go.
   - Criar uma chamada HTTP local para o Symfony backend para obter a lista dinâmica de políticas de segurança configuradas na console.
2. **Integração com Active Directory (SMTP AUTH)**:
   - Configurar o método `Auth` do SMTP Server do Go para realizar uma chamada para a API local do Symfony:

     ```go
     // Exemplo de integração no worker
     err := CallLocalApi(ctx, "POST", "/auth/smtp", map[string]string{
         "username": username,
         "password": password,
     }, &authResult)
     ```

   - No Symfony, implementar o `SmtpAuthProvider` que conecta ao servidor AD LDAP configurado no `.env`.

#### Fase 2: Identidade Visual Neogrid no Console SvelteKit

1. Customizar as variáveis e tokens do CSS customizado para aplicar a paleta de cores Neogrid:

   ```css
   /* frontend/src/routes/app.css */
   :root {
       --color-primary: #FF5A00;        /* Laranja Neogrid */
       --color-primary-hover: #E04F00;
       --color-bg-dark: #001E3C;        /* Azul Escuro Neogrid */
       --color-text-on-dark: #FFFFFF;
   }
   ```

2. Substituir o logo padrão do console pelo logo Neogrid (codificado em SVG inline no layout do SvelteKit).

#### Fase 3: Instrumentação do OpenTelemetry (OTEL) no Go Worker

1. Instalar as dependências do OpenTelemetry em Go (`go.opentelemetry.io/otel` e `go.opentelemetry.io/otel/exporters/otlp/otlptrace/otlptracehttp`).
2. Configurar o Tracer Provider global em `worker/main.go` apontando para o endpoint do Collector configurado nas variáveis de ambiente.
3. Propagar o `trace_id` e criar spans ao extrair e-mails da fila e realizar a conversação SMTP em `worker/send.go`.

---

### 3. Vantagens da Adaptação

* **Escalabilidade:** A stack em Go + PostgreSQL permite rodar múltiplos workers distribuídos em cluster, enquanto o SQLite limitaria a concorrência a uma única instância de disco.
- **Segurança:** Centralização do controle de acesso, logs de auditoria detalhados e facilidade de renovação de credenciais via console administrativa.
- **Manutenabilidade:** SvelteKit + Symfony fornece uma arquitetura desacoplada e amplamente testável se comparada a scripts acoplados em Python FastAPI.
