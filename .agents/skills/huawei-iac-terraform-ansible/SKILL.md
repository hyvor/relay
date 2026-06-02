# Huawei Cloud IAC — Terraform + Ansible Skill

> **Scope:** Infrastructure as Code for Huawei Cloud using Terraform (provisioning) and Ansible (configuration management). Compatible with Claude Code, Codex, OpenCode, and Google Antigravity.


Expert Infrastructure as Code for Cloud Based using Terraform, Pulumi, CloudFormation, ARM, Bicep, CDK, Crossplane, OpenTofu(provisioning) and Ansible, Chef, Puppet, SaltStack (configuration management). 

---

## 1. When to Activate This Skill

Activate when the user request involves ANY of the following:

- Creating, modifying, or reviewing Terraform `.tf` files targeting Huawei Cloud
- Writing or reviewing Ansible playbooks/roles for Huawei Cloud ECS/CCE instances
- Provisioning Huawei Cloud resources: VPC, ECS, CCE, RDS, DCS, OBS, KMS, IAM, EVS, NAT, VPN, EIP
- Configuring multi-account or multi-environment Huawei Cloud infrastructure
- Integrating Azure Key Vault with Huawei Cloud Terraform workflows
- Setting up Atlantis, CI/CD pipelines, or `load-env.sh` for Huawei Cloud IAC
- Post-provisioning configuration of Huawei Cloud VMs (ECS) via Ansible

**Keywords / triggers:** `huawei`, `huaweicloud`, `terraform huawei`, `iac huawei`, `ansible huawei`, `ecs configure`, `cce deploy`, `huawei cloud infrastructure`

---

## 2. Technology Stack

| Component | Technology | Version |
|-----------|-----------|---------|
| IaC Engine | Terraform (HCL) | >= 1.5.0 (recommended 1.9.x) |
| Huawei Provider | `huaweicloud/huaweicloud` | 1.86.0 |
| Azure Provider | `hashicorp/azurerm` | 4.30.0 |
| Random Provider | `hashicorp/random` | 3.8.1 |
| Configuration Mgmt | Ansible | >= 2.15 |
| Backend State | Azure Blob Storage | via `azurerm` backend |
| Secrets | Azure Key Vault | `kv-{env}-eastus2-cloud` |
| CI/CD | Atlantis | v3 workflows |
| Module Registry | GitLab (SSH) | `git@gitlab.com:...` |
| Regions | `la-south-2` (primary), `sa-brazil-1` (secondary) |

---

## 3. Project Directory Structure

```
project-name/
├── terraform/                     # Terraform root
│   ├── version.tf                 # required_version + required_providers
│   ├── backend.tf                 # azurerm backend (empty block)
│   ├── provider.tf                # huaweicloud + azurerm providers
│   ├── variables.tf               # ALL variable declarations
│   ├── c-global-variables.tf      # Global vars (credentials, region, tags)
│   ├── d-network.tf               # Data sources (VPC, subnets, secgroups)
│   ├── d-vault.tf                 # Data sources (Azure Key Vault secrets)
│   ├── r-vpc.tf                   # VPC resources (if inline, prefer modules)
│   ├── r-ecs-machine-app.tf       # ECS module calls
│   ├── r-cce-k8s.tf               # CCE Kubernetes cluster
│   ├── r-rds-database.tf          # RDS PostgreSQL
│   ├── r-dcs-redis.tf             # DCS Redis
│   ├── r-obs-bucket.tf            # OBS object storage
│   ├── r-kms-key.tf               # KMS encryption keys
│   ├── r-iam-identity.tf          # IAM users/groups/policies
│   ├── r-vault-secret.tf          # Azure Key Vault secret management
│   ├── e-dev.tfvars               # Dev environment values
│   ├── e-pre.tfvars               # Pre-prod environment values
│   └── e-prd.tfvars               # Production environment values
├── ansible/
│   ├── ansible.cfg                # Ansible configuration
│   ├── inventory/
│   │   ├── hosts.yml              # Static inventory (or generated)
│   │   └── huawei_ec2.yml         # Dynamic inventory plugin
│   ├── playbooks/
│   │   ├── site.yml               # Master playbook
│   │   ├── base.yml               # Base OS hardening
│   │   └── docker.yml             # Docker installation
│   ├── roles/
│   │   ├── common/                # Base role (NTP, DNS, users, sshd)
│   │   ├── security/              # CIS hardening, firewall
│   │   ├── docker/                # Docker CE install + config
│   │   ├── monitoring/            # Node exporter, prometheus
│   │   └── backup/                # OBS backup agent
│   ├── group_vars/
│   │   ├── all.yml                # Global variables
│   │   ├── ecs_app.yml            # App server variables
│   │   └── ecs_db.yml             # DB server variables
│   └── host_vars/                 # Per-host overrides
├── scripts/
│   └── load-env.sh                # Environment loader (Azure KV → env vars)
├── atlantis.yaml                  # Atlantis CI/CD configuration
├── .gitignore
└── README.md
```

---

## 4. Terraform Conventions

### 4.1 File Naming (strict)

| Prefix | Purpose | Examples |
|--------|---------|---------|
| `c-` | Configuration | `c-provider.tf`, `c-backend.tf`, `c-global-variables.tf`, `c-outputs.tf` |
| `r-` | Resource definitions | `r-network.tf`, `r-ecs-machine-app.tf`, `r-cce-k8s.tf` |
| `d-` | Data source lookups | `d-vpc.tf`, `d-keyvault.tf`, `d-network.tf` |
| `e-` | Environment tfvars | `e-dev.tfvars`, `e-pre.tfvars`, `e-prd.tfvars` |

Standalone files: `version.tf`, `backend.tf`, `provider.tf`, `variables.tf`.

### 4.2 Provider Configuration

```hcl
# version.tf
terraform {
  required_version = ">= 1.5.0"
  required_providers {
    huaweicloud = {
      source  = "huaweicloud/huaweicloud"
      version = "1.86.0"
    }
    azurerm = {
      source  = "hashicorp/azurerm"
      version = "4.30.0"
    }
    random = {
      source  = "hashicorp/random"
      version = "3.8.1"
    }
  }
}
```

```hcl
# provider.tf
provider "huaweicloud" {
  region     = var.region
  access_key = var.hw_access_key
  secret_key = var.hw_secret_key
}

provider "azurerm" {
  features {}
  subscription_id = var.subscription_id
}
```

**Multi-account pattern** (use aliases):
```hcl
provider "huaweicloud" {
  alias      = "account_name"
  region     = var.region
  access_key = var.credentialshwc_account_name[0]
  secret_key = var.credentialshwc_account_name[1]
}
```

### 4.3 Backend (dynamic, never hardcode keys)

```hcl
# backend.tf — empty block, configured at init time
terraform {
  backend "azurerm" {}
}
```

```bash
# Init with dynamic backend config
terraform init -input=false -reconfigure -upgrade \
  -backend-config="storage_account_name=$AZURE_STORAGE_NAME" \
  -backend-config="container_name=$AZURE_STORAGE_CONTAINER" \
  -backend-config="key=${CLOUD}/${ACCOUNT}/iac-shared-${PRODUCT}-tfstate"
```

### 4.4 Variables

```hcl
# Always: description, type, default (for optional)
variable "ecs_machine_app" {
  description = "Mapa de configuração para máquinas ECS de aplicação."
  default     = {}  # optional maps use empty default
}

variable "environment" {
  description = "Ambiente dos recursos (dev, pre, prd)."
  type        = string
}

variable "hw_access_key" {
  description = "Chave de acesso Huawei Cloud."
  type        = string
  # NO default — must be provided via env or tfvars
}
```

**Rules:**
- Descriptions in Portuguese (pt-BR) or English — be consistent per project
- Always declare `type` for non-map variables
- Use `default = {}` for optional map variables
- Credential variables: NO default, NO value in version control
- Use `validation` blocks when enforcing constraints

### 4.5 Resource Pattern: `for_each` + Provision Filter

```hcl
module "huawei_ecs_machine_app" {
  source = "git@gitlab.com:neogrid1/cloud-services/huawei/terraform-modules/ecs//module?ref=v1.0.8"

  for_each = {
    for key, value in var.ecs_machine_app : key => value
    if value.provision == true
  }

  region      = var.region
  environment = var.environment
  name        = each.key
  flavor_id   = each.value.flavor_id
  image_name  = try(each.value.image_name, "Rocky Linux 9.0 64bit")
  tags        = merge(var.product_tags, var.global_tags, { "environment" = var.environment }, each.value.tags)
}
```

### 4.6 Data Sources (conditional loading)

```hcl
data "huaweicloud_vpc" "vpc_main_01" {
  name = "vpc-${var.environment}-${replace(var.region, "-", "")}-oem-01"
}

data "huaweicloud_vpc_subnet" "snet_cce_01" {
  count = var.data_snet_cce_01_provision == true ? 1 : 0
  name  = "snet-${var.environment}-${replace(var.region, "-", "")}-devops-01"
}

# Reference with [count.index] when conditional
vpc_id = data.huaweicloud_vpc_subnet.snet_cce_01[0].id
```

### 4.7 Tags (always merged)

```hcl
# Global tags (in tfvars)
global_tags = {
  provisioning    = "terraform"
  iac             = "project-name"
  bu              = "coe-cloud"
  owner           = "team@company.com"
  confidentiality = "confidencial"
}

# Product tags (in tfvars)
product_tags = {
  product = "cloud-services"
  offer   = "core-infra"
}

# Merge pattern (in resource)
tags = merge(var.product_tags, var.global_tags, { "environment" = var.environment })
```

### 4.8 Naming Conventions

| Resource Type | Pattern | Example |
|---------------|---------|---------|
| VPC | `vpc-{env}-{region}-{purpose}-{num}` | `vpc-prd-lasouth2-oem-01` |
| Subnet | `snet-{env}-{region}-{purpose}-{num}` | `snet-prd-lasouth2-devops-01` |
| Route Table | `rtb-vpc-{env}-{region}-{vpc_name}` | `rtb-vpc-prd-lasouth2-oem` |
| Security Group | `sg-{purpose}-{num}` | `sg-devops-01` |
| ECS Instance | `{purpose}-{num}` (via module) | `app-01`, `db-01` |
| Module name | `huawei_{service}_{purpose}` | `huawei_ecs_machine_app` |
| Data source | `data.huaweicloud_{resource}.{name}` | `data.huaweicloud_vpc.vpc_main_01` |

### 4.9 Comments & Documentation

```hcl
######################
## CCE K8S CREATION ##
######################

# https://gitlab.com/neogrid1/cloud-services/huawei/terraform-modules/cce

module "huawei_cce_k8s" {
  ...
}
```

- Banner comments: `## SECTION NAME ##` surrounded by `#`
- Module links: comment above with GitLab URL
- README.md: Portuguese, auto-generated via `terraform-docs`

### 4.10 Error Handling

- Use `try()` for optional attributes: `try(each.value.image_name, "Rocky Linux 9.0 64bit")`
- Use conditional `count` for optional data sources
- Never let `null` propagate to sensitive attributes (passwords, keys)
- Prefer `for_each` over `count` for resources (better state management)

---

## 5. Ansible Conventions

### 5.1 Inventory

**Static inventory** (`inventory/hosts.yml`):
```yaml
all:
  children:
    ecs_app:
      hosts:
        app-01:
          ansible_host: 10.148.1.10
        app-02:
          ansible_host: 10.148.1.11
    ecs_db:
      hosts:
        db-01:
          ansible_host: 10.148.2.10
  vars:
    ansible_user: root
    ansible_ssh_private_key_file: ~/.ssh/huawei_ecs.pem
```

**Dynamic inventory** — generate from Terraform outputs:
```bash
# After terraform apply, extract ECS IPs
terraform output -json ecs_ips | python3 -c "
import json, sys, yaml
data = json.load(sys.stdin)
inventory = {'all': {'children': {'ecs_app': {'hosts': {}}}}}
for name, ip in data.items():
    inventory['all']['children']['ecs_app']['hosts'][name] = {'ansible_host': ip}
print(yaml.dump(inventory))
" > ansible/inventory/hosts.yml
```

### 5.2 Ansible Configuration

```ini
# ansible.cfg
[defaults]
inventory = inventory/hosts.yml
roles_path = roles
remote_user = root
host_key_checking = False
retry_files_enabled = False
stdout_callback = yaml

[ssh_connection]
pipelining = True
ssh_args = -o ControlMaster=auto -o ControlPersist=60s -o StrictHostKeyChecking=no
```

### 5.3 Playbook Structure

```yaml
# playbooks/site.yml
---
- name: Base OS configuration
  hosts: all
  become: true
  roles:
    - common
    - security

- name: Application servers
  hosts: ecs_app
  become: true
  roles:
    - docker
    - monitoring

- name: Database servers
  hosts: ecs_db
  become: true
  roles:
    - monitoring
    - backup
```

### 5.4 Role Structure

```
roles/common/
├── tasks/
│   ├── main.yml          # Entry point, includes others
│   ├── packages.yml      # yum/apt package installs
│   ├── users.yml         # System users and SSH keys
│   ├── sshd.yml          # SSH hardening
│   ├── ntp.yml           # Chrony/NTP config
│   └── dns.yml           # DNS resolver config
├── handlers/
│   └── main.yml          # Service restart handlers
├── templates/
│   ├── sshd_config.j2
│   └── chrony.conf.j2
├── files/
│   └── authorized_keys
├── defaults/
│   └── main.yml          # Default variables
├── vars/
│   └── main.yml          # Role-specific variables
└── meta/
    └── main.yml          # Role dependencies
```

### 5.5 Huawei Cloud-Specific Ansible Patterns

**ECS instance post-provisioning:**
```yaml
# roles/common/tasks/main.yml
---
- name: Install base packages
  yum:
    name:
      - vim
      - curl
      - wget
      - htop
      - net-tools
      - python3
    state: present

- name: Set hostname
  hostname:
    name: "{{ inventory_hostname }}"

- name: Configure DNS (Huawei internal)
  template:
    src: resolv.conf.j2
    dest: /etc/resolv.conf
  vars:
    dns_servers:
      - 100.125.0.250    # Huawei Cloud DNS
      - 100.125.1.250

- name: Mount OBS via s3fs (backup agent)
  include_tasks: obs_mount.yml
  when: obs_backup_enabled | default(false)
```

**CCE cluster post-deployment:**
```yaml
# playbooks/cce-postdeploy.yml
---
- name: Post-deploy CCE configuration
  hosts: localhost
  connection: local
  gather_facts: false
  tasks:
    - name: Get kubeconfig from Huawei CCE
      uri:
        url: "https://cce.{{ region }}.myhuaweicloud.com/api/v3/projects/{{ project_id }}/clusters/{{ cluster_id }}/clustercert"
        method: GET
        headers:
          X-Auth-Token: "{{ hw_auth_token }}"
        status_code: 200
      register: kubeconfig_response

    - name: Write kubeconfig
      copy:
        content: "{{ kubeconfig_response.json | to_nice_yaml }}"
        dest: "~/.kube/config-{{ cluster_name }}"
        mode: '0600'

    - name: Deploy base Helm charts
      kubernetes.core.helm:
        name: "{{ item.name }}"
        chart_ref: "{{ item.chart }}"
        release_namespace: "{{ item.namespace }}"
        create_namespace: true
        values: "{{ item.values | default({}) }}"
      loop: "{{ helm_releases }}"
```

### 5.6 Group Variables

```yaml
# group_vars/all.yml
---
hw_region: "la-south-2"
hw_project_id: "{{ lookup('env', 'HW_PROJECT_ID') }}"
ansible_python_interpreter: /usr/bin/python3

# Huawei Cloud DNS
huawei_dns_private:
  - 100.125.0.250
  - 100.125.1.250

# group_vars/ecs_app.yml
---
docker_edition: "ce"
docker_version: "24.0"
node_exporter_version: "1.7.0"
obs_backup_bucket: "bucket-oem-shared-backup-01"
```

### 5.7 Secrets in Ansible

- **Never** store secrets in playbooks or group_vars in version control
- Use `ansible-vault` for encrypted variables: `ansible-vault encrypt_string 'secret_value'`
- Use `lookup('hashi_vault', 'secret/data/huawei:hw_access_key')` for Vault integration
- Or load from environment: `hw_auth_token: "{{ lookup('env', 'HW_AUTH_TOKEN') }}"`

---

## 6. Terraform → Ansible Integration

### 6.1 Generate Inventory from Terraform

```hcl
# outputs.tf
output "ecs_instances" {
  description = "Mapa de instâncias ECS criadas com IPs e metadados."
  value = {
    for name, instance in module.huawei_ecs_machine_app : name => {
      private_ip = instance.private_ip
      public_ip  = instance.public_ip
      flavor     = instance.flavor_id
      az         = instance.availability_zone
    }
  }
}

output "cce_cluster_endpoint" {
  description = "Endpoint do cluster CCE."
  value       = module.huawei_cce_k8s["cluster-01"].api_endpoint
}
```

```bash
# Generate Ansible inventory after terraform apply
terraform output -json ecs_instances | \
  jq -r '
    ["[ecs_app]"] + 
    (to_entries[] | "\(.key) ansible_host=\(.value.private_ip)") 
    | .[]
  ' > ansible/inventory/hosts.ini
```

### 6.2 Terraform `local-exec` for Ansible Trigger

```hcl
resource "null_resource" "ansible_configure" {
  for_each = module.huawei_ecs_machine_app

  triggers = {
    instance_id = each.value.id
  }

  provisioner "local-exec" {
    command = <<-EOT
      ansible-playbook \
        -i ansible/inventory/hosts.yml \
        ansible/playbooks/site.yml \
        --limit ${each.key} \
        -e "hw_auth_token=${var.hw_access_key}"
    EOT
    working_dir = path.module
  }

  depends_on = [module.huawei_ecs_machine_app]
}
```

### 6.3 Dynamic Inventory Plugin

```yaml
# ansible/inventory/huawei_ec2.yml
plugin: amazon.aws.aws_ec2  # Huawei supports EC2-compatible API

regions:
  - la-south-2

hostnames:
  - tag:Name
  - private-ip-address

keyed_groups:
  - key: tags.role
    prefix: ecs
  - key: placement.availability_zone
    prefix: az

filters:
  tag:provisioning: terraform
  instance-state-name: running
```

---

## 7. Workflows

### 7.1 New Huawei Cloud Project

1. Create directory structure (Section 3)
2. Copy `version.tf`, `backend.tf`, `provider.tf` from templates
3. Define `variables.tf` with all resource maps
4. Create `e-{env}.tfvars` for each environment
5. Add module calls in `r-{service}.tf` files
6. Add data sources in `d-{resource}.tf` files
7. Run `terraform init` → `terraform validate` → `terraform plan`
8. Create `ansible/` with roles for post-provisioning

### 7.2 New ECS Machine Type

1. Add variable map in `variables.tf`:
   ```hcl
   variable "ecs_machine_newtype" {
     description = "Mapa para máquinas ECS do tipo novo."
     default     = {}
   }
   ```
2. Create `r-ecs-machine-newtype.tf` with module call
3. Add values in `e-{env}.tfvars`:
   ```hcl
   ecs_machine_newtype = {
     server-01 = {
       provision    = true
       flavor_id    = "s6.large.2"
       subnet_name  = "snet-prd-lasouth2-app-01"
       tags         = { function = "newtype", ... }
     }
   }
   ```
4. Add Ansible role under `roles/newtype/`

### 7.3 Environment Promotion

```bash
# 1. Validate in dev
terraform workspace select dev
terraform plan -var-file=e-dev.tfvars

# 2. Apply in dev
terraform apply -var-file=e-dev.tfvars

# 3. Run Ansible against dev
ansible-playbook -i inventory/hosts.yml playbooks/site.yml -l ecs_app

# 4. Promote to pre (same code, different tfvars)
terraform workspace select pre
terraform plan -var-file=e-pre.tfvars

# 5. Promote to prd (requires Atlantis approval)
terraform workspace select prd
terraform plan -var-file=e-prd.tfvars
```

---

## 8. Validation Commands

```bash
# Terraform
terraform fmt -check -recursive          # Lint formatting
terraform fmt -recursive                 # Auto-format
terraform validate                       # Syntax + config validation
terraform plan -var-file=e-prd.tfvars    # Dry-run plan
checkov -d .                             # Security scan (if installed)

# Ansible
ansible-playbook --syntax-check playbooks/site.yml
ansible-lint playbooks/
ansible-playbook -i inventory/hosts.yml playbooks/site.yml --check  # Dry run

# Full pipeline
source scripts/load-env.sh prd
terraform init -input=false -reconfigure -upgrade \
  -backend-config="storage_account_name=$AZURE_STORAGE_NAME" \
  -backend-config="container_name=$AZURE_STORAGE_CONTAINER" \
  -backend-config="key=${CLOUD}/${ACCOUNT}/iac-shared-${PRODUCT}-tfstate"
terraform validate
terraform plan -input=false -refresh -out plan.out -var-file=e-prd.tfvars
```

---

## 9. Security Rules

1. **NEVER** hardcode credentials in `.tf`, `.tfvars`, or `.yml` files
2. **ALWAYS** use Azure Key Vault for secrets: `data.azurerm_key_vault_secret`
3. **ALWAYS** use `TF_VAR_*` env vars or `az keyvault secret show` for runtime credentials
4. **PIN** all module versions: `?ref=vX.Y.Z`
5. **ENCRYPT** Ansible variables with `ansible-vault`
6. **RESTRICT** `.gitignore`: exclude `.terraform/`, `*.tfstate`, `*.lock.hcl`, `crash.log`, override files
7. **AUDIT** VPN PSKs — never commit them, store in Key Vault
8. **VALIDATE** inputs with `variable` validation blocks
9. **USE** `try()` instead of allowing `null` to propagate to sensitive attributes
10. **REVIEW** with `checkov -d .` before every apply

---

## 10. Huawei Cloud Resource Quick Reference

| Service | Module | Key Attributes |
|---------|--------|---------------|
| VPC | `network-main` | CIDR, subnets, peering |
| ECS (VM) | `ecs` | flavor, image, disk, network, kms |
| CCE (K8s) | `cce` | flavor, nodes, pools, addons, networking |
| RDS (DB) | `rds` | flavor, volume, backup, availability_zone |
| DCS (Redis) | `dcs` | flavor, capacity, availability_zone |
| OBS (S3) | `obs` | bucket, acl, website, lifecycle |
| KMS | `kms` | key_spec, pending_days, is_enabled |
| IAM | `iam` | users, groups, policies, custom_policy |
| EVS (Disk) | `evs` | size, type, availability_zone |
| VPN IPsec | `vpn-ipsec` | gateway, connections, psk, peer subnets |
| NAT Gateway | `nat-gateway` | spec, snat_rules, dnat_rules |
| Elastic IP | `elastic-ip` | size, type, bandwidth |

---

## 11. Compatibility Notes

This skill follows portable conventions that work across agent frameworks:

- **Claude Code:** Load via `skill` tool or reference SKILL.md directly
- **Codex:** Reads SKILL.md/AGENTS.md from repo root; instructions apply as context
- **OpenCode:** Follows standard markdown skill format with numbered sections
- **Google Antigravity:** Uses standard agent instruction format; no platform-specific hooks
- **All agents:** File naming (`c-`, `r-`, `d-`, `e-`) and code patterns are framework-agnostic
