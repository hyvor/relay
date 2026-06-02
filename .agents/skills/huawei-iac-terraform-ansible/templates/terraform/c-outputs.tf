##############
## OUTPUTS   ##
##############

output "ecs_instances" {
  description = "Mapa de instâncias ECS criadas com IPs e metadados."
  value = {
    for name, instance in module.huawei_ecs_machine_app : name => {
      id         = instance.id
      private_ip = instance.private_ip
      public_ip  = instance.public_ip
      flavor     = instance.flavor_id
    }
  }
}

output "cce_cluster_info" {
  description = "Informações dos clusters CCE criados."
  value = {
    for name, cluster in module.huawei_cce_k8s : name => {
      id       = cluster.id
      endpoint = cluster.api_endpoint
      status   = cluster.status
    }
  }
}

output "vpc_id" {
  description = "ID da VPC principal."
  value       = data.huaweicloud_vpc.vpc_main_01.id
}

output "rds_endpoints" {
  description = "Endpoints dos bancos de dados RDS."
  value = {
    for name, db in module.huawei_rds_database : name => {
      private_ip = db.private_ips
      port       = db.port
    }
  }
}

output "dcs_endpoints" {
  description = "Endpoints das instâncias DCS Redis."
  value = {
    for name, redis in module.huawei_dcs_redis : name => {
      ip   = redis.ip
      port = redis.port
    }
  }
}

output "ansible_inventory" {
  description = "Inventário Ansible gerado a partir das instâncias ECS."
  value = {
    ecs_app = {
      for name, instance in module.huawei_ecs_machine_app : name => {
        ansible_host = try(instance.private_ip, instance.public_ip)
      }
    }
  }
}
