########################
## HUAWEI CREDENTIALS ##
########################

variable "hw_access_key" {
  description = "Chave de acesso do usuário terraform na Huawei Cloud."
  type        = string
}

variable "hw_secret_key" {
  description = "Secret de acesso do usuário terraform na Huawei Cloud."
  type        = string
}

#######################
## AZURE CREDENTIALS ##
#######################

variable "subscription_id" {
  description = "Subscription ID na Azure."
  type        = string
}

######################
## GLOBAL VARIABLES ##
######################

variable "environment" {
  description = "Ambiente dos recursos (dev, pre, prd)."
  type        = string
}

variable "product" {
  description = "Nome do produto."
  type        = string
}

variable "region" {
  description = "Região da Huawei Cloud."
  type        = string
  default     = "la-south-2"
}

variable "global_tags" {
  description = "Tags globais aplicadas a todos os recursos."
  type        = map(string)
}

variable "product_tags" {
  description = "Tags específicas do produto."
  type        = map(string)
}

############################
## DATA SOURCE TOGGLES    ##
############################

variable "data_snet_cce_01_provision" {
  description = "Habilita busca do ID da subnet CCE."
  type        = bool
  default     = false
}

variable "data_snet_database_01_provision" {
  description = "Habilita busca do ID da subnet de database."
  type        = bool
  default     = false
}

############################
## AZURE KEY VAULT        ##
############################

variable "key_vault_name" {
  description = "Nome do Azure Key Vault onde os segredos estão armazenados."
  type        = string
}

variable "key_vault_rg" {
  description = "Nome do Resource Group do Azure onde o Key Vault está localizado."
  type        = string
}

variable "key_vault_secret" {
  description = "Mapa de configuração para segredos do Key Vault."
  default     = {}
}

variable "key_vault_secret_ecs" {
  description = "Nome do segredo da senha ECS no Key Vault."
  type        = string
  default     = "huawei-ecs-machine-password"
}

################################
## SECURITY GROUP             ##
################################

variable "secgroup_cce_name" {
  description = "Nome do security group do CCE."
  type        = string
  default     = "sg-devops-01"
}

variable "secgroup_database_name" {
  description = "Nome do security group do banco de dados."
  type        = string
  default     = "sg-database-01"
}

############################
## RESOURCE MAPS          ##
############################

variable "cce_k8s" {
  description = "Mapa de configuração para clusters CCE Kubernetes."
  default     = {}
}

variable "ecs_machine_app" {
  description = "Mapa de configuração para máquinas ECS de aplicação."
  default     = {}
}

variable "ecs_machine_db" {
  description = "Mapa de configuração para máquinas ECS de banco de dados."
  default     = {}
}

variable "ecs_machine_jump" {
  description = "Mapa de configuração para máquinas ECS de jump server."
  default     = {}
}

variable "ecs_machine_dns" {
  description = "Mapa de configuração para máquinas ECS de DNS."
  default     = {}
}

variable "ecs_machine_public" {
  description = "Mapa de configuração para máquinas ECS públicas."
  default     = {}
}

variable "ecs_machine_dc" {
  description = "Mapa de configuração para máquinas ECS de domínio controlador."
  default     = {}
}

variable "ecs_machine_ts" {
  description = "Mapa de configuração para máquinas ECS de terminal services."
  default     = {}
}

variable "rds_database" {
  description = "Mapa de configuração para bancos de dados RDS."
  default     = {}
}

variable "dcs_redis" {
  description = "Mapa de configuração para instâncias DCS Redis."
  default     = {}
}

variable "obs_bucket" {
  description = "Mapa de configuração para buckets OBS."
  default     = {}
}

variable "kms_key" {
  description = "Mapa de configuração para chaves KMS."
  default     = {}
}

variable "iam_identity" {
  description = "Mapa de configuração para identidades IAM."
  default     = {}
}

variable "evs_volume" {
  description = "Mapa de configuração para volumes EVS."
  default     = {}
}
