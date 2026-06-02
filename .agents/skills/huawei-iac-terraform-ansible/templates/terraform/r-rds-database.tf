######################
## RDS DATABASE      ##
######################

# https://gitlab.com/neogrid1/cloud-services/huawei/terraform-modules/rds

module "huawei_rds_database" {
  source = "git@gitlab.com:neogrid1/cloud-services/huawei/terraform-modules/rds//module?ref=v1.0.2"

  for_each = {
    for key, value in var.rds_database : key => value
    if value.provision == true
  }

  # Global
  region      = var.region
  environment = var.environment
  product     = var.product

  # RDS
  name               = each.key
  flavor_id          = each.value.flavor_id
  availability_zones = try(each.value.availability_zones, ["la-south-2a"])
  vpc_id             = data.huaweicloud_vpc.vpc_main_01.id
  subnet_id          = each.value.subnet_id
  security_group_id  = data.huaweicloud_networking_secgroup.secgroup_database_01.id

  # Volume
  volume_type = try(each.value.volume_type, "ULTRAHIGH")
  volume_size = try(each.value.volume_size, 40)

  # Database
  db_type    = try(each.value.db_type, "PostgreSQL")
  db_version = try(each.value.db_version, "14")
  password   = data.azurerm_key_vault_secret.ecs.value

  # Backup
  backup_keep_days = try(each.value.backup_keep_days, 7)
  backup_start_time = try(each.value.backup_start_time, "02:00-03:00")

  # Tags
  tags = merge(var.product_tags, var.global_tags, { "environment" = var.environment }, try(each.value.tags, {}))
}
