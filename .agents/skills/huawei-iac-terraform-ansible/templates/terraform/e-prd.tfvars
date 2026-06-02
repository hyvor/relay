##############################
## PRODUCTION ENVIRONMENT   ##
##############################

# e-prd.tfvars

environment = "prd"
product     = "cloud-services"
region      = "la-south-2"

# Global tags
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

# Azure Key Vault
key_vault_name = "kv-prd-eastus2-cloud"
key_vault_rg   = "rg-prd-eastus2-cloud"

# Security Groups
secgroup_cce_name      = "sg-devops-01"
secgroup_database_name = "sg-database-01"

# Data source toggles
data_snet_cce_01_provision      = true
data_snet_database_01_provision = true

# CCE Kubernetes
cce_k8s = {
  cluster-01 = {
    provision                = true
    flavor_id                = "cce.s2.small"
    container_network_type   = "overlay_l2"
    container_network_cidr   = "172.16.0.0/16"
    service_network_cidr     = "10.247.0.0/16"
    node = {
      node-01 = {
        flavor_id         = "s6.xlarge.2"
        availability_zone = "la-south-2a"
        count             = 3
      }
    }
    node_pools = {}
    namespaces = {
      default = {}
    }
    pvcs   = {}
    addons = {}
  }
}

# ECS Machines - App Servers
ecs_machine_app = {
  app-01 = {
    provision       = true
    flavor_id       = "s6.large.2"
    subnet_name     = "snet-prd-lasouth2-devops-01"
    security_name   = ["sg-devops-01"]
    system_disk_size = 40
    tags = {
      function = "application"
      created  = "13-03-2026"
      backup   = "yes"
    }
  }
  app-02 = {
    provision       = true
    flavor_id       = "s6.large.2"
    subnet_name     = "snet-prd-lasouth2-devops-01"
    security_name   = ["sg-devops-01"]
    system_disk_size = 40
    tags = {
      function = "application"
      created  = "13-03-2026"
      backup   = "yes"
    }
  }
}

# ECS Machines - DB Servers
ecs_machine_db = {
  db-01 = {
    provision       = true
    flavor_id       = "s6.xlarge.2"
    subnet_name     = "snet-prd-lasouth2-database-01"
    security_name   = ["sg-database-01"]
    system_disk_size = 100
    system_disk_type = "SSD"
    tags = {
      function = "database"
      created  = "13-03-2026"
      backup   = "yes"
    }
  }
}

# RDS PostgreSQL
rds_database = {
  postgres-prd-01 = {
    provision           = true
    flavor_id           = "rds.pg.n1.large.2"
    availability_zones  = ["la-south-2a", "la-south-2b"]
    db_version          = "14"
    volume_size         = 100
    volume_type         = "ULTRAHIGH"
    backup_keep_days    = 7
    backup_start_time   = "02:00-03:00"
    tags = {
      function = "database"
      created  = "13-03-2026"
      backup   = "yes"
    }
  }
}

# DCS Redis
dcs_redis = {
  redis-prd-01 = {
    provision           = true
    flavor              = "redis.ha.xu1.large.r2.1"
    capacity            = 2
    engine_version      = "5.0"
    availability_zones  = ["la-south-2a"]
    tags = {
      function = "cache"
      created  = "13-03-2026"
      backup   = "no"
    }
  }
}

# OBS Buckets
obs_bucket = {
  shared-cloud-01 = {
    provision   = true
    user_domain = ["bucket-prd.neogrid.com"]
    website     = { enabled = false }
    tags = {
      function = "storage"
      created  = "13-03-2026"
      backup   = "no"
    }
  }
}

# KMS Keys
kms_key = {
  ecs = {
    provision    = true
    key_spec     = "AES_256"
    pending_days = 7
    is_enabled   = true
    tags = {
      function = "encryption"
      created  = "13-03-2026"
    }
  }
}

# IAM Identities
iam_identity = {
  ansible = {
    provision     = true
    enabled       = true
    type          = "svc"
    resource      = "obs"
    description   = "Ansible service user for OBS access"
    custom_policy = true
    policy = {
      role_1 = {
        Effect   = "Allow"
        Action   = ["obs:bucket:ListAllMyBuckets"]
      }
      role_2 = {
        Effect   = "Allow"
        Action   = ["obs:bucket:ListBucket"]
        Resource = ["OBS:*:*:bucket:bucket-prd-shared-cloud-01"]
      }
    }
  }
}

# EVS Volumes
evs_volume = {
  extra-data-01 = {
    provision      = true
    size           = 100
    volume_type    = "SSD"
    tags = {
      function = "data"
      created  = "13-03-2026"
    }
  }
}

# Azure Key Vault Secrets
key_vault_secret = {
  huawei-ecs-machine-password = {
    provision = true
    password = {
      length  = 24
      special = true
    }
  }
}
