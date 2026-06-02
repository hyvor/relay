# Hyvor Relay

O [Hyvor Relay](https://relay.hyvor.com) é uma API de e-mail auto-hospedada (self-hosted) e de código aberto para desenvolvedores. Ele utiliza SMTP para enviar e-mails usando sua própria infraestrutura de servidores e IPs. Foi projetado para ser simples de instalar, fácil de gerenciar e monitorar, e poderoso o suficiente para enviar milhões de e-mails diários de forma escalável.

<p align="center">
  <a href="https://relay.hyvor.com">
    <img src="https://hyvor.com/img/logo.png" alt="Logo do Hyvor Relay" width="130"/>
  </a>
</p>

<p align="center">
  <a href="https://relay.hyvor.com">
    API de E-mail para Desenvolvedores
  </a>
    <span> | </span>
    <a href="https://relay.hyvor.com/hosting">
    Documentação de Auto-Hospedagem
  </a>
    <span> | </span>
    <a href="https://relay.hyvor.com/docs">
    Documentação do Produto
  </a>
</p>

---

## 1. Funcionalidades Principais

- **Auto-Hospedado (Self-Hosted)**: Suporte a deploys descomplicados utilizando Docker Compose ou Docker Swarm.
- **Verificações de Saúde (Health Checks)**: Múltiplos testes internos para garantir a melhor entrega e reputação de IPs.
- **API de Envio simples**: Envio de e-mails por meio de uma API REST limpa em JSON.
- **Histórico e Conversações SMTP**: Visualização dos logs de envio e conversações SMTP detalhadas das mensagens por até 30 dias.
- **Multi-Tenancy**: Suporte a múltiplas contas/organizações com controle de acesso baseado em escopos.
- **Gerenciamento de Projetos**: Organização em múltiplos projetos isolados dentro de cada organização.
- **Isolamento de Filas**: Duas filas de prioridade (transacional e distribucional) para blindar a reputação do IP.
- **Retentativas e Greylisting**: Gerenciamento automático de retentativas de envio inteligente baseadas em tempos exponenciais.
- **Tratamento de Bounces**: Captura e parsing automático de e-mails retornados (bounces).
- **Feedback Loops (FBL)**: Integração com provedores para lidar com reclamações de spam (ARF).
- **Lista de Supressão Automática**: Bloqueio preventivo de envio para e-mails que registraram bounces persistentes ou reclamações.
- **Automação de DNS**: Servidor DNS autoritativo interno para delegar registros DKIM, SPF e DMARC automaticamente.
- **Webhooks**: Recebimento de callbacks HTTP contendo eventos em tempo real (envios, entregas, bounces, etc.).
- **Escalabilidade**: Facilidade para adicionar novos servidores de envio e múltiplos IPs.
- **Observabilidade**: Métricas Prometheus + dashboards Grafana pré-configurados, com tracing distribuído opcional via **OpenTelemetry (OTLP/HTTP)** no worker Go.
- **Anti-Open-Relay**: Allowlist de IPs de origem (CIDR) e domínios remetentes aplicada no servidor SMTP de entrada.
- **SMTP AUTH corporativo**: Delegação opcional da autenticação SMTP ao backend Symfony, permitindo validar credenciais contra **Active Directory / LDAP**.
- **Customização visual (white-label)**: Tokens CSS sobrescritíveis em `frontend/src/routes/app.css` para aplicar paletas de marca (ex.: tema Neogrid laranja/azul incluso).

---

## 2. Tecnologias e Frameworks Utilizados

O projeto utiliza uma arquitetura híbrida dividida em três componentes principais rodando em contêineres:

- **Backend (API)**: PHP 8.4+ rodando com o framework **Symfony 7.4** e **Doctrine ORM**.
- **Worker (Serviços em Go)**: **Go 1.25**, compilado como um único binário, responsável pela fila concorrente de envios, servidor SMTP local de bounces, webhook listener e servidor DNS. Instrumentado com **OpenTelemetry** (`go.opentelemetry.io/otel`) e exportador **Prometheus** nativo.
- **Frontend (Console)**: **Svelte 5**, **SvelteKit** e TypeScript integrados ao [**Hyvor Design System**](https://github.com/hyvor/design).
- **Banco de Dados**: **PostgreSQL** para dados persistentes e filas transacionais (`FOR UPDATE SKIP LOCKED`).
- **Roteamento & Execução**: **FrankenPHP (Caddy)** para servir a API HTTP e arquivos estáticos compilados do frontend.

---

## 3. Instalação e Configuração

### Pré-requisitos
- **Docker** e **Docker Compose** instalados na máquina host.

### A. Desenvolvimento Local (Ambiente de Testes)

1. Configure o ambiente de desenvolvimento padrão [hyvor/dev](https://github.com/hyvor/dev) para inicializar o banco de dados Postgres comum e o Traefik.
2. Inicie a stack do Hyvor Relay executando o comando na raiz do projeto:
   ```bash
   ./run relay
   ```
3. Acesse o Console de Desenvolvimento em: `https://relay.hyvor.localhost`
4. Execute as migrações e popule o banco de dados com dados de simulação (isso criará um projeto padrão com a chave de API `test-api-key`):
   ```bash
   docker compose exec -it backend bash -c "bin/console dev:reset --seed"
   ```

### B. Instalação em Produção (Self-Hosted)

Para produção, o Hyvor Relay fornece modelos de deployment na pasta `/deploy`.

1. Copie o arquivo de exemplo de ambiente `.env` na raiz da pasta `backend/.env` ou configure no seu gerenciador de segredos.
2. Defina os segredos obrigatórios listados abaixo:
   - `APP_ENV=prod`
   - `APP_SECRET`: Chave secreta de 32 bytes gerada por `openssl rand -base64 32`
   - `DATABASE_URL`: String de conexão com o banco PostgreSQL.
   - `WEB_URL`: A URL pública onde a API e o Console estarão acessíveis (ex: `https://relay.seu-dominio.com`).
   - `INSTANCE_DOMAIN`: O domínio de e-mail usado para servidores SMTP (ex: `mail.relay.seu-dominio.com`).
   - Configurações do OpenID Connect (OIDC) para autenticação do console.
   - *(Opcional)* `OTEL_EXPORTER_OTLP_ENDPOINT` e `OTEL_SERVICE_NAME` no worker para habilitar tracing distribuído. Quando ausentes, o tracer roda em modo no-op.
   - *(Opcional)* Políticas anti-open-relay e SMTP AUTH via Symfony são lidas do payload `/api/local/state` (`security.allowedSourceIps`, `security.allowedSenderDomains`, `security.smtpAuthViaSymfony`). Quando vazias, mantêm o comportamento legado.
3. Escolha uma das pastas de implantação:
   - **`deploy/easy`**: Uma estrutura contendo o PostgreSQL e o Relay em uma única receita.
   - **`deploy/prod`**: Configuração recomendada para Docker Compose independente conectando a um Postgres externo.
4. Execute o deploy:
   ```bash
   docker compose -f deploy/prod/compose.yaml up -d
   ```

---

## 4. Exemplos de Uso

### Enviar um e-mail via API REST (cURL)
Use a chave de API gerada na interface administrativa para realizar chamadas REST.

```bash
curl -X POST https://relay.hyvor.localhost/api/console/sends \
     -H "Authorization: Bearer test-api-key" \
     -H "Content-Type: application/json" \
     -d '{
           "from": "contato@seu-dominio.com",
           "to": "destinatario@exemplo.com",
           "subject": "Boas-vindas ao Hyvor Relay!",
           "body_text": "Olá! Este é um e-mail de teste enviado usando a API REST do Hyvor Relay."
         }'
```

### Testar cenários de Bounce com o simulador local
Você pode simular falhas temporárias ou permanentes de e-mail subindo o contêiner do simulador incluído:
```bash
# 1. Inicie o simulador
docker compose up -d simulator

# 2. Dispare um e-mail de teste para um endereço simulado
# E-mails para "accept@simulator.net" serão aceitos.
# E-mails para "tempfail@simulator.net" simularão falhas temporárias (greylisting/deferral).
# E-mails para "missing@simulator.net" simularão bounces permanentes (550 User Unknown).
```

### Aplicar tema visual customizado (ex.: Neogrid)
Os tokens de cor do console SvelteKit são centralizados em `frontend/src/routes/app.css`. Para aplicar uma paleta corporativa, sobrescreva as variáveis CSS:
```css
:root {
    --color-primary: #FF5A00;        /* Laranja Neogrid */
    --color-bg-dark:  #001E3C;       /* Azul escuro Neogrid */
    --accent:         #FF5A00 !important; /* override do @hyvor/design */
}
```

### Habilitar tracing OpenTelemetry no worker
```bash
export OTEL_EXPORTER_OTLP_ENDPOINT="http://otel-collector:4318"
export OTEL_SERVICE_NAME="hyvor-relay-worker"
# spans gerados: smtp.send, smtp.send_to_host (atributos: rcpt_domain, mx_host, ip, queue, try_count)
```

---

## 5. Contribuição

Contribuições são extremamente bem-vindas! Se você deseja corrigir um bug ou implementar uma nova funcionalidade, siga as diretrizes abaixo:

### Diretrizes de Estilo de Código
- **Backend (PHP)**: Siga as recomendações de estilo PSR-12. Certifique-se de que o código passa nas validações estáticas do PHPStan (`composer phpstan` na pasta backend) e que todos os testes unitários do PHPUnit estejam passando.
- **Worker (Go)**: Mantenha o formato oficial da linguagem executando `go fmt ./...`. Certifique-se de documentar e validar acessos concorrentes a dados usando mutexes apropriados.
- **Frontend (SvelteKit)**: Formate o código com `npm run format` (Prettier) e garanta que não haja erros de lint rodando `npm run lint` na pasta frontend.

### Processo de Pull Request
1. Faça um **Fork** do repositório oficial.
2. Crie uma branch para sua modificação: `git checkout -b feature/minha-melhoria`
3. Commit suas alterações com mensagens claras e objetivas.
4. Envie a branch para o seu fork: `git push origin feature/minha-melhoria`
5. Abra um **Pull Request** detalhando o escopo da sua mudança e fornecendo instruções sobre como testá-la.

---

## 6. Roadmap

Veja abaixo os próximos passos planejados para o Hyvor Relay:

- [x] **Envio via SMTP** com pool concorrente em Go.
- [x] **Tracing OpenTelemetry** no worker (spans `smtp.send` / `smtp.send_to_host`).
- [x] **Anti-Open-Relay**: allowlist de IP/CIDR e domínios remetentes no servidor SMTP de entrada.
- [~] **SMTP AUTH via Active Directory / LDAP**: cliente Go pronto (delega a `/api/local/auth/smtp`); endpoint Symfony pendente.
- [ ] **Roteamento de e-mails recebidos (Incoming email routing)**: Receber e encaminhar e-mails recebidos para servidores internos via webhook ou SMTP.
- [ ] **IPs Dedicados por Usuário**: Possibilidade de associar IPs de saída específicos a organizações ou projetos específicos.
- [ ] **Seletores DKIM Customizados**: Permitir cadastrar múltiplos seletores DKIM por domínio nas configurações.
- [ ] **Otimizações Concorrentes**: Refatoração interna de concorrência nos loops de resolução MX e nos serviços autoritativos de DNS do worker em Go.
- [ ] **TUI em Go (Bubbletea)**: CLI leve consultando `/state` e `/metrics` do worker para inspeção em tempo real.

---

## 7. Contato e Suporte

Caso tenha dúvidas, precise de suporte técnico ou queira interagir com outros usuários da stack:

- **Fórum da Comunidade**: [HYVOR Community](https://hyvor.community)
- **Servidor no Discord**: [Discord da HYVOR](https://hyvor.com/go/discord)
- **Site Oficial**: [relay.hyvor.com](https://relay.hyvor.com)

---

## 8. Licença

O Hyvor Relay é distribuído sob a licença **AGPL-3.0 (GNU Affero General Public License v3)**. 

Também oferecemos [licenciamento comercial (enterprise)](https://hyvor.com/enterprise) para organizações que necessitam de suporte dedicado, não desejam expor modificações de código interno ou desejam operar fora das obrigações da licença AGPLv3. Veja as dúvidas frequentes em [Hosting License FAQ](https://hyvor.com/docs/hosting-license).

---

![HYVOR Banner](https://raw.githubusercontent.com/hyvor/relay/refs/heads/main/meta/assets/hyvor-banner.svg)

Copyright © HYVOR. HYVOR name and logo are trademarks of HYVOR, SARL.
