# Relatório de Análise Detalhada do Projeto: Hyvor Relay

O **Hyvor Relay** é uma API de e-mail auto-hospedada (self-hosted) e de código aberto voltada para desenvolvedores. Ela foi projetada para enviar milhões de e-mails diários utilizando a própria infraestrutura do usuário por meio de servidores SMTP integrados, visando simplicidade de gerenciamento, observabilidade e isolamento de reputação de IP.

---

## 1. Relatório Estruturado do Projeto

### Tecnologias, Funcionalidades e Integrações

A tabela a seguir resume as tecnologias centrais, as principais funcionalidades e as integrações implementadas em cada componente do projeto:

| Componente / Diretório | Tecnologias Utilizadas | Funcionalidades Principais | Integrações |
| :--- | :--- | :--- | :--- |
| **API Backend**<br>`c:\Tools\Code\relay\backend` | PHP 8.4+, Symfony 7.4, Doctrine ORM, FrankenPHP (Caddy-based runtime), PHPUnit, Prometheus Client, PHPStan | - Gerenciamento de multi-tenancy (organizações e projetos)<br>- Gerenciamento de usuários e chaves de API com escopos de acesso<br>- Controle e armazenamento de logs de supressão (bounces, reclamações)<br>- Agendador interno de tarefas e filas persistentes<br>- Métricas e estatísticas gerais de envios | - Banco de dados PostgreSQL (persistência geral)<br>- API local do Go Worker (`/api/local/*`) para controle de estados<br>- Servidores OpenID Connect (OIDC) para autenticação |
| **Email Worker**<br>`c:\Tools\Code\relay\worker` | Go 1.25, `github.com/miekg/dns`, `github.com/joho/godotenv`, `html` | - Consumo da fila de e-mails no PostgreSQL e envio concorrente via SMTP<br>- Resolução de registros MX com cache local em memória<br>- Servidor SMTP de entrada para recebimento de bounces e relatórios de spam (FBL)<br>- Analisador nativo de Bounces (RFC 3464) e Complaints (ARF - RFC 5965)<br>- Servidor DNS autoritativo interno para DKIM, SPF e DMARC<br>- Servidor de métricas Prometheus integrado | - Banco de dados PostgreSQL (Consumo de filas e gravação de tentativas de envio)<br>- API Symfony local para reportar status e receber o estado atualizado<br>- Redes externas SMTP para entrega de e-mails<br>- Webhooks externos para notificação de eventos em tempo real |
| **Console Frontend**<br>`c:\Tools\Code\relay\frontend` | Svelte 5, SvelteKit, TypeScript, Vite, `@hyvor/design`, `@hyvor/icons`, Chart.js, Dayjs | - Painel Administrativo Global (Sudo Dashboard) para administradores<br>- Console do usuário para acompanhamento de envios, métricas, supressões e chaves de API<br>- Visualização do log completo das conversações SMTP realizadas pelos e-mails enviados | - API Console do Symfony (`/api/console/*`) |
| **Benchmark**<br>`c:\Tools\Code\relay\benchmark` | JavaScript (k6), Docker Compose | - Script de teste de carga e benchmarking concorrente para validar o desempenho da API de envios em larga escala | - API de envio de e-mails (`/api/console/sends`) |
| **Deploy**<br>`c:\Tools\Code\relay\deploy` | Shell Script (Bash/Sh), Docker Compose | - Automação do empacotamento de releases via script `zip.sh`<br>- Orquestração de contêineres Docker para ambientes de desenvolvimento local ("easy") e produção ("prod") | - Docker Engine e Docker Swarm |
| **Infraestrutura / Monitoramento**<br>`c:\Tools\Code\relay\meta` | Prometheus, Grafana, Alertmanager, Caddy, Supervisor | - Definição de regras de alerta e roteamento de notificações do Alertmanager<br>- Painel de monitoramento do Grafana para análise detalhada de desempenho<br>- Roteamento Caddy e controle de múltiplos processos em execução no container (FrankenPHP, Workers, Agendadores) via Supervisor | - Prometheus Server, Grafana, Caddy Server, supervisord |

---

## 2. Análise Detalhada dos Diretórios do Projeto

Abaixo é apresentada a análise técnica detalhada de cada diretório contendo códigos do projeto:

### 1. `c:\Tools\Code\relay\backend`
* **Linguagem de Programação Predominante**: PHP
* **Descrição da Funcionalidade Principal**:
  Controla o núcleo administrativo da aplicação por meio do framework Symfony. É responsável por expor as APIs de gerenciamento do console administrativo (`/api/console/*`), a API local para sincronização de estado com o worker de e-mails (`/api/local/*`), autenticar usuários, validar domínios de envio, decodificar anexos e gerenciar o histórico de envios e suppressões no banco de dados.
* **Possíveis Problemas de Segurança ou Vulnerabilidades**:
  * **Vulnerabilidade 1: Confiança Excessiva em Proxies na Autenticação Local (Severidade: Média)**
    * *Descrição*: O `LocalAuthorizationListener` restringe requisições para `/api/local/*` validando se o IP do cliente é `127.0.0.1` ou `::1`. Contudo, a aplicação Symfony está configurada com a diretiva `trusted_proxies` que confia em faixas de IP privadas inteiras (`10.0.0.0/8`, `172.16.0.0/12`, etc.) via `.env`. Se o proxy reverso público (ou balanceador de carga) não estiver limpando o cabeçalho `X-Forwarded-For` em requisições externas, um atacante externo pode falsificar esse cabeçalho enviando `X-Forwarded-For: 127.0.0.1` e burlar a verificação de IP para chamar rotas locais críticas de status e alteração de estado.
    * *Sugestão de Correção*: Implementar uma verificação de token de segurança compartilhado (ex: `GO_SYMFONY_KEY`) configurado via variável de ambiente, exigindo-o como um cabeçalho personalizado em todas as requisições para a API local.
  * **Vulnerabilidade 2: Abortamento Prematuro de Lotes de Bounce (Severidade: Média)**
    * *Descrição*: No arquivo `IncomingMailService.php`, o método `handleIncomingBounce` processa uma lista de destinatários em um loop. Se um único destinatário no DSN tiver uma ação diferente de `failed` (ex: `delayed`), ou se o e-mail/destinatário associado não for localizado no banco, a execução do método é abortada por completo com a palavra-chave `return;`. Isso impede que os destinatários subsequentes da lista (que podem ser bounces legítimos e válidos) sejam processados e adicionados à lista de supressão.
    * *Sugestão de Correção*: Substituir as instruções `return;` por `continue;` no loop de destinatários em `handleIncomingBounce`.
* **Sugestões de Otimização e Melhoria**:
  * **Otimização de Buscas (Alta Prioridade)**: No arquivo `SendService.php`, a função `getSends` monta consultas SQL usando filtros `LIKE` com wildcards bilaterais (`%Search%`) em campos de e-mail e assunto (`from_address`, `address`, `subject`). Essas consultas provocam scans de tabela completos (Full Table Scans), o que causará extrema lentidão no banco de dados quando houver milhões de registros de e-mail. Recomenda-se criar índices baseados em extensões de trigrama (`pg_trgm`) ou implementar Busca Textual (Full-Text Search) do PostgreSQL.
  * **Redundância de Validação de Domínio**: Centralizar e simplificar a resolução de domínios cadastrados no backend para evitar consultas duplicadas à tabela `domains` a cada chamada de validação de envio.

---

### 2. `c:\Tools\Code\relay\worker`
* **Linguagem de Programação Predominante**: Go (Golang)
* **Descrição da Funcionalidade Principal**:
  Serviço standalone de alta performance escrito em Go. Ele encapsula o servidor DNS autoritativo que automatiza as regras SPF/DKIM, um servidor SMTP de entrada para capturar bounces/FBL, o worker que consome a fila de envios do PostgreSQL e envia os e-mails via conexões diretas SMTP SMTP e um sistema que despacha webhooks para aplicações clientes notificando as entregas e falhas de e-mail.
* **Possíveis Problemas de Segurança ou Vulnerabilidades**:
  * **Vulnerabilidade 1: Data Race Crítico no Cache MX (Severidade: Alta)**
    * *Descrição*: No arquivo `worker/mx.go`, a função `getMxHostsFromDomain` realiza leitura direta no mapa `mxCache.data` de forma concorrente sem adquirir o lock `mxCache.mu` (que só é adquirido na escrita). Como múltiplas goroutines de workers de e-mail acessam essa função simultaneamente sob carga, o Go causará um crash fatal do processo com o erro `"fatal error: concurrent map read and map write"`.
    * *Sugestão de Correção*: Alterar o mutex de `mxCache` para um `sync.RWMutex` e fazer lock de leitura (`RLock()`) na verificação do cache.
  * **Vulnerabilidade 2: Concorrência Insegura no Servidor DNS (Severidade: Alta)**
    * *Descrição*: No arquivo `worker/dns.go`, a variável `s.dnsRecords` é lida de forma concorrente em `findDnsRecordsByTypeAndHost` diretamente nas goroutines de requisição UDP do pacote `dns`. Quando a API local do Symfony atualiza a lista de registros chamando o método `Set()`, o slice é rescrito sem sincronização, gerando problemas de concorrência e corrupção de memória ou pânico em execução.
    * *Sugestão de Correção*: Implementar proteção com `sync.RWMutex` na struct `DnsServer` envolvendo o acesso à variável `s.dnsRecords`.
  * **Vulnerabilidade 3: Conexões Travadas no Cliente HTTP de Webhook (Severidade: Média)**
    * *Descrição*: No arquivo `worker/webhooks.go`, o cliente de requisições `httpClient` é inicializado sem limite de timeout (`&http.Client{}`). Se a URL de um webhook configurado por um cliente apontar para um servidor que aceita conexões mas se recusa a responder, a goroutine do worker ficará travada para sempre, vazando recursos e congelando a entrega de outros webhooks.
    * *Sugestão de Correção*: Definir um timeout máximo explícito no cliente HTTP (ex: `Timeout: 8 * time.Second`).
  * **Vulnerabilidade 4: Server-Side Request Forgery - SSRF (Severidade: Média)**
    * *Descrição*: O worker despacha webhooks para qualquer URL fornecida pelo tenant. Não há validação para impedir chamadas HTTP contra IPs privados ou de loopback (`localhost`, `127.0.0.1`, subredes do Docker e IPs de metadados da nuvem como `169.254.169.254`). Isso permite que atacantes escaneiem ou manipulem serviços internos.
    * *Sugestão de Correção*: Implementar uma rotina de checagem da URL antes do envio para rejeitar requisições cujos IPs de destino sejam reservados ou pertençam a redes locais e privadas.
* **Sugestões de Otimização e Melhoria**:
  * **Uso de sync.Pool para Payloads (Média Prioridade)**: A leitura constante de e-mails em formato bruto (`raw`) na fila de envios gera muita alocação de memória. Utilizar buffers de memória reutilizáveis reduz a pressão sobre o Garbage Collector sob volumetria alta.
  * **Modularização do Diretório (Média Prioridade)**: Mover o código de servidores e lógicas específicas da raiz do diretório `worker` para pacotes estruturados (ex: `pkg/dns`, `pkg/smtp`, `pkg/webhook`), organizando melhor o código e facilitando os testes unitários.

---

### 3. `c:\Tools\Code\relay\frontend`
* **Linguagem de Programação Predominante**: TypeScript / Svelte
* **Descrição da Funcionalidade Principal**:
  Aplicação cliente que compila para arquivos estáticos integrados ao Caddy. Utiliza o framework SvelteKit com o compilador Svelte 5 para oferecer uma interface veloz e interativa para gerenciamento do Hyvor Relay, gráficos em tempo real de estatísticas de e-mail e renderização de logs de conversação SMTP.
* **Possíveis Problemas de Segurança ou Vulnerabilidades**:
  * **Exposição de Dados por Falta de Sanitização em Logs (Severidade: Baixa)**
    * *Descrição*: A renderização de logs de conversação SMTP pode conter payloads ou dados maliciosos injetados nos headers do e-mail. Se renderizados incorretamente no painel do console, podem propiciar ataques de injeção de script (XSS).
    * *Sugestão de Correção*: Garantir que todos os logs e cabeçários exibidos sejam devidamente escapados pelo motor do Svelte, e implementar cabeçalhos de CSP (Content Security Policy) no servidor Caddy.
* **Sugestões de Otimização e Melhoria**:
  * **Carregamento Preguiçoso (Lazy Loading) de Gráficos**: Carregar dinamicamente a biblioteca Chart.js apenas quando o usuário navegar para a tela de Analytics, reduzindo o tamanho do bundle JavaScript inicial do Console.
  * **Validação de Entrada Consistente**: Adicionar regras mais rígidas no frontend para formatos de domínios e chaves de API, economizando requisições desnecessárias para o backend Symfony.

---

### 4. `c:\Tools\Code\relay\benchmark`
* **Linguagem de Programação Predominante**: JavaScript (usando a ferramenta de testes k6)
* **Descrição da Funcionalidade Principal**:
  Contém o arquivo `benchmark.js` configurado com regras da ferramenta **k6** para disparar requisições concorrentes artificiais simulando 10 usuários virtuais contínuos enviando e-mails à API a fim de medir métricas de latência e taxa de transferência.
* **Possíveis Problemas de Segurança ou Vulnerabilidades**:
  * **Credenciais no Código (Severidade: Baixa)**: O token de autenticação está vazio (`'Authorization': 'Bearer'`), mas deve-se assegurar que chaves de API reais de teste não sejam acidentalmente publicadas no arquivo.
* **Sugestões de Otimização e Melhoria**:
  * **Parametrização por Variáveis de Ambiente**: Alterar o arquivo para ler a URL de envio (`WEB_URL`) e a chave de API de teste (`API_KEY`) diretamente de variáveis de ambiente do k6 (`__ENV`), evitando a necessidade de alterar fisicamente o script antes de testar em diferentes servidores.
  * **Adição de Asserções (Checks)**: Implementar asserções automáticas no script k6 para validar se as respostas do backend retornam status HTTP `200` e se o tempo de resposta do P95 está abaixo do esperado.

---

### 5. `c:\Tools\Code\relay\deploy`
* **Linguagem de Programação Predominante**: Shell Script (Bash/Sh) e arquivos YAML
* **Descrição da Funcionalidade Principal**:
  Orquestra as configurações de Docker Compose necessárias para colocar a pilha de serviços do Hyvor Relay em funcionamento. Contém o diretório `prod` (configurações prontas para ambiente produtivo atrás de proxy) e `easy` (configuração contendo dependências simplificadas para deploy imediato), além do script `zip.sh` responsável por atualizar tags de imagem e compactar o diretório de entrega.
* **Possíveis Problemas de Segurança ou Vulnerabilidades**:
  * **Vazamento de Variáveis de Ambiente e Segredos (Severidade: Média)**
    * *Descrição*: Arquivos `.env` de configuração contêm chaves criptográficas (`APP_SECRET`) e credenciais de banco de dados (`DATABASE_URL`). Caso sejam copiados incorretamente ou gerados na pasta de deploy exposta publicamente no servidor, podem revelar segredos cruciais.
    * *Sugestão de Correção*: Garantir que arquivos com dados confidenciais tenham permissões de leitura restritas e usar o suporte a Docker Secrets para carregar senhas em produção de forma isolada.
* **Sugestões de Otimização e Melhoria**:
  * **Substituição de Edição sed por Templates (Média Prioridade)**: O script `zip.sh` edita diretamente os arquivos YAML com comandos `sed` para substituir a versão da tag da imagem. Seria mais limpo e seguro utilizar variáveis de ambiente padrões do Docker Compose (`hyvor/relay:${IMAGE_TAG:-latest}`) no lugar de alterar arquivos de configuração em disco de forma destrutiva.

---

### 6. `c:\Tools\Code\relay\meta`
* **Linguagem de Programação Predominante**: Não possui (contém arquivos de configuração JSON, YAML e Dockerfiles)
* **Descrição da Funcionalidade Principal**:
  Centraliza os arquivos de infraestrutura da aplicação. Inclui recursos de monitoramento da stack (painel do Grafana, regras do Alertmanager), regras para imagens Docker de desenvolvimento e produção (Caddyfile, supervisord.conf, scripts de inicialização) e configurações de pipelines de CI.
* **Possíveis Problemas de Segurança ou Vulnerabilidades**:
  * **Exposição de Métricas sem Autenticação (Severidade: Média)**
    * *Descrição*: Se a porta ou a rota `/api/local/metrics` do Prometheus exportada pelo backend/worker estiver exposta na internet sem nenhuma restrição, dados estatísticos sensíveis da infraestrutura e tráfego de e-mails poderão ser monitorados por terceiros.
    * *Sugestão de Correção*: Restringir o acesso a essas rotas no Caddy ou aplicar Basic Auth para assegurar que apenas servidores de monitoramento Prometheus coletem as métricas.
* **Sugestões de Otimização e Melhoria**:
  * **Otimização de Logs do supervisord (Média Prioridade)**: Atualmente, os logs do Supervisor estão direcionados para `/dev/null` e `/dev/stdout`. Recomenda-se configurar limites e rotação de logs para os processos de backend e worker, a fim de evitar sobrecarga no armazenamento ou perda de rastreabilidade de eventos históricos de erro do daemon.
