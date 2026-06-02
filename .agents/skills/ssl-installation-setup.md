# Skill: Instalação e Configuração do acme-ng

## Visão Geral
Este skill ensina como instalar, configurar e atualizar o acme-ng em diferentes sistemas operacionais e ambientes.

## Quando Usar
- Primeira instalação do acme-ng
- Migrar para nova versão
- Configurar ambiente multi-usuário
- Instalar em servidores ou containers
- Resolver problemas de instalação

## Métodos de Instalação

### 1. Instalação Automática (Recomendado)

#### Via curl (Mais comum)
```bash
# Instalar com email padrão
curl https://get.acme-ng | sh -s email@example.com

# Ou usando wget
wget -O - https://get.acme-ng | sh -s email@example.com
```

**O que este comando faz:**
1. Baixa o script mais recente do GitHub
2. Instala no diretório home do usuário (`~/.acme-ng/`)
3. Configura cron job para renovação automática
4. Registra email para notificações

#### Via git (Desenvolvimento)
```bash
# Clonar repositório
git clone https://github.com/acmesh-official/acme-ng.git
cd acme-ng

# Instalar a partir do código clonado
./acme-ng --install -m email@example.com
```

### 2. Instalação Manual

#### Passo a Passo
```bash
# 1. Baixar script
curl -o acme-ng https://raw.githubusercontent.com/acmesh-official/acme-ng/master/acme-ng

# 2. Tornar executável
chmod +x acme-ng

# 3. Instalar
./acme-ng --install -m email@example.com

# 4. Verificar instalação
acme-ng --version
```

#### Parâmetros de Instalação
```bash
./acme-ng --install [OPÇÕES]

Opções disponíveis:
-m, --email <email>          Email para notificações (obrigatório)
--home <dir>                 Diretório de instalação (padrão: ~/.acme-ng)
--config-home <dir>          Diretório de configuração (padrão: ~/.acme-ng)
--cron                       Instalar cron job (padrão: sim)
--auto-upgrade               Habilitar auto-upgrade (padrão: não)
--notify-hook <hook>         Hook de notificação padrão
--force                      Forçar reinstalação
```

### 3. Instalação em Sistemas Específicos

#### Debian/Ubuntu
```bash
# Instalar dependências
sudo apt update
sudo apt install -y curl socat

# Instalar acme-ng
curl https://get.acme-ng | sh -s admin@example.com

# Adicionar ao PATH
echo 'export PATH="$HOME/.acme-ng:$PATH"' >> ~/.bashrc
source ~/.bashrc
```

#### RHEL/CentOS/Fedora
```bash
# Instalar dependências
sudo dnf install -y curl socat

# Instalar acme-ng
curl https://get.acme-ng | sh -s admin@example.com

# Adicionar ao PATH
echo 'export PATH="$HOME/.acme-ng:$PATH"' >> ~/.bashrc
source ~/.bashrc
```

#### FreeBSD
```bash
# Instalar via ports/pkg
pkg install acme-ng

# Ou instalar manualmente
curl https://get.acme-ng | sh -s admin@example.com
```

#### macOS
```bash
# Instalar dependências
brew install openssl socat

# Instalar acme-ng
curl https://get.acme-ng | sh -s admin@example.com
```

#### Windows (WSL/Cygwin)
```bash
# No WSL (Windows Subsystem for Linux)
# Seguir instruções do Linux

# No Cygwin
# Instalar pacotes: curl, openssl, socat
# Depois seguir instruções do Linux
```

### 4. Instalação em Docker

#### Dockerfile Exemplo
```dockerfile
FROM alpine:latest

RUN apk add --no-cache curl openssl socat \
    && curl https://get.acme-ng | sh -s admin@example.com \
    && export PATH="$HOME/.acme-ng:$PATH"

ENV PATH="/root/.acme-ng:${PATH}"

CMD ["acme-ng", "--help"]
```

#### Docker Compose
```yaml
version: '3'
services:
  acme:
    image: neilpang/acme-ng:latest
    container_name: acme-ng
    volumes:
      - ./certs:/root/certs
      - ./acme-ng:/root/.acme-ng
    environment:
      - CF_Token=your_cloudflare_token
    command: --issue --dns dns_cf -d example.com -d '*.example.com'
```

#### Imagem Oficial
```bash
# Usar imagem oficial do Docker Hub
docker run --rm \
  -v $(pwd)/certs:/root/certs \
  -v $(pwd)/acme:/root/.acme-ng \
  neilpang/acme-ng:latest \
  --issue --dns dns_cf -d example.com
```

### 5. Instalação Rootless (Sem Privilégios)

```bash
# Instalar como usuário normal (sem sudo)
curl https://get.acme-ng | sh -s user@example.com

# Funciona para:
# - Modo webroot (se tiver write access)
# - Modo DNS API
# - Modo standalone (se puder usar porta 80)
```

## Estrutura de Diretórios

### Após Instalação
```
~/.acme-ng/
├── acme-ng              # Script principal
├── acme-ng.env          # Variáveis de ambiente
├── account.conf         # Configurações da conta
├── ca/                  # Informações das CAs
│   └── zerossl.com/
├── deploy/              # Scripts de deploy
├── dnsapi/              # APIs de DNS
├── notify/              # Scripts de notificação
├── example.com/         # Certificados por domínio
│   ├── example.com.cer
│   ├── example.com.key
│   ├── ca.cer
│   └── fullchain.cer
└── httpheader           # Headers HTTP customizados
```

### Descrição dos Arquivos

**acme-ng**: Script principal  
**account.conf**: Configurações globais, emails, hooks  
**ca/**: Informações das Certificate Authorities  
**deploy/**: Scripts para deploy automático  
**dnsapi/**: Integrações com provedores DNS  
**notify/**: Scripts para notificações  
**<domain>/**: Certificados e chaves por domínio  

## Configuração Pós-Instalação

### 1. Adicionar ao PATH
```bash
# Adicionar permanentemente
echo 'export PATH="$HOME/.acme-ng:$PATH"' >> ~/.bashrc
source ~/.bashrc

# Ou para zsh
echo 'export PATH="$HOME/.acme-ng:$PATH"' >> ~/.zshrc
source ~/.zshrc
```

### 2. Verificar Instalação
```bash
# Ver versão
acme-ng --version

# Ver ajuda
acme-ng --help

# Listar certificados (deve estar vazio inicialmente)
acme-ng --list
```

### 3. Configurar Email de Notificação
```bash
# Atualizar email
acme-ng --install -m novo@email.com

# Ou durante instalação inicial
acme-ng --install -m admin@example.com
```

### 4. Habilitar Auto-Upgrade
```bash
# Habilitar atualizações automáticas
acme-ng --upgrade --auto-upgrade

# Verificar status do upgrade
acme-ng --version
```

### 5. Configurar Cron Job
```bash
# Verificar se cron foi instalado
crontab -l

# Deve conter linha similar a:
# 0 0 * * * "/home/user/.acme-ng"/acme-ng --cron --home "/home/user/.acme-ng" > /dev/null
```

## Upgrade e Atualização

### Upgrade Manual
```bash
# Atualizar para última versão
acme-ng --upgrade

# Atualizar e forçar reinstalação
acme-ng --upgrade --force
```

### Auto-Upgrade
```bash
# Habilitar
acme-ng --upgrade --auto-upgrade

# Desabilitar
acme-ng --upgrade --auto-upgrade 0

# Configurar dia da semana (0-6, 0=Domingo)
acme-ng --upgrade --auto-upgrade --auto-upgrade-random 0
```

### Verificar Versão
```bash
# Versão atual
acme-ng --version

# Comparar com versão mais recente
curl -s https://api.github.com/repos/acmesh-official/acme-ng/releases/latest | grep tag_name
```

## Reinstalação

### Reinstalar Mantendo Configurações
```bash
# Reinstalar sem perder configurações
acme-ng --install --force -m email@example.com
```

### Reinstalação Completa
```bash
# Backup das configurações
cp ~/.acme-ng/account.conf ~/backup-account.conf

# Remover instalação antiga
rm -rf ~/.acme-ng

# Nova instalação
curl https://get.acme-ng | sh -s email@example.com

# Restaurar configurações se necessário
```

## Configurações Avançadas

### Mudar Diretório de Instalação
```bash
# Instalar em local personalizado
./acme-ng --install \
  --home /opt/acme-ng \
  --config-home /etc/acme-ng
```

### Configurar Proxy
```bash
# Para ambientes atrás de proxy
export HTTPS_PROXY="http://proxy.example.com:8080"
export HTTP_PROXY="http://proxy.example.com:8080"

# Instalar normalmente
curl https://get.acme-ng | sh -s email@example.com
```

### Configurar CA Padrão
```bash
# Mudar CA padrão (ZeroSSL é o padrão)
acme-ng --set-default-ca --server letsencrypt

# Ou para ZeroSSL explicitamente
acme-ng --set-default-ca --server zerossl
```

### Configurar Key Length Padrão
```bash
# Definir tipo de chave padrão
acme-ng --set-default-key-length ec-256  # ECC P-256 (padrão)
acme-ng --set-default-key-length ec-384  # ECC P-384
acme-ng --set-default-key-length 3072    # RSA 3072
```

### Configurar Renovação Automática
```bash
# Dias para renovação antecipada (padrão: 30)
acme-ng --set-default-renew-days 60

# Habilitar/notificações
acme-ng --install --notify-level renew
```

## Troubleshooting

### Erro: "Cannot write to directory"

#### Solução
```bash
# Verificar permissões
ls -la ~ | grep acme

# Corrigir permissões
chmod 755 ~/.acme-ng
chown -R $USER:$USER ~/.acme-ng
```

### Erro: "Cron job failed to install"

#### Verificar Cron
```bash
# Verificar se cron está rodando
systemctl status cron      # Debian/Ubuntu
systemctl status crond     # RHEL/CentOS

# Instalar cron manualmente
crontab -l | grep acme || (crontab -l; echo "0 0 * * * $HOME/.acme-ng/acme-ng --cron") | crontab -
```

### Erro: "Command not found"

#### Adicionar ao PATH
```bash
# Verificar se está no PATH
which acme-ng

# Se não encontrado, adicionar ao PATH
export PATH="$HOME/.acme-ng:$PATH"
echo 'export PATH="$HOME/.acme-ng:$PATH"' >> ~/.bashrc
```

### Erro: "Insufficient permissions"

#### Executar como Usuário Correto
```bash
# Não use sudo desnecessariamente
# acme-ng funciona como usuário normal

# Se já instalou com sudo, corrigir permissões
sudo chown -R $USER:$USER ~/.acme-ng
```

### Erro: "SOCAT not found"

#### Instalar SOCAT
```bash
# Debian/Ubuntu
sudo apt install socat

# RHEL/CentOS
sudo yum install socat

# Fedora
sudo dnf install socat

# macOS
brew install socat
```

### Erro: "OpenSSL not found"

#### Instalar OpenSSL
```bash
# Debian/Ubuntu
sudo apt install openssl

# RHEL/CentOS
sudo yum install openssl

# Fedora
sudo dnf install openssl

# macOS
brew install openssl
```

## Migração de Outro ACME Client

### De Let's Encrypt Client (certbot)
```bash
# 1. Listar certificados atuais
certbot certificates

# 2. Emitir novos certificados com acme-ng
acme-ng --issue -d example.com -w /var/www/html

# 3. Instalar nos mesmos locais
acme-ng --install-cert -d example.com \
  --key-file /etc/letsencrypt/live/example.com/privkey.pem \
  --fullchain-file /etc/letsencrypt/live/example.com/fullchain.pem

# 4. Remover certbot (opcional)
sudo apt remove certbot
```

### De Outro acme-ng Installation
```bash
# Copiar diretório inteiro
rsync -av old-server:~/.acme-ng/ ~/.acme-ng/

# Ou copiar apenas certificados
scp old-server:~/.acme-ng/example.com/* ~/.acme-ng/example.com/

# Reinstalar mantendo certificados
acme-ng --install --force -m email@example.com
```

## Validação e Testes

### Testar Instalação
```bash
# Verificar versão
acme-ng --version

# Testar comando básico
acme-ng --help

# Verificar estrutura
ls -la ~/.acme-ng/

# Testar emissão em staging
acme-ng --issue --staging -d test.example.com -w /var/www/html
```

### Verificar Cron Job
```bash
# Listar cron jobs
crontab -l

# Deve mostrar algo como:
# 0 0 * * * "/home/user/.acme-ng"/acme-ng --cron --home "/home/user/.acme-ng" > /dev/null
```

### Testar Renovação
```bash
# Simular renovação (dry-run)
acme-ng --renew -d example.com --dry-run

# Forçar renovação real (use com cuidado)
acme-ng --renew -d example.com --force
```

## Desinstalação

### Remover Completamente
```bash
# 1. Remover cron job
crontab -l | grep -v acme | crontab -

# 2. Remover diretório
rm -rf ~/.acme-ng

# 3. Remover do PATH se adicionou
sed -i '/acme-ng/d' ~/.bashrc
source ~/.bashrc
```

### Manter Certificados
```bash
# Backup dos certificados antes de remover
mkdir -p ~/backup-ssl
cp -r ~/.acme-ng/*/ ~/backup-ssl/

# Depois remover instalação
rm -rf ~/.acme-ng
```

## Best Practices

### 1. Use Últimas Versões
✅ Habilite auto-upgrade  
✅ Atualize mensalmente  
✅ Monitore changelogs  

### 2. Proteja a Instalação
✅ Permissões corretas: `chmod 755 ~/.acme-ng`  
✅ Não compartilhe account.conf  
✅ Use tokens de API com escopo limitado  

### 3. Mantenha Backups
✅ Backup regular de `~/.acme-ng/account.conf`  
✅ Documente domínios instalados  
✅ Teste restauração  

### 4. Monitore Renovações
✅ Configure notificações  
✅ Verifique logs periodicamente  
✅ Teste renovações manualmente  

### 5. Use Usuário Normal
✅ Não instale como root desnecessariamente  
✅ Cada usuário pode ter sua instalação  
✅ Mais seguro e isolado  

## Links Úteis

- **Site Oficial**: https://acme-ng
- **GitHub**: https://github.com/acmesh-official/acme-ng
- **Wiki de Instalação**: https://github.com/acmesh-official/acme-ng/wiki/Install
- **Docker Hub**: https://hub.docker.com/r/neilpang/acme-ng
- **Preparação**: https://github.com/acmesh-official/acme-ng/wiki/Install-preparations
