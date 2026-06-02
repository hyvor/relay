# Skill: Emissão de Certificados SSL com acme-ng

## Visão Geral
Este skill ensina como emitir certificados SSL/TLS gratuitos usando o acme-ng, um cliente ACME escrito em Shell puro.

## Quando Usar
- Emitir certificados SSL para domínios
- Renovar certificados existentes
- Gerenciar certificados wildcard
- Configurar renovação automática

## Comandos Principais

### 1. Verificar Ajuda
```bash
acme-ng --help
```

### 2. Emitir Certificado - Modo Webroot (Recomendado)
```bash
acme-ng --issue -d example.com -w /var/www/html
```

**Domínios Múltiplos (SAN):**
```bash
acme-ng --issue -d example.com -d www.example.com -d api.example.com -w /var/www/html
```

**Parâmetros:**
- `-d`: Domínio principal (obrigatório)
- `-w`: Diretório web root (deve ter permissão de escrita)
- Todos os domínios devem apontar para o mesmo webroot

### 3. Emitir Certificado - Modo Standalone (Porta 80)
```bash
acme-ng --issue --standalone -d example.com -d www.example.com
```

**Requisitos:**
- Requer acesso root/sudo
- Porta 80 deve estar livre
- Útil quando não há servidor web rodando

### 4. Emitir Certificado - Modo TLS-ALPN (Porta 443)
```bash
acme-ng --issue --alpn -d example.com -d www.example.com
```

**Requisitos:**
- Requer acesso root/sudo
- Porta 443 deve estar livre

### 5. Emitir Certificado - Modo Apache
```bash
acme-ng --issue --apache -d example.com -d www.example.com
```

**Requisitos:**
- Requer acesso root/sudo para interagir com Apache
- Não modifica arquivos de configuração do Apache

### 6. Emitir Certificado - Modo Nginx
```bash
acme-ng --issue --nginx -d example.com -d www.example.com
```

**Requisitos:**
- Requer acesso root/sudo para interagir com Nginx
- Configura automaticamente e depois restaura a configuração original

### 7. Certificado Wildcard
```bash
acme-ng --issue -d example.com -d '*.example.com' --dns dns_cf
```

**Notas:**
- Requer validação DNS-01
- Necessário usar API de DNS ou modo manual

## Tipos de Chave

### ECC (Padrão - Recomendado)
```bash
# ECDSA P-256 (padrão)
acme-ng --issue -w /var/www/html -d example.com

# ECDSA P-384
acme-ng --issue -w /var/www/html -d example.com --keylength ec-384

# ECDSA P-521 (não suportado pelo Let's Encrypt ainda)
acme-ng --issue -w /var/www/html -d example.com --keylength ec-521
```

### RSA
```bash
# RSA 2048-bit
acme-ng --issue -w /var/www/html -d example.com --keylength 2048

# RSA 3072-bit
acme-ng --issue -w /var/www/html -d example.com --keylength 3072

# RSA 4096-bit
acme-ng --issue -w /var/www/html -d example.com --keylength 4096
```

## Validação por DNS (DNS-01)

### Usando API de DNS (Automático)
```bash
# Cloudflare
export CF_Key="sdfsdfsdfljlbjkljlkjsdfoiwje"
export CF_Email="user@example.com"
acme-ng --issue --dns dns_cf -d example.com -d '*.example.com'

# AWS Route53
export AWS_ACCESS_KEY_ID="AKIAIOSFODNN7EXAMPLE"
export AWS_SECRET_ACCESS_KEY="wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY"
acme-ng --issue --dns dns_aws -d example.com
```

**Ver todos os provedores DNS suportados:** `dnsapi/` directory

### Modo Manual (DNS sem API)
```bash
# Primeira etapa - Criar registro TXT
acme-ng --issue --dns -d example.com -d www.example.com

# Saída esperada:
# Add the following txt record:
# Domain: _acme-challenge.example.com
# Txt value: 9ihDbjYfTExAYeDs4DBUeuTo18KBzwvTEjUnSwd32-c

# Adicione o registro TXT no seu DNS e aguarde propagação

# Segunda etapa - Verificar
acme-ng --renew -d example.com
```

⚠️ **Aviso:** Modo manual não pode ser renovado automaticamente

## Boas Práticas

### 1. Escolha o Modo Correto
- **Webroot**: Mais seguro, não requer root (se já tiver write access)
- **Standalone**: Quando não há servidor web
- **Apache/Nginx**: Integração direta, mas requer root
- **DNS API**: Ideal para wildcards e automação completa

### 2. Permissões
- acme-ng NÃO requer acesso root/sudo necessariamente
- Pode rodar como usuário normal
- Apenas alguns modos específicos requerem privilégios elevados

### 3. Localização dos Certificados
- Certificados são armazenados em: `~/.acme-ng/<domain>/`
- **NÃO use os arquivos diretamente desta pasta**
- Use `--install-cert` para copiar para localização final

### 4. Renovação Automática
- Certificados são renovados automaticamente a cada 30 dias
- Cron job é configurado automaticamente na instalação
- Notificações podem ser configuradas

## Exemplos Completos

### Exemplo 1: Site Simples
```bash
# Emitir certificado
acme-ng --issue -w /var/www/example.com -d example.com -d www.example.com

# Instalar no Nginx
acme-ng --install-cert -d example.com \
  --key-file /etc/nginx/ssl/example.com.key \
  --fullchain-file /etc/nginx/ssl/example.com.fullchain.pem \
  --reloadcmd "systemctl reload nginx"
```

### Exemplo 2: Múltiplos Domínios
```bash
acme-ng --issue -w /var/www/main \
  -d main.com \
  -d www.main.com \
  -d blog.main.com \
  -d shop.main.com
```

### Exemplo 3: Wildcard com Cloudflare
```bash
export CF_Token="cloudflare_api_token"
acme-ng --issue --dns dns_cf \
  -d example.com \
  -d '*.example.com'
```

### Exemplo 4: Certificado ECC Personalizado
```bash
acme-ng --issue -w /var/www/html \
  -d secure.example.com \
  --keylength ec-384
```

## Solução de Problemas

### Erro: "Port 80 already in use"
- Use outro modo (nginx, apache, ou dns)
- Pare o serviço usando a porta 80 temporariamente
- Use standalone TLS mode (porta 443)

### Erro: "Domain verification failed"
- Verifique se o domínio aponta para o webroot correto
- Confirme que o arquivo de desafio está acessível via HTTP
- Verifique firewalls e redirecionamentos

### Erro: "DNS propagation timeout"
- Aguarde mais tempo para propagação do DNS
- Verifique se o registro TXT foi criado corretamente
- Use `--dnssleep` para aumentar o tempo de espera

## Links Úteis
- Wiki Oficial: https://github.com/acmesh-official/acme-ng/wiki
- DNS API: https://github.com/acmesh-official/acme-ng/wiki/dnsapi
- Modo Manual: https://github.com/acmesh-official/acme-ng/wiki/dns-manual-mode
