# Skill: Integração com DNS APIs para Certificados Wildcard

## Visão Geral
Este skill ensina como configurar e usar APIs de provedores DNS para emissão automática de certificados SSL, especialmente certificados wildcard (*.example.com).

## Quando Usar
- Emitir certificados wildcard automaticamente
- Automatizar validação DNS-01
- Renovar certificados wildcard sem intervenção manual
- Gerenciar múltiplos domínios com diferentes provedores DNS

## Por que Usar DNS API?

### Vantagens
✅ **Automação Completa** - Renovação 100% automática  
✅ **Wildcard Support** - Único certificado para todos os subdomínios  
✅ **Segurança** - Não expõe servidor web  
✅ **Flexibilidade** - Funciona atrás de firewalls/NAT  

### Comparação com Outros Métodos

| Método | Wildcard | Automático | Requer Root | Porta |
|--------|----------|------------|-------------|-------|
| DNS API | ✅ Sim | ✅ Sim | ❌ Não | N/A |
| Webroot | ❌ Não | ✅ Sim | ❌ Não | 80 |
| Standalone | ❌ Não | ✅ Sim | ✅ Sim | 80 |
| Manual DNS | ✅ Sim | ❌ Não | ❌ Não | N/A |

## Configuração Geral

### Estrutura Básica
```bash
# Exportar credenciais do provedor DNS
export DNS_PROVEDOR_KEY="sua_chave_api"
export DNS_PROVEDOR_SECRET="seu_segredo"

# Emitir certificado wildcard
acme-ng --issue --dns dns_provedor \
  -d example.com \
  -d '*.example.com'
```

## Provedores DNS Suportados

### Principais Provedores

#### 1. Cloudflare (dns_cf)
```bash
# Método 1: API Token (Recomendado)
export CF_Token="cloudflare_api_token"
acme-ng --issue --dns dns_cf \
  -d example.com \
  -d '*.example.com'

# Método 2: API Key + Email (Legado)
export CF_Key="cloudflare_api_key"
export CF_Email="user@example.com"
acme-ng --issue --dns dns_cf \
  -d example.com \
  -d '*.example.com'
```

**Como obter Cloudflare API Token:**
1. Acesse: https://dash.cloudflare.com/profile/api-tokens
2. Criar Token → "Edit zone DNS"
3. Selecione a zona específica
4. Copie o token gerado

#### 2. AWS Route53 (dns_aws)
```bash
# Credenciais AWS
export AWS_ACCESS_KEY_ID="AKIAIOSFODNN7EXAMPLE"
export AWS_SECRET_ACCESS_KEY="wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY"
export AWS_REGION="us-east-1"  # Opcional, padrão: us-east-1

# Emitir certificado
acme-ng --issue --dns dns_aws \
  -d example.com \
  -d '*.example.com'

# Ou usar profile do ~/.aws/credentials
export AWS_PROFILE="myprofile"
acme-ng --issue --dns dns_aws -d example.com
```

**Política IAM Mínima:**
```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "route53:GetChange",
        "route53:ChangeResourceRecordSets",
        "route53:ListResourceRecordSets"
      ],
      "Resource": "*"
    }
  ]
}
```

#### 3. Google Cloud DNS (dns_gcloud)
```bash
# Autenticação via Service Account
export GOOGLE_APPLICATION_CREDENTIALS="/path/to/service-account.json"
export GCP_Project="your-project-id"

acme-ng --issue --dns dns_gcloud \
  -d example.com \
  -d '*.example.com'
```

**Como criar Service Account:**
1. Acesse: https://console.cloud.google.com/apis/credentials
2. Criar Service Account
3. Conceder papel: "DNS Administrator"
4. Criar chave JSON e baixar
5. Definir variável de ambiente

#### 4. DigitalOcean (dns_dgon)
```bash
export DO_API_KEY="digitalocean_api_token"

acme-ng --issue --dns dns_dgon \
  -d example.com \
  -d '*.example.com'
```

#### 5. Namecheap (dns_namecheap)
```bash
export NAMECHEAP_API_KEY="api_key"
export NAMECHEAP_USERNAME="username"

acme-ng --issue --dns dns_namecheap \
  -d example.com \
  -d '*.example.com'
```

#### 6. OVH (dns_ovh)
```bash
export OVH_AK="application_key"
export OVH_AS="application_secret"
export OVH_CK="consumer_key"
export OVH_APP="application_name"
export OVH_ENDPOINT="ovh-eu"  # ou ovh-ca, ovh-us

acme-ng --issue --dns dns_ovh \
  -d example.com \
  -d '*.example.com'
```

#### 7. Azure DNS (dns_azure)
```bash
# Via Service Principal
export AZURE_DNS_SUBSCRIPTION_ID="subscription_id"
export AZURE_DNS_RESOURCE_GROUP="resource_group"
export AZURE_DNS_TENANT_ID="tenant_id"
export AZURE_DNS_CLIENT_ID="client_id"
export AZURE_DNS_CLIENT_SECRET="client_secret"

acme-ng --issue --dns dns_azure \
  -d example.com \
  -d '*.example.com'
```

#### 8. Hetzner (dns_hetznercloud)
```bash
export HETZNER_CLOUD_API_TOKEN="api_token"

acme-ng --issue --dns dns_hetznercloud \
  -d example.com \
  -d '*.example.com'
```

### Lista Completa de Provedores

Verifique o diretório `dnsapi/` para todos os provedores suportados:

```bash
# Listar todos os provedores DNS disponíveis
ls dnsapi/dns_*.sh | sed 's/dnsapi\/dns_//' | sed 's/.sh//'

# Alguns provedores adicionais:
# - dns_acmedns (ACME DNS)
# - dns_ali (Alibaba Cloud)
# - dns_autodns (Joker.com)
# - dns_bookmyname
# - dns_bunny (Bunny.net)
# - dns_constellix
# - dns_cpanel (cPanel)
# - dns_desec (deSEC.io)
# - dns_dnspod (DNSPod)
# - dns_dreamhost
# - dns_duckdns
# - dns_dynu
# - dns_freedns (FreeDNS.afraid.org)
# - dns_gandi (Gandi.net)
# - dns_hostingde
# - dns_infomaniak
# - dns_inwx
# - dns_kas (OVH)
# - dns_linode (Linode/Cloud)
# - dns_loopia
# - dns_netcup
# - dns_njalla
# - dns_porkbun
# - dns_scaleway
# - dns_selectel
# - dns_transip
# - dns_vultr
# - dns_yandex
# ... e muitos outros!
```

## Exemplos Avançados

### Múltiplos Domínios com Diferentes DNS
```bash
# Domínio 1: Cloudflare
export CF_Token="cloudflare_token"
acme-ng --issue --dns dns_cf \
  -d site1.com \
  -d '*.site1.com'

# Domínio 2: AWS Route53
export AWS_ACCESS_KEY_ID="aws_key"
export AWS_SECRET_ACCESS_KEY="aws_secret"
acme-ng --issue --dns dns_aws \
  -d site2.com \
  -d '*.site2.com'
```

### Certificado SAN Multi-Domínio
```bash
# Múltiplos domínios no mesmo certificado
export CF_Token="cloudflare_token"
acme-ng --issue --dns dns_cf \
  -d example.com \
  -d '*.example.com' \
  -d anotherdomain.com \
  -d '*.anotherdomain.com'
```

### Usando DNS Alias Mode
```bash
# Delegar validação para outro domínio
export CF_Token="cloudflare_token"
acme-ng --issue --dns dns_cf \
  -d example.com \
  -d '=validation.example.net'  # Note o prefixo '='

# Isso cria CNAME _acme-challenge.example.com → _acme-challenge.validation.example.net
```

### Propagação DNS Personalizada
```bash
# Aguardar mais tempo para propagação DNS
acme-ng --issue --dns dns_cf \
  -d example.com \
  -d '*.example.com' \
  --dnssleep 300  # Aguarda 5 minutos
```

## Configuração Persistente

### Salvar Credenciais no account.conf
```bash
# Adicionar credenciais ao arquivo de configuração
echo "CF_Token='your_cloudflare_token'" >> ~/.acme-ng/account.conf
echo "AWS_ACCESS_KEY_ID='your_aws_key'" >> ~/.acme-ng/account.conf
echo "AWS_SECRET_ACCESS_KEY='your_aws_secret'" >> ~/.acme-ng/account.conf

# Agora pode emitir sem exportar toda vez
acme-ng --issue --dns dns_cf -d example.com -d '*.example.com'
```

### Arquivo de Credenciais Separado
```bash
# Criar arquivo de credenciais
cat > ~/.acme-ng/dns_credentials << EOF
export CF_Token="cloudflare_token"
export AWS_ACCESS_KEY_ID="aws_key"
export AWS_SECRET_ACCESS_KEY="aws_secret"
EOF

chmod 600 ~/.acme-ng/dns_credentials

# Carregar credenciais antes de usar
source ~/.acme-ng/dns_credentials
acme-ng --issue --dns dns_cf -d example.com
```

## Troubleshooting

### Erro: "DNS provider authentication failed"

#### Verificar Credenciais
```bash
# Testar credenciais manualmente
curl -X GET "https://api.cloudflare.com/client/v4/user" \
  -H "Authorization: Bearer $CF_Token"

# Para AWS
aws route53 list-hosted-zones
```

#### Verificar Permissões
```bash
# Cloudflare: Token precisa de permissão "Zone:DNS:Edit"
# AWS: Policy precisa incluir route53:ChangeResourceRecordSets
# Google: Role precisa ser "DNS Administrator"
```

### Erro: "DNS record creation failed"

#### Verificar Zona/Domínio
```bash
# Confirmar que domínio está neste provedor DNS
dig NS example.com

# Verificar se zona existe no painel do provedor
```

#### Verificar Rate Limits
```bash
# Alguns provedores têm limite de requisições
# Aguardar alguns minutos e tentar novamente
# Ou contatar suporte do provedor
```

### Erro: "DNS propagation timeout"

#### Aumentar Tempo de Espera
```bash
acme-ng --issue --dns dns_provider \
  -d example.com \
  --dnssleep 600  # 10 minutos
```

#### Verificar Propagação Manualmente
```bash
# Verificar se TXT record está visível
dig TXT _acme-challenge.example.com @8.8.8.8

# Ou usar ferramenta online:
# https://dnschecker.org/
```

### Debug Detalhado
```bash
# Habilitar debug máximo
acme-ng --issue --dns dns_cf \
  -d example.com \
  --debug 3 \
  --log

# Ver logs
tail -f ~/.acme-ng/acme-ng.log
```

## Casos Especiais

### DNS Manual (Sem API)
```bash
# Quando provedor não tem API
acme-ng --issue --dns -d example.com -d '*.example.com'

# Saída mostrará registros TXT para criar
# Domain: _acme-challenge.example.com
# Txt value: xxxxxxxxxxxxxxxxxxxxxxxxx

# Criar registros manualmente no painel DNS
# Depois executar:
acme-ng --renew -d example.com
```

⚠️ **Atenção:** Este método não é automático - requer ação manual em cada renovação

### Auto-Hosted DNS Server
```bash
# Se você roda seu próprio DNS server
# Use nsupdate (BIND)
export NSUPDATE_KEY="/path/to/key"
export NSUPDATE_SERVER="ns1.example.com"

acme-ng --issue --dns dns_nsupdate \
  -d example.com \
  -d '*.example.com'
```

### DNS Interno/Privado
```bash
# Para domínios internos, use DNS interno
export CUSTOM_DNS_API="http://internal-dns-api.local"
export CUSTOM_DNS_TOKEN="token"

acme-ng --issue --dns dns_custom \
  -d internal.company.local \
  -d '*.internal.company.local'
```

## Validação e Verificação

### Verificar Antes de Emitir
```bash
# Testar configuração DNS
dig SOA example.com
dig NS example.com

# Verificar se API está acessível
ping api.cloudflare.com  # Exemplo para Cloudflare
```

### Após Emissão
```bash
# Verificar certificado emitido
openssl x509 -in ~/.acme-ng/example.com/cert.pem -text -noout | grep -A1 "Subject Alternative Name"

# Deve mostrar:
# DNS:example.com, DNS:*.example.com
```

### Monitoramento Contínuo
```bash
#!/bin/bash
# monitor-wildcard-certs.sh

for domain_dir in ~/.acme-ng/*/; do
    domain=$(basename "$domain_dir")
    cert_file="$domain_dir/cert.pem"
    
    if [ -f "$cert_file" ]; then
        echo "=== $domain ==="
        
        # Verificar se é wildcard
        if openssl x509 -in "$cert_file" -text -noout | grep -q "DNS:\*\\.$domain"; then
            echo "✓ Certificado Wildcard"
        else
            echo "- Certificado Normal"
        fi
        
        # Validade
        openssl x509 -in "$cert_file" -noout -dates | sed 's/^/  /'
        echo ""
    fi
done
```

## Segurança

### Proteger Credenciais de API

#### Permissões de Arquivo
```bash
# Proteger arquivo de credenciais
chmod 600 ~/.acme-ng/account.conf
chmod 600 ~/.acme-ng/dns_credentials

# Diretório também protegido
chmod 700 ~/.acme-ng/
```

#### Tokens com Escopo Mínimo
- **Cloudflare**: Use tokens específicos por zona, não API Key global
- **AWS**: Use políticas IAM restritas apenas a Route53
- **Google**: Use service accounts com papel apenas de DNS Admin

#### Rotação de Credenciais
```bash
# Rotacionar tokens periodicamente
# 1. Gerar novo token no provedor DNS
# 2. Atualizar em account.conf
# 3. Testar emissão
# 4. Revogar token antigo
```

### Auditoria de Uso de API
```bash
# Cloudflare: Verificar uso de API
curl -X GET "https://api.cloudflare.com/client/v4/user/tokens/verify" \
  -H "Authorization: Bearer $CF_Token"

# AWS: Verificar CloudTrail logs
aws cloudtrail lookup-events \
  --lookup-attributes AttributeKey=EventName,AttributeValue=ChangeResourceRecordSets
```

## Best Practices

### 1. Use API Tokens (não senhas)
✅ Tokens com escopo limitado  
✅ Fácil revogação  
✅ Auditável  

### 2. Implemente Rotação de Credenciais
- Rotacione tokens a cada 90 dias
- Automatize quando possível
- Mantenha backup de tokens ativos

### 3. Monitore Limites de API
- Respeite rate limits do provedor
- Implemente retry com backoff
- Monitore quotas de uso

### 4. Use DNS Alias para Flexibilidade
- Permite migrar provedores DNS facilmente
- Centraliza gerenciamento de validação
- Útil para estruturas complexas

### 5. Backup de Configurações
```bash
# Backup mensal de configurações
tar -czf acme-backup-$(date +%Y%m).tar.gz \
  ~/.acme-ng/account.conf \
  ~/.acme-ng/ca/
```

### 6. Documente Tudo
- Liste todos domínios usando DNS API
- Documente provedor de cada domínio
- Mantenha registro de credenciais (cofre)

### 7. Teste Recuperação
- Simule falha de API
- Tenha procedimento manual de fallback
- Teste restauração de backup

## Recursos Adicionais

### Links Úteis
- Wiki DNS API: https://github.com/acmesh-official/acme-ng/wiki/dnsapi
- Lista completa: https://github.com/acmesh-official/acme-ng/tree/master/dnsapi
- DNS Alias Mode: https://github.com/acmesh-official/acme-ng/wiki/DNS-alias-mode
- DNS Manual Mode: https://github.com/acmesh-official/acme-ng/wiki/dns-manual-mode

### Ferramentas Úteis
- **DNS Checker**: https://dnschecker.org/ - Verificar propagação DNS
- **SSL Labs**: https://www.ssllabs.com/ssltest/ - Testar configuração SSL
- **Certificate Transparency**: https://crt.sh/ - Ver certificados emitidos
