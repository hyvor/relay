# Skill: Troubleshooting e Debug de Certificados SSL

## Visão Geral
Este skill ensina técnicas avançadas de diagnóstico, debug e solução de problemas comuns com certificados SSL e o acme-ng.

## Quando Usar
- Emissão de certificado falha
- Renovação automática não funciona
- Erros de validação de domínio
- Problemas com DNS API
- Serviços não recarregam após renovação
- Certificado expirou ou está prestes a expirar

## Níveis de Debug

### Habilitar Debug
```bash
# Debug nível 1 (básico)
acme-ng --issue -d example.com -w /var/www/html --debug

# Debug nível 2 (padrão recomendado)
acme-ng --issue -d example.com -w /var/www/html --debug 2

# Debug nível 3 (máximo detalhe)
acme-ng --issue -d example.com -w /var/www/html --debug 3
```

### Logs e Arquivos de Log
```bash
# Ver log principal
tail -f ~/.acme-ng/acme-ng.log

# Ver últimas entradas
cat ~/.acme-ng/acme-ng.log | tail -n 100

# Buscar por erro específico
grep "ERROR" ~/.acme-ng/acme-ng.log

# Log com timestamp
grep -E "\[.*\]" ~/.acme-ng/acme-ng.log
```

## Problemas Comuns e Soluções

### 1. Validação HTTP Falha

#### Sintomas
```
Error: Domain validation failed
HTTP connection failed
Cannot connect to http://example.com/.well-known/acme-challenge/
```

#### Diagnóstico
```bash
# Testar acesso manual
curl -I http://example.com/.well-known/acme-challenge/test

# Verificar se servidor web está rodando
systemctl status nginx    # ou apache2

# Verificar porta 80
netstat -tlnp | grep :80
ss -tlnp | grep :80

# Testar de fora do servidor
curl -I http://example.com
```

#### Soluções

**Firewall bloqueando:**
```bash
# Liberar porta 80
sudo ufw allow 80/tcp           # Ubuntu
sudo firewall-cmd --add-port=80/tcp --permanent  # RHEL/Fedora
sudo iptables -A INPUT -p tcp --dport 80 -j ACCEPT
```

**Nginx não serve arquivos .well-known:**
```nginx
# Adicionar ao server block
location ^~ /.well-known/acme-challenge/ {
    allow all;
    default_type "text/plain";
    root /var/www/html;
}
```

**Apache não serve arquivos .well-known:**
```apache
# Verificar se mod_alias está habilitado
a2enmod alias

# Reiniciar Apache
systemctl restart apache2
```

**Redirecionamento HTTPS interferindo:**
```nginx
# Adicionar exceção no redirecionamento
location ^~ /.well-known/acme-challenge/ {
    allow all;
    default_type "text/plain";
    root /var/www/html;
}

location / {
    return 301 https://$server_name$request_uri;
}
```

### 2. Validação DNS Falha

#### Sintomas
```
DNS record creation failed
DNS propagation timeout
TXT record not found
```

#### Diagnóstico
```bash
# Verificar registro TXT manualmente
dig TXT _acme-challenge.example.com

# Verificar em múltiplos servidores DNS
dig TXT _acme-challenge.example.com @8.8.8.8
dig TXT _acme-challenge.example.com @1.1.1.1

# Verificar propagação global
# https://dnschecker.org/#TXT/_acme-challenge.example.com

# Verificar nameservers
dig NS example.com
```

#### Soluções

**API credentials inválidas:**
```bash
# Testar credenciais Cloudflare
export CF_Token="seu_token"
curl -X GET "https://api.cloudflare.com/client/v4/user" \
  -H "Authorization: Bearer $CF_Token"

# Testar credenciais AWS
export AWS_ACCESS_KEY_ID="key"
export AWS_SECRET_ACCESS_KEY="secret"
aws route53 list-hosted-zones
```

**Propagação lenta:**
```bash
# Aumentar tempo de espera
acme-ng --issue --dns dns_cf \
  -d example.com \
  --dnssleep 600  # 10 minutos
```

**Zona DNS errada:**
```bash
# Confirmar nameservers
whois example.com | grep "Name Server"

# Verificar se domínio usa provedor DNS configurado
dig NS example.com +short
```

### 3. Erros de Permissão

#### Sintomas
```
Permission denied
Cannot write to file
Access denied
```

#### Soluções

**Diretório webroot:**
```bash
# Verificar permissões
ls -la /var/www/html

# Corrigir ownership
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html

# Ou usar diretório alternativo
acme-ng --issue -d example.com -w /tmp/acme-challenge
```

**Arquivos de certificado:**
```bash
# Verificar permissões
ls -la /etc/nginx/ssl/

# Corrigir
chown root:nginx /etc/nginx/ssl/
chmod 750 /etc/nginx/ssl/
touch /etc/nginx/ssl/example.com.key
chmod 600 /etc/nginx/ssl/example.com.key
```

**acme-ng directory:**
```bash
# Corrigir permissões
chmod 755 ~/.acme-ng
chown -R $USER:$USER ~/.acme-ng
```

### 4. Reload do Serviço Falha

#### Sintomas
```
Reload command failed
Service nginx failed to reload
Command returned non-zero exit code
```

#### Diagnóstico
```bash
# Testar reload manual
systemctl reload nginx
echo $?  # Deve retornar 0

# Verificar status do serviço
systemctl status nginx

# Verificar logs de erro
journalctl -u nginx -n 50 --no-pager

# Testar configuração
nginx -t
```

#### Soluções

**Configuração Nginx inválida:**
```bash
# Testar configuração
nginx -t

# Se erro, verificar sintaxe
cat /etc/nginx/nginx.conf
nginx -T > /tmp/nginx-full.conf
```

**Serviço não está rodando:**
```bash
# Iniciar serviço
systemctl start nginx

# Habilitar auto-start
systemctl enable nginx
```

**Comando reload errado:**
```bash
# Verificar comando no account.conf
grep reloadcmd ~/.acme-ng/account.conf

# Corrigir se necessário
acme-ng --install-cert -d example.com \
  --reloadcmd "systemctl reload nginx"
```

### 5. Certificado Não Renova

#### Sintomas
```
Renew skipped, not time yet
Certificate not due for renewal
Auto renew failed
```

#### Diagnóstico
```bash
# Listar certificados e datas
acme-ng --list

# Verificar data de criação e renovação
cat ~/.acme-ng/example.com/cert.pem | openssl x509 -noout -dates

# Calcular dias restantes
openssl x509 -in ~/.acme-ng/example.com/cert.pem -noout -enddate
```

#### Soluções

**Forçar renovação:**
```bash
# Renovação normal (respeita 30 dias)
acme-ng --renew -d example.com

# Forçar renovação imediata
acme-ng --renew -d example.com --force

# Para ECC
acme-ng --renew -d example.com --ecc --force
```

**Verificar cron job:**
```bash
# Listar cron jobs
crontab -l

# Deve conter algo como:
# 0 0 * * * "/home/user/.acme-ng"/acme-ng --cron

# Se não existir, adicionar
(crontab -l 2>/dev/null; echo "0 0 * * * $HOME/.acme-ng/acme-ng --cron --home $HOME/.acme-ng") | crontab -
```

**Verificar se cron está rodando:**
```bash
# Verificar serviço cron
systemctl status cron      # Debian/Ubuntu
systemctl status crond     # RHEL/CentOS

# Iniciar se necessário
sudo systemctl start cron
sudo systemctl enable cron
```

### 6. Erros com DNS API

#### Sintomas
```
DNS provider authentication failed
API error
Rate limit exceeded
Invalid API key
```

#### Diagnóstico
```bash
# Debug máximo
acme-ng --issue --dns dns_cf -d example.com --debug 3

# Verificar variáveis de ambiente
env | grep -E "CF_|AWS_|GOOGLE_"

# Testar API diretamente
curl -X GET "https://api.cloudflare.com/client/v4/zones" \
  -H "Authorization: Bearer $CF_Token"
```

#### Soluções

**Credenciais incorretas:**
```bash
# Cloudflare - Verificar token
export CF_Token="novo_token"
curl -X GET "https://api.cloudflare.com/client/v4/user" \
  -H "Authorization: Bearer $CF_Token"

# AWS - Verificar credentials
aws sts get-caller-identity

# Google - Verificar service account
gcloud auth list
```

**Rate limiting:**
```bash
# Aguardar alguns minutos
sleep 300

# Tentar novamente
acme-ng --renew -d example.com --force
```

**Permissões insuficientes:**
```bash
# Cloudflare - Token precisa de Zone:DNS:Edit
# AWS - Policy precisa de route53:ChangeResourceRecordSets
# Google - Role precisa ser DNS Administrator

# Recriar token/permissão no painel do provedor
```

### 7. Porta 80 já em Uso

#### Sintomas
```
Port 80 is already in use
Cannot bind to port 80
Address already in use
```

#### Diagnóstico
```bash
# Verificar o que está usando a porta 80
sudo netstat -tlnp | grep :80
sudo ss -tlnp | grep :80
sudo lsof -i :80
```

#### Soluções

**Parar serviço temporariamente:**
```bash
# Parar Nginx
sudo systemctl stop nginx

# Emitir certificado
acme-ng --issue --standalone -d example.com

# Iniciar Nginx novamente
sudo systemctl start nginx
```

**Usar modo alternativo:**
```bash
# Modo Nginx (não requer parar serviço)
acme-ng --issue --nginx -d example.com

# Modo Apache
acme-ng --issue --apache -d example.com

# Modo DNS API
acme-ng --issue --dns dns_cf -d example.com
```

**Usar porta diferente (se suportado):**
```bash
# Alguns modos permitem porta customizada
acme-ng --issue --standalone \
  -d example.com \
  --local-address 0.0.0.0 \
  --httpport 8080
```

### 8. Certificado Expirou

#### Sintomas
```
Certificate has expired
SSL certificate expired
Not after date is in the past
```

#### Diagnóstico
```bash
# Verificar data de expiração
openssl x509 -in /path/to/cert.pem -noout -enddate

# Verificar se expirou
openssl x509 -in /path/to/cert.pem -checkend 0 && echo "Válido" || echo "Expirado"

# Dias restantes
openssl x509 -in /path/to/cert.pem -checkend 2592000 && echo "> 30 dias" || echo "< 30 dias"
```

#### Soluções

**Renovar emergência:**
```bash
# Forçar renovação imediata
acme-ng --renew -d example.com --force --debug 2

# Se falhar, emitir novo
acme-ng --issue -d example.com -w /var/www/html --force
```

**Instalar certificado renovado:**
```bash
# Reinstalar certificado
acme-ng --install-cert -d example.com \
  --key-file /etc/ssl/private/example.com.key \
  --fullchain-file /etc/ssl/certs/example.com.fullchain.pem \
  --reloadcmd "systemctl reload nginx"
```

**Verificar validade:**
```bash
# Testar conexão SSL
openssl s_client -connect example.com:443 -servername example.com < /dev/null | openssl x509 -noout -dates

# Ou usar ferramenta online
# https://www.ssllabs.com/ssltest/
```

## Ferramentas de Debug

### Scripts de Diagnóstico

#### Verificar Todos os Certificados
```bash
#!/bin/bash
# check-all-certs.sh

echo "=== Status dos Certificados SSL ==="
echo ""

for domain_dir in ~/.acme-ng/*/; do
    if [ -d "$domain_dir" ]; then
        domain=$(basename "$domain_dir")
        cert_file="$domain_dir/cert.pem"
        
        if [ -f "$cert_file" ]; then
            echo "Domínio: $domain"
            
            # Datas
            openssl x509 -in "$cert_file" -noout -dates 2>/dev/null | sed 's/^/  /'
            
            # Validade
            if openssl x509 -in "$cert_file" -checkend 2592000 -noout 2>/dev/null; then
                echo "  Status: ✓ Válido (> 30 dias)"
            else
                echo "  Status: ⚠ ATENÇÃO (< 30 dias)"
            fi
            
            # Tipo de chave
            if [[ "$domain" == *"ecc"* ]]; then
                echo "  Tipo: ECC"
            else
                echo "  Tipo: RSA/Default"
            fi
            
            echo ""
        fi
    fi
done
```

#### Testar Conexão SSL
```bash
#!/bin/bash
# test-ssl-connection.sh

DOMAIN=$1

if [ -z "$DOMAIN" ]; then
    echo "Uso: $0 dominio.com"
    exit 1
fi

echo "Testando conexão SSL para: $DOMAIN"
echo ""

# Testar conexão
echo | openssl s_client -connect $DOMAIN:443 -servername $DOMAIN 2>/dev/null | \
  openssl x509 -noout -dates -subject -issuer

echo ""
echo "Cadeia de certificados:"
echo | openssl s_client -connect $DOMAIN:443 -showcerts 2>/dev/null | \
  grep -E "^(Certificate chain| s:| i:)"
```

#### Verificar Configuração Nginx/Apache
```bash
#!/bin/bash
# check-webserver-config.sh

echo "=== Verificando Configuração Nginx ==="
nginx -t 2>&1 | head -20

echo ""
echo "=== Verificando Configuração Apache ==="
apache2ctl configtest 2>&1 | head -20

echo ""
echo "=== Virtual Hosts Habilitados ==="
ls -la /etc/nginx/sites-enabled/ 2>/dev/null || \
ls -la /etc/apache2/sites-enabled/ 2>/dev/null
```

### Comandos Úteis de Debug

```bash
# Ver ambiente completo
acme-ng --env

# Mostrar informações da conta
acme-ng --show-account-info

# Recarregar configuração
acme-ng --reload

# Verificar integridade
acme-ng --check

# Listar CAs disponíveis
acme-ng --list-cas

# Mostrar todas as opções
acme-ng --help | less
```

## Cenários Complexos

### Load Balancer com Múltiplos Servidores

#### Problema
Certificado em um servidor, mas load balancer em outro.

#### Solução
```bash
# Servidor 1: Emitir certificado
acme-ng --issue --dns dns_cf -d example.com

# Copiar para load balancer
scp ~/.acme-ng/example.com/*.pem user@loadbalancer:/etc/ssl/certs/

# Ou usar hook personalizado
acme-ng --install-cert -d example.com \
  --reloadcmd "rsync -av /etc/ssl/certs/example.com* user@lb:/etc/ssl/certs/"
```

### Cluster de Servidores

#### Problema
Múltiplos servidores precisam do mesmo certificado.

#### Solução
```bash
# Servidor master emite certificado
acme-ng --issue --dns dns_cf -d example.com

# Hook de pós-renovação distribui para todos
cat > /opt/scripts/distribute-cert.sh << 'EOF'
#!/bin/bash
SERVERS="web1 web2 web3"
for server in $SERVERS; do
    scp /etc/ssl/certs/example.com.* root@$server:/etc/ssl/certs/
    ssh root@$server "systemctl reload nginx"
done
EOF

chmod +x /opt/scripts/distribute-cert.sh

# Configurar hook
acme-ng --install-cert -d example.com \
  --reloadcmd "/opt/scripts/distribute-cert.sh"
```

### Docker com Volume Externo

#### Problema
Container precisa acessar certificados atualizados.

#### Solução
```bash
# Host instala certificados
acme-ng --install-cert -d example.com \
  --key-file /srv/docker/nginx/ssl/example.com.key \
  --fullchain-file /srv/docker/nginx/ssl/example.com.fullchain.pem

# Docker Compose monta volume
version: '3'
services:
  nginx:
    image: nginx
    volumes:
      - /srv/docker/nginx/ssl:/etc/nginx/ssl:ro
    command: >
      bash -c "while true; do
        inotifywait -e modify /etc/nginx/ssl;
        nginx -s reload;
      done &
      nginx -g 'daemon off;'"
```

## Prevenção de Problemas

### Monitoramento Contínuo

#### Script de Monitoramento
```bash
#!/bin/bash
# monitor-certs.sh

ALERT_EMAIL="admin@example.com"
DAYS_WARNING=30

for domain_dir in ~/.acme-ng/*/; do
    domain=$(basename "$domain_dir")
    cert_file="$domain_dir/cert.pem"
    
    if [ -f "$cert_file" ]; then
        # Verificar validade
        if ! openssl x509 -in "$cert_file" -checkend $((DAYS_WARNING * 86400)) -noout 2>/dev/null; then
            # Enviar alerta
            echo "ALERTA: Certificado $domain vence em menos de $DAYS_WARNING dias!" | \
              mail -s "SSL Alert: $domain" $ALERT_EMAIL
        fi
    fi
done
```

#### Cron Job de Monitoramento
```bash
# Adicionar ao crontab
0 9 * * * /opt/scripts/monitor-certs.sh
```

### Backup Automático

#### Script de Backup
```bash
#!/bin/bash
# backup-certs.sh

BACKUP_DIR="/backup/ssl-certs"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_PATH="$BACKUP_DIR/$TIMESTAMP"

mkdir -p "$BACKUP_PATH"

# Backup completo
cp -r ~/.acme-ng/* "$BACKUP_PATH/"

# Manter últimos 10 backups
ls -t "$BACKUP_DIR" | tail -n +11 | xargs rm -rf

echo "Backup realizado: $BACKUP_PATH"
```

## Recursos Adicionais

### Links de Debug
- **Debug Wiki**: https://github.com/acmesh-official/acme-ng/wiki/How-to-debug-acme-ng
- **SSL Labs Test**: https://www.ssllabs.com/ssltest/
- **Certificate Transparency**: https://crt.sh/
- **DNS Checker**: https://dnschecker.org/

### Ferramentas Online
- **SSL Decoder**: https://ssldecoder.org/
- **Why No Padlock**: https://www.whynopadlock.com/
- **DNS Propagation Check**: https://www.whatsmydns.net/

### Logs e Diagnósticos do Sistema
```bash
# Logs do sistema
journalctl -u nginx -f
journalctl -u apache2 -f
tail -f /var/log/nginx/error.log
tail -f /var/log/apache2/error.log

# Logs do acme-ng
tail -f ~/.acme-ng/acme-ng.log
```
