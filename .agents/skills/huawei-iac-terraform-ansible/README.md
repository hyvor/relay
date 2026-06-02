# Huawei Cloud IAC — Terraform + Ansible Skill

Skill para agentes de IA (Claude Code, Codex, OpenCode, Google Antigravity) focado em desenvolvimento de **Infrastructure as Code** na **Huawei Cloud** usando **Terraform** + **Ansible**.

---

## Visão Geral

Este skill fornece instruções, templates e convenções para criar, modificar e gerenciar infraestrutura Huawei Cloud de forma consistente e segura. Baseado em padrões reais de produção com integração Azure Key Vault e CI/CD via Atlantis.

| Componente | Tecnologia | Versão |
|-----------|-----------|--------|
| IaC Engine | Terraform (HCL) | >= 1.5.0 |
| Huawei Provider | `huaweicloud/huaweicloud` | 1.86.0 |
| Azure Provider | `hashicorp/azurerm` | 4.30.0 |
| Config Management | Ansible | >= 2.15 |
| Backend State | Azure Blob Storage | via `azurerm` |
| Secrets | Azure Key Vault | `kv-{env}-eastus2-cloud` |
| CI/CD | Atlantis | v3 |
| Regiões | `la-south-2` (principal), `sa-brazil-1` (secundária) | |

---

## Como Usar

### Claude Code

```
Carregue o skill usando a ferramenta Skill com o nome "huawei-iac-terraform-ansible"
ou referencie o SKILL.md diretamente.
```

### Codex / OpenCode

O skill segue formato markdown padrão. Copie o conteúdo de `SKILL.md` para o contexto do agente ou referencie-o como instrução de projeto.

### Google Antigravity

Compatível com o formato padrão de instruções de agente. Nenhuma adaptação necessária.

---

## Estrutura de Arquivos

```
skills/huawei-iac-terraform-ansible/
├── SKILL.md                           # Instruções completas do skill (715 linhas)
├── README.md                          # Este arquivo
└── templates/
    ├── .gitignore                      # Gitignore padrão (Terraform + Ansible)
    ├── atlantis.yaml                   # Configuração CI/CD Atlantis
    ├── scripts/
    │   └── load-env.sh                # Loader de ambiente (Azure KV → env vars)
    ├── terraform/
    │   ├── version.tf                  # Providers e versões
    │   ├── backend.tf                  # Backend azurerm (dinâmico)
    │   ├── provider.tf                 # Providers huaweicloud + azurerm
    │   ├── variables.tf               # Todas as variáveis (37 variáveis)
    │   ├── c-outputs.tf               # Outputs (ECS, CCE, RDS, DCS, Ansible)
    │   ├── d-network.tf               # Data sources (VPC, subnets, secgroups)
    │   ├── d-vault.tf                  # Data sources (Azure Key Vault)
    │   ├── r-cce-k8s.tf               # Módulo CCE Kubernetes
    │   ├── r-ecs-machine-app.tf       # Módulo ECS máquinas de aplicação
    │   ├── r-rds-database.tf          # Módulo RDS PostgreSQL
    │   ├── r-dcs-redis.tf             # Módulo DCS Redis
    │   ├── r-obs-bucket.tf            # Módulo OBS object storage
    │   ├── r-kms-key.tf               # Módulo KMS encryption keys
    │   ├── r-iam-identity.tf          # Módulo IAM users/groups/policies
    │   ├── r-evs-volume.tf            # Módulo EVS volumes
    │   ├── r-vault-secret.tf          # Módulo Azure Key Vault secrets
    │   ├── e-dev.tfvars               # Ambiente DEV
    │   ├── e-pre.tfvars               # Ambiente PRE-PROD
    │   └── e-prd.tfvars               # Ambiente PROD
    └── ansible/
        ├── ansible.cfg                # Configuração Ansible
        ├── inventory/
        │   └── hosts.yml              # Inventário estático (template)
        ├── playbooks/
        │   ├── site.yml               # Playbook master
        │   └── base.yml               # Playbook standalone base
        ├── group_vars/
        │   └── all.yml                # Variáveis globais
        └── roles/
            ├── common/                # Base (pacotes, hostname, DNS, NTP, SSH, users)
            │   ├── tasks/             # 7 tarefas
            │   ├── handlers/          # 3 handlers
            │   ├── templates/         # 3 templates (resolv.conf, chrony, sshd_config)
            │   └── defaults/          # Variáveis padrão
            ├── security/              # CIS hardening, fail2ban, iptables
            │   ├── tasks/
            │   ├── handlers/
            │   └── templates/         # fail2ban jail.local
            ├── docker/                # Docker CE + Docker Compose
            │   ├── tasks/
            │   ├── handlers/
            │   └── templates/         # daemon.json
            ├── monitoring/            # Prometheus Node Exporter
            │   ├── tasks/
            │   ├── handlers/
            │   └── templates/         # systemd service
            └── backup/                # OBS backup via s3fs
                └── tasks/
```

**Total: 50 arquivos | 2.611 linhas**

---

## Padrões Implementados

### Terraform

| Padrão | Exemplo |
|--------|---------|
| Nomes de arquivo | `c-provider.tf`, `r-network.tf`, `d-vpc.tf`, `e-prd.tfvars` |
| Recursos condicionais | `for_each` + `if value.provision == true` |
| Atributos opcionais | `try(each.value.image_name, "Rocky Linux 9.0 64bit")` |
| Tags | `merge(var.product_tags, var.global_tags, { "environment" = var.environment })` |
| Módulos externos | `source = "git@gitlab.com:...//module?ref=vX.Y.Z"` |
| Data sources opcionais | `count = var.flag == true ? 1 : 0` |
| Backend dinâmico | Block vazio + config via `-backend-config` no init |

### Ansible

| Padrão | Exemplo |
|--------|---------|
| DNS Huawei Cloud | `100.125.0.250`, `100.125.1.250` |
| NTP Huawei Cloud | `ntp.myhuaweicloud.com` |
| OBS Backup | `s3fs` mount + cron jobs |
| CCE Deploy | kubeconfig via API + Helm charts |
| Inventário dinâmico | `terraform output -json` → `hosts.yml` |
| Segredos | `ansible-vault` ou variáveis de ambiente |

---

## Comandos Rápidos

```bash
# Inicializar projeto
terraform init -input=false -reconfigure -upgrade \
  -backend-config="storage_account_name=$AZURE_STORAGE_NAME" \
  -backend-config="container_name=$AZURE_STORAGE_CONTAINER" \
  -backend-config="key=${CLOUD}/${ACCOUNT}/iac-shared-${PRODUCT}-tfstate"

# Validar e planejar
terraform validate
terraform fmt -check -recursive
terraform plan -var-file=e-prd.tfvars

# Carregar ambiente via script
source scripts/load-env.sh prd

# Ansible
ansible-playbook --syntax-check playbooks/site.yml
ansible-playbook -i inventory/hosts.yml playbooks/site.yml --check  # dry run
ansible-playbook -i inventory/hosts.yml playbooks/site.yml           # apply

# Segurança
checkov -d .
ansible-lint playbooks/
```

---

## Serviços Huawei Cloud Suportados

| Serviço | Módulo | Descrição |
|---------|--------|-----------|
| VPC | `network-main` | VPC, subnets, peering |
| ECS | `ecs` | Máquinas virtuais (app, db, jump, dns, public, ts, dc) |
| CCE | `cce` | Clusters Kubernetes gerenciados |
| RDS | `rds` | PostgreSQL gerenciado |
| DCS | `dcs` | Redis gerenciado |
| OBS | `obs` | Object storage |
| KMS | `kms` | Chaves de criptografia |
| IAM | `iam` | Usuários, grupos, políticas |
| EVS | `evs` | Volumes de armazenamento |
| VPN | `vpn-ipsec` | VPN site-to-site |
| NAT | `nat-gateway` | NAT Gateway |
| EIP | `elastic-ip` | IPs elásticos |

---

## Requisitos

- Terraform >= 1.5.0
- Ansible >= 2.15
- Azure CLI (`az`) autenticado
- Acesso ao Azure Key Vault `kv-{env}-eastus2-cloud`
- Chave SSH para ECS (`~/.ssh/huawei_ecs.pem`)
- Acesso GitLab SSH para módulos (`git@gitlab.com:neogrid1/...`)

---

## Referências

- [SKILL.md](./SKILL.md) — Documentação completa do skill
- [Huawei Cloud Terraform Provider](https://registry.terraform.io/providers/huaweicloud/huaweicloud/latest/docs)
- [Huawei Cloud Services](https://www.huaweicloud.com/intl/pt-br/product/)
- [Ansible Documentation](https://docs.ansible.com/)
- [Atlantis Documentation](https://www.runatlantis.io/docs/)
