# Skill: Instalação e Configuração de Certificados SSL

## Visão Geral
Este skill ensina como instalar certificados SSL emitidos pelo acme-ng em servidores web e outros serviços.

## Quando Usar
- Instalar certificados após emissão
- Configurar renovação automática com reload do serviço
- Copiar certificados para locais seguros
- Preservar permissões e ownership dos arquivos

## ⚠️ IMPORTANTE: Por que usar --install-cert?

**NÃO use os arquivos diretamente de `~/.acme-ng/`** porque:
- A estrutura interna pode mudar em atualizações futuras
- Os arquivos são para uso interno do acme-ng
- Permissões podem não estar corretas para seu serviço
- Não há garantia de compatibilidade futura

**SEMPRE use:**
```bash
acme-ng --install-cert -d example.com [OPÇÕES]
```

## Comando de Instalação

### Sintaxe Básica
```bash
acme-ng --install-cert -d example.com \
  --cert-file      /caminho/para/cert.pem \
  --key-file       /caminho/para/key.pem \
  --ca-file        /caminho/para/ca.pem \
  --fullchain-file /caminho/para/fullchain.pem \
  --reloadcmd      "comando para recarregar serviço"
```

### Parâmetros Disponíveis

| Parâmetro | Descrição | Obrigatório |
|-----------|-----------|-------------|
| `-d` | Domínio do certificado | ✅ Sim |
| `--cert-file` | Caminho para o certificado | ❌ Não |
| `--key-file` | Caminho para a chave privada | ❌ Não |
| `--ca-file` | Caminho para CA intermediário | ❌ Não |
| `--fullchain-file` | Certificado completo (cert + CA) | ❌ Não |
| `--reloadcmd` | Comando para recarregar serviço | ❌ Não* |

\* Recomendado para renovação automática

## Arquivos do Certificado

### O que cada arquivo contém:

**cert-file** (`cert.pem`):
- Apenas o certificado do domínio
- Não inclui cadeia de confiança
- Tamanho: ~3-5KB

**key-file** (`key.pem`):
- Chave privada do certificado
- **MANTER SEGURO E COM PERMISSÕES RESTRITAS**
- Tamanho: varia (ECC ~200 bytes, RSA ~1.6KB)

**ca-file** (`ca.pem`):
- Autoridade certificadora intermediária
- Cadeia de confiança
- Tamanho: ~3-5KB

**fullchain-file** (`fullchain.pem`):
- Certificado completo = cert.pem + ca.pem
- **Mais comum para servidores web**
- Tamanho: ~6-10KB

## Exemplos por Servidor

### 1️⃣ Apache

#### Debian/Ubuntu
```bash
acme-ng --install-cert -d example.com \
  --cert-file      /etc/ssl/certs/example.com.crt \
  --key-file       /etc/ssl/private/example.com.key \
  --fullchain-file /etc/ssl/certs/example.com-fullchain.crt \
  --reloadcmd      "systemctl reload apache2"
```

#### RHEL/CentOS/Fedora
```bash
acme-ng --install-cert -d example.com \
  --cert-file      /etc/pki/tls/certs/example.com.crt \
  --key-file       /etc/pki/tls/private/example.com.key \
  --fullchain-file /etc/pki/tls/certs/example.com-fullchain.crt \
  --reloadcmd      "systemctl reload httpd"
```

#### Configuração VirtualHost Apache
```apache
<VirtualHost *:443>
    ServerName example.com
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/example.com.crt
    SSLCertificateKeyFile /etc/ssl/private/example.com.key
    SSLCertificateChainFile /etc/ssl/certs/example.com-fullchain.crt
</VirtualHost>
```

### 2️⃣ Nginx

#### Configuração Padrão
```bash
acme-ng --install-cert -d example.com \
  --key-file       /etc/nginx/ssl/example.com.key \
  --fullchain-file /etc/nginx/ssl/example.com.fullchain.pem \
  --reloadcmd      "systemctl reload nginx"
```

#### Configuração Nginx
```nginx
server {
    listen 443 ssl http2;
    server_name example.com;

    ssl_certificate /etc/nginx/ssl/example.com.fullchain.pem;
    ssl_certificate_key /etc/nginx/ssl/example.com.key;

    # Configurações SSL recomendadas
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers on;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256;
}

# Redirecionar HTTP para HTTPS
server {
    listen 80;
    server_name example.com;
    return 301 https://$server_name$request_uri;
}
```

### 3️⃣ HAProxy

#### Instalação
```bash
# HAProxy precisa do certificado e key juntos
acme-ng --install-cert -d example.com \
  --key-file       /etc/haproxy/certs/example.com.key \
  --fullchain-file /etc/haproxy/certs/example.com.crt \
  --reloadcmd      "systemctl reload haproxy"

# Combinar em um único arquivo PEM (formato HAProxy)
cat /etc/haproxy/certs/example.com.key \
    /etc/haproxy/certs/example.com.crt \
    > /etc/haproxy/certs/example.com.pem
    
chmod 600 /etc/haproxy/certs/example.com.pem
```

#### Configuração HAProxy
```haproxy
frontend https_front
    bind *:443 ssl crt /etc/haproxy/certs/example.com.pem
    default_backend web_servers
```

### 4️⃣ Dovecot (IMAP/POP3)

#### Instalação
```bash
acme-ng --install-cert -d mail.example.com \
  --key-file       /etc/dovecot/private/mail.example.com.key \
  --fullchain-file /etc/dovecot/certs/mail.example.com.crt \
  --reloadcmd      "systemctl reload dovecot"
```

#### Configuração Dovecot
```conf
ssl_cert = </etc/dovecot/certs/mail.example.com.crt
ssl_key = </etc/dovecot/private/mail.example.com.key
ssl_min_protocol = TLSv1.2
```

### 5️⃣ Postfix (SMTP)

#### Instalação
```bash
acme-ng --install-cert -d mail.example.com \
  --key-file       /etc/postfix/ssl/mail.example.com.key \
  --fullchain-file /etc/postfix/ssl/mail.example.com.crt \
  --reloadcmd      "systemctl reload postfix"
```

#### Configuração Postfix
```conf
smtpd_tls_cert_file = /etc/postfix/ssl/mail.example.com.crt
smtpd_tls_key_file = /etc/postfix/ssl/mail.example.com.key
smtpd_use_tls = yes
smtpd_tls_security_level = may
```

### 6️⃣ Proxmox VE

#### Instalação
```bash
acme-ng --install-cert -d proxmox.example.com \
  --key-file       /etc/pve/local/pveproxy-ssl.key \
  --fullchain-file /etc/pve/local/pveproxy-ssl.pem \
  --reloadcmd      "systemctl reload pveproxy"
```

### 7️⃣ cPanel/WHM

#### Instalação via UAPI
```bash
acme-ng --install-cert -d example.com \
  --deploy-hook cpanel_uapi
```

### 8️⃣ Docker/Containers

#### Copiar para Container
```bash
# Instalar em diretório montado
acme-ng --install-cert -d example.com \
  --key-file       /srv/docker/nginx/ssl/example.com.key \
  --fullchain-file /srv/docker/nginx/ssl/example.com.fullchain.pem

# Ou copiar manualmente após instalação
cp ~/.acme-ng/example.com/*.key /path/to/volume/
cp ~/.acme-ng/example.com/*.pem /path/to/volume/
```

## Permissões e Segurança

### Permissões Recomendadas

```bash
# Chave privada - apenas root/owner
chmod 600 /etc/ssl/private/example.com.key
chown root:root /etc/ssl/private/example.com.key

# Certificados - leitura para serviços
chmod 644 /etc/ssl/certs/example.com.crt
chmod 644 /etc/ssl/certs/example.com-fullchain.crt
```

### Preservação Automática
O acme-ng preserva automaticamente:
- Ownership existente dos arquivos
- Permissões existentes dos arquivos
- **Dica:** Pré-crie os arquivos com as permissões desejadas

```bash
# Criar arquivos com permissões corretas antes
touch /etc/nginx/ssl/example.com.key
chmod 600 /etc/nginx/ssl/example.com.key
chown www-data:www-data /etc/nginx/ssl/example.com.key

# Depois executar install-cert
acme-ng --install-cert -d example.com \
  --key-file /etc/nginx/ssl/example.com.key \
  ...
```

## Comandos de Reload por Serviço

```bash
# Apache
--reloadcmd "systemctl reload apache2"        # Debian/Ubuntu
--reloadcmd "systemctl reload httpd"          # RHEL/CentOS
--reloadcmd "service apache2 force-reload"

# Nginx
--reloadcmd "systemctl reload nginx"
--reloadcmd "service nginx force-reload"

# HAProxy
--reloadcmd "systemctl reload haproxy"

# Dovecot
--reloadcmd "systemctl reload dovecot"

# Postfix
--reloadcmd "systemctl reload postfix"

# Múltiplos serviços
--reloadcmd "systemctl reload nginx && systemctl reload php-fpm"

# Docker
--reloadcmd "docker restart nginx-container"

# Customizado
--reloadcmd "/opt/scripts/reload-certs.sh"
```

## Casos Especiais

### 1. Múltiplos Domínios (SAN)
```bash
acme-ng --install-cert -d example.com \
  --key-file       /etc/ssl/multi-domain.key \
  --fullchain-file /etc/ssl/multi-domain.fullchain.pem \
  --reloadcmd      "systemctl reload nginx"
```

### 2. Certificado ECC
```bash
acme-ng --install-cert -d example.com --ecc \
  --key-file       /etc/ssl/ecc/example.com.key \
  --fullchain-file /etc/ssl/ecc/example.com.fullchain.pem
```

### 3. Wildcard
```bash
acme-ng --install-cert -d '*.example.com' \
  --key-file       /etc/ssl/wildcard.key \
  --fullchain-file /etc/ssl/wildcard.fullchain.pem \
  --reloadcmd      "systemctl reload nginx"
```

### 4. Sem Reload (Apenas Cópia)
```bash
acme-ng --install-cert -d example.com \
  --key-file       /backup/certs/example.com.key \
  --fullchain-file /backup/certs/example.com.fullchain.pem
  # Sem --reloadcmd
```

### 5. Hook Personalizado
```bash
acme-ng --install-cert -d example.com \
  --key-file       /etc/ssl/example.com.key \
  --fullchain-file /etc/ssl/example.com.fullchain.pem \
  --reloadcmd      "/opt/scripts/custom-reload.sh arg1 arg2"
```

## Deploy Hooks Automatizados

### Usando Deploy Hooks Integrados
```bash
# Nginx
acme-ng --issue -d example.com -w /var/www/html \
  --deploy-hook nginx

# Apache
acme-ng --issue -d example.com -w /var/www/html \
  --deploy-hook apache

# Docker
acme-ng --issue -d example.com -w /var/www/html \
  --deploy-hook docker

# Kubernetes
acme-ng --issue -d example.com --dns dns_cf \
  --deploy-hook kubernetes
```

### Deploy Hook Personalizado
```bash
acme-ng --issue -d example.com -w /var/www/html \
  --deploy-hook "/opt/scripts/deploy-cert.sh"
```

## Verificação e Troubleshooting

### Verificar Instalação
```bash
# Listar certificados instalados
acme-ng --list

# Verificar detalhes do certificado
openssl x509 -in /etc/ssl/certs/example.com.crt -text -noout

# Verificar validade
openssl x509 -in /etc/ssl/certs/example.com.crt -noout -dates

# Testar conexão SSL
openssl s_client -connect example.com:443 -servername example.com
```

### Erros Comuns

**Erro: "Permission denied"**
```bash
# Verificar permissões
ls -la /etc/ssl/private/

# Corrigir ownership
chown www-data:www-data /etc/ssl/private/example.com.key
```

**Erro: "Reload command failed"**
```bash
# Testar comando de reload manualmente
systemctl reload nginx

# Verificar se serviço está rodando
systemctl status nginx
```

**Erro: "File not found"**
```bash
# Criar diretório se não existir
mkdir -p /etc/nginx/ssl

# Pré-criar arquivos
touch /etc/nginx/ssl/example.com.key
```

## Boas Práticas

1. **Sempre use --reloadcmd** para renovação automática
2. **Teste o reload** manualmente antes de automatizar
3. **Backup dos certificados** antes de grandes mudanças
4. **Monitore a renovação** com logs ou notificações
5. **Use fullchain** para máxima compatibilidade
6. **Proteja as chaves privadas** com permissões restritas
7. **Valide após instalação** com openssl ou navegador

## Links Úteis
- Deploy Hooks Wiki: https://github.com/acmesh-official/acme-ng/wiki/deployhooks
- Exemplos de Deploy: https://github.com/acmesh-official/acme-ng/tree/master/deploy
