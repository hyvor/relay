##############################
## ECS MACHINE APP CREATION ##
##############################

# https://gitlab.com/neogrid1/cloud-services/huawei/terraform-modules/ecs

module "huawei_ecs_machine_app" {
  source = "git@gitlab.com:neogrid1/cloud-services/huawei/terraform-modules/ecs//module?ref=v1.0.8"

  for_each = {
    for key, value in var.ecs_machine_app : key => value
    if value.provision == true
  }

  # Global
  region      = var.region
  environment = var.environment
  product     = var.product

  # ECS
  name              = each.key
  flavor_id         = each.value.flavor_id
  image_name        = try(each.value.image_name, "Rocky Linux 9.0 64bit")
  image_visibility  = try(each.value.image_visibility, "public")
  availability_zone = try(each.value.az, "la-south-2a")
  image_id          = try(each.value.image_id, null)
  admin_pass        = data.azurerm_key_vault_secret.ecs.value
  tags              = merge(var.product_tags, var.global_tags, { "environment" = var.environment }, each.value.tags)

  # EVS
  system_disk_type       = try(each.value.system_disk_type, "GPSSD")
  system_disk_size       = try(each.value.system_disk_size, 40)
  system_disk_iops       = try(each.value.system_disk_iops, null)
  system_disk_throughput = try(each.value.system_disk_throughput, null)
  volume_disks           = try(each.value.volume_disks, {})

  # KMS
  system_disk_kms = try(each.value.system_disk_kms, false)
  kms_default_ecs = module.huawei_kms_key["ecs"].name

  # Networking
  nic_source_dest_check = try(each.value.source_dest_check, true)
  security_name         = try(each.value.security_name, ["sg-devops-01"])
  subnet_name           = try(each.value.subnet_name, "snet-default-01")

}
