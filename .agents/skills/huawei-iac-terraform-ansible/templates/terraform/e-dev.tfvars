##############################
## DEV ENVIRONMENT          ##
##############################

# e-dev.tfvars

environment = "dev"
product     = "cloud-services"
region      = "la-south-2"

global_tags = {
  provisioning    = "terraform"
  iac             = "iac-shared-oem-huawei"
  bu              = "coe-cloud"
  owner           = "infra.ss@company.com"
  confidentiality = "confidencial"
}

product_tags = {
  product = "core-vms"
  offer   = "cloud-services"
}

key_vault_name = "kv-dev-eastus2-cloud"
key_vault_rg   = "rg-dev-eastus2-cloud"

secgroup_cce_name      = "sg-devops-01"
secgroup_database_name = "sg-database-01"

data_snet_cce_01_provision      = true
data_snet_database_01_provision = true

cce_k8s = {
  cluster-01 = {
    provision              = true
    flavor_id              = "cce.s2.small"
    container_network_type = "overlay_l2"
    container_network_cidr = "172.18.0.0/16"
    service_network_cidr   = "10.249.0.0/16"
    node = {
      node-01 = {
        flavor_id         = "s6.large.2"
        availability_zone = "la-south-2a"
        count             = 1
      }
    }
  }
}

ecs_machine_app = {
  app-01 = {
    provision       = true
    flavor_id       = "s6.large.2"
    subnet_name     = "snet-dev-lasouth2-devops-01"
    security_name   = ["sg-devops-01"]
    system_disk_size = 40
    tags = { function = "application", created = "13-03-2026", backup = "no" }
  }
}

ecs_machine_db    = {}
rds_database      = {}
dcs_redis         = {}
obs_bucket        = {}
kms_key           = { ecs = { provision = true } }
iam_identity      = {}
evs_volume        = {}
key_vault_secret  = {}
