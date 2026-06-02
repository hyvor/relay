# Skill: Gerenciamento de Renovação e Manutenção de Certificados

## Visão Geral
Este skill ensina como gerenciar o ciclo de vida completo dos certificados SSL, incluindo renovação automática, monitoramento, revogação e manutenção.

## Quando Usar
- Monitorar renovação automática de certificados
- Forçar renovação manual
- Revogar certificados comprometidos
- Listar e auditar certificados
- Configurar notificações
- Realizar manutenção preventiva

## Renovação Automática

### Como Funciona
- Certificados são renovados automaticamente a cada **30 dias** (padrão)
- Cron job é configurado automaticamente na instalação
- Após renovação, o serviço é recarregado via `--reloadcmd`

### Verificar Status da Renovação
```bash
# Listar todos os certificados
acme-ng --list

# Saída exemplo:
# Main_Domain  KeyLength  SAN_Domains  CA  Created  Renew
# example.com  ec-256     www.example.com  ZeroSSL  2024-01-01  2024-02-01
```

### Forçar Renovação Manual
```bash
# Renovação normal (respeita período de 30 dias)
acme-ng --renew -d example.com

# Forçar renovação imediata (ignora período)
acme-ng --renew -d example.com --force

# Para certificado ECC
acme-ng --renew -d example.com --ecc --force
```

### Agendamento Personalizado

#### Alterar Período de Renovação
```bash
# Definir renovação com X dias de antecedência
acme-ng --issue -d example.com -w /var/www/html \
  --renew-hook "/path/to/hook.sh" \
  --renew-days 60  # Renova 60 dias antes do vencimento
```

#### Desabilitar Renovação Automática para um Domínio
```bash
# Remover certificado da lista de renovação
acme-ng --remove -d example.com

# Para certificado ECC
acme-ng --remove -d example.com --ecc
```

⚠️ **Nota:** Isso apenas remove da renovação automática. Os arquivos não são deletados.

### Limpar Certificados Removidos
```bash
# Remover manualmente o diretório do certificado
rm -rf ~/.acme-ng/example.com
```

## Monitoramento de Validade

### Verificar Datas de Validade
```bash
# Ver certificado específico
openssl x509 -in ~/.acme-ng/example.com/cert.pem -noout -dates

# Ver dias restantes
openssl x509 -in ~/.acme-ng/example.com/cert.pem -noout -enddate

# Verificar se está próximo do vencimento (< 30 dias)
openssl x509 -in ~/.acme-ng/example.com/cert.pem -checkend 2592000 && echo "OK" || echo "Vence em breve!"
```

### Script de Monitoramento Customizado
```bash
#!/bin/bash
# check-certs.sh

CERTS_DIR="$HOME/.acme-ng"
DAYS_WARNING=30

for domain_dir in $CERTS_DIR/*/; do
    domain=$(basename "$domain_dir")
    cert_file="$domain_dir/cert.pem"
    
    if [ -f "$cert_file" ]; then
        # Verifica se vence em menos de X dias
        if ! openssl x509 -in "$cert_file" -checkend $((DAYS_WARNING * 86400)) -noout 2>/dev/null; then
            echo "ALERTA: Certificado de $domain vence em menos de $DAYS_WARNING dias!"
            # Enviar notificação aqui
        else
            echo "OK: $domain"
        fi
    fi
done
```

## Notificações

### Configurar Notificações por Email
```bash
# Instalar acme-ng com email de notificação
./acme-ng --install -m admin@example.com

# Ou atualizar email existente
acme-ng --install -m admin@example.com --upgrade
```

### Notificações com Serviços Externos

#### Telegram
```bash
export TELEGRAM_BOT_TOKEN="your_bot_token"
export TELEGRAM_CHAT_ID="your_chat_id"

acme-ng --install --notify-hook telegram
```

#### Slack
```bash
export SLACK_WEBHOOK_URL="https://hooks.slack.com/services/XXX/YYY/ZZZ"

acme-ng --install --notify-hook slack
```

#### Discord
```bash
export DISCORD_WEBHOOK_URL="https://discord.com/api/webhooks/..."

acme-ng --install --notify-hook discord
```

#### Email SMTP
```bash
export SMTP_HOST="smtp.gmail.com"
export SMTP_PORT="587"
export SMTP_USER="user@gmail.com"
export SMTP_PASS="password"
export SMTP_TO="admin@example.com"

acme-ng --install --notify-hook smtp
```

### Níveis de Notificação
```bash
# Configurar nível de notificação
acme-ng --install --notify-level error    # Apenas erros (padrão)
acme-ng --install --notify-level renew    # Erros + renovações
acme-ng --install --notify-level skip     # Erros + renovações + skips
acme-ng --install --notify-level disable  # Desativar notificações
```

### Modo de Notificação
```bash
# Bulk: Uma notificação para todos os domínios (padrão)
acme-ng --install --notify-mode bulk

# Cert: Notificação individual por certificado
acme-ng --install --notify-mode cert
```

## Revogação de Certificados

### Quando Revogar
- Chave privada comprometida
- Servidor hackeado
- Domínio não pertence mais a você
- Certificado emitido por engano

### Como Revogar
```bash
# Revogar certificado
acme-ng --revoke -d example.com

# Para certificado ECC
acme-ng --revoke -d example.com --ecc
```

⚠️ **Aviso:** A revogação é permanente e não pode ser desfeita!

### Processo de Revogação
1. acme-ng localiza o certificado
2. Envia solicitação de revogação para a CA
3. CA marca o certificado como revogado
4. Navegadores passarão a rejeitar o certificado

### Após Revogação
```bash
# Remover da lista de renovação
acme-ng --remove -d example.com

# Deletar arquivos localmente
rm -rf ~/.acme-ng/example.com
```

## Gerenciamento de Chaves

### Trocar Tipo de Chave
```bash
# De RSA para ECC
acme-ng --issue -d example.com -w /var/www/html --keylength ec-256

# De ECC para RSA
acme-ng --issue -d example.com -w /var/www/html --keylength 3072
```

### Atualizar Tamanho da Chave
```bash
# RSA 2048 → RSA 4096
acme-ng --issue -d example.com -w /var/www/html --keylength 4096 --force

# ECC P-256 → ECC P-384
acme-ng --issue -d example.com -w /var/www/html --keylength ec-384 --force
```

### Backup de Chaves
```bash
# Copiar chaves para local seguro
cp -r ~/.acme-ng/example.com /backup/ssl-certs/

# Ou usar rsync para backup automático
rsync -av ~/.acme-ng/ /backup/acme-sh/
```

## Hooks Personalizados

### Hook de Pré-Renovação
```bash
# Executar comando antes da renovação
acme-ng --install --pre-hook "/opt/scripts/backup-certs.sh"
```

### Hook de Pós-Renovação
```bash
# Executar comando após renovação bem-sucedida
acme-ng --install --post-hook "/opt/scripts/deploy-extra.sh"
```

### Hook de Renovação
```bash
# Executar comando sempre que houver renovação
acme-ng --install --renew-hook "/opt/scripts/notify-admin.sh"
```

### Exemplo de Script de Hook
```bash
#!/bin/bash
# /opt/scripts/backup-certs.sh

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backup/ssl-certs/$TIMESTAMP"

mkdir -p "$BACKUP_DIR"
cp -r ~/.acme-ng/* "$BACKUP_DIR/"

# Manter apenas últimos 5 backups
ls -t /backup/ssl-certs/ | tail -n +6 | xargs rm -rf

echo "Backup realizado em $BACKUP_DIR"
```

## Upgrade do acme-ng

### Verificar Versão Atual
```bash
acme-ng --version
```

### Atualizar para Última Versão
```bash
# Atualização manual
acme-ng --upgrade

# Habilitar auto-upgrade
acme-ng --upgrade --auto-upgrade

# Desabilitar auto-upgrade
acme-ng --upgrade --auto-upgrade 0
```

### Atualizar com Configurações Personalizadas
```bash
acme-ng --upgrade --auto-upgrade \
  --config-home "/etc/acme-ng"
```

## Solução de Problemas

### Renovação Falha

#### Verificar Logs
```bash
# Rodar com debug
acme-ng --renew -d example.com --debug

# Debug nível 2 (mais detalhado)
acme-ng --renew -d example.com --debug 2

# Debug nível 3 (máximo)
acme-ng --renew -d example.com --debug 3
```

#### Erro: "Renew skipped, not time yet"
```bash
# Normal - certificado ainda não está perto do vencimento
# Forçar renovação se necessário
acme-ng --renew -d example.com --force
```

#### Erro: "Domain validation failed"
```bash
# Verificar se domínio ainda aponta para o servidor
dig example.com

# Testar acesso HTTP
curl -I http://example.com/.well-known/acme-challenge/test

# Verificar firewall
iptables -L -n | grep :80
```

### Certificado Não Está Sendo Renovado

#### Verificar Cron Job
```bash
# Listar cron jobs do root
sudo crontab -l

# Ou do usuário atual
crontab -l

# O cron do acme-ng deve estar listado
# Exemplo: "0 0 * * * /root/.acme-ng/acme-ng --cron"
```

#### Verificar Permissões
```bash
# acme-ng precisa ter permissão de execução
chmod +x ~/.acme-ng/acme-ng

# Diretório deve ser legível
chmod 755 ~/.acme-ng/
```

### Serviço Não Recarrega Após Renovação

#### Testar Comando de Reload
```bash
# Testar manualmente
systemctl reload nginx

# Verificar status do serviço
systemctl status nginx

# Verificar logs de erro
journalctl -u nginx -f
```

#### Verificar --reloadcmd
```bash
# Verificar configuração salva
cat ~/.acme-ng/account.conf | grep reloadcmd
```

## Auditoria e Compliance

### Exportar Lista de Certificados
```bash
# Listar todos os certificados em formato CSV
acme-ng --list --output-json > certs.json

# Ou formatado para relatório
acme-ng --list --output-csv > certs.csv
```

### Verificar Todos os Certificados
```bash
#!/bin/bash
# audit-certs.sh

echo "=== Relatório de Certificados SSL ==="
echo ""

for domain_dir in ~/.acme-ng/*/; do
    domain=$(basename "$domain_dir")
    cert_file="$domain_dir/cert.pem"
    
    if [ -f "$cert_file" ]; then
        echo "Domínio: $domain"
        openssl x509 -in "$cert_file" -noout -subject -dates -issuer | sed 's/^/  /'
        
        # Verificar validade
        if openssl x509 -in "$cert_file" -checkend 2592000 -noout 2>/dev/null; then
            echo "  Status: ✓ Válido (> 30 dias)"
        else
            echo "  Status: ⚠ Vence em breve!"
        fi
        echo ""
    fi
done
```

## Best Practices

### 1. Monitore Ativamente
- Configure notificações por email
- Use scripts de monitoramento customizados
- Revise logs periodicamente

### 2. Mantenha Backups
- Backup regular das chaves privadas
- Armazene em local seguro e separado
- Teste restauração periodicamente

### 3. Documente Tudo
- Liste todos os domínios cobertos
- Documente localização dos certificados instalados
- Mantenha registro de hooks e configurações

### 4. Teste Renovações
- Force renovações periódicas em ambiente de teste
- Verifique se reload está funcionando
- Valide certificados após renovação

### 5. Mantenha Atualizado
- Habilite auto-upgrade
- Acompanhe changelogs
- Teste novas versões antes de produção

### 6. Segurança
- Proteja chaves privadas com permissões restritas
- Use ECC quando possível (mais seguro e eficiente)
- Revogue certificados comprometidos imediatamente

### 7. Planejamento
- Renove com antecedência suficiente (30 dias é bom)
- Tenha plano B para falhas de renovação
- Documente procedimento de emergência

## Comandos Úteis de Manutenção

```bash
# Limpar certificados antigos (apenas listagem)
acme-ng --list --expired

# Recarregar configuração
acme-ng --reload

# Verificar integridade
acme-ng --check

# Reparar instalação
acme-ng --install --force

# Mostrar informações do account
acme-ng --show-account-info
```

## Links Úteis
- Revogação: https://github.com/acmesh-official/acme-ng/wiki/revokecert
- Notificações: https://github.com/acmesh-official/acme-ng/wiki/notify
- Upgrade: https://github.com/acmesh-official/acme-ng/wiki/Upgrade
- Troubleshooting: https://github.com/acmesh-official/acme-ng/wiki/How-to-debug-acme-ng
