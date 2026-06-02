#####################
## DCS REDIS        ##
#####################

# https://gitlab.com/neogrid1/cloud-services/huawei/terraform-modules/dcs

module "huawei_dcs_redis" {
  source = "git@gitlab.com:neogrid1/cloud-services/huawei/terraform-modules/dcs//module?ref=v0.0.4"

  for_each = {
    for key, value in var.dcs_redis : key => value
    if value.provision == true
  }

  # Global
  region      = var.region
  environment = var.environment

  # DCS
  name               = each.key
  engine_version     = try(each.value.engine_version, "5.0")
  capacity           = each.value.capacity
  flavor             = each.value.flavor
  availability_zones = try(each.value.availability_zones, ["la-south-2a"])
  vpc_id             = data.huaweicloud_vpc.vpc_main_01.id
  subnet_id          = each.value.subnet_id
  password           = data.azurerm_key_vault_secret.ecs.value

  # Tags
  tags = merge(var.product_tags, var.global_tags, { "environment" = var.environment }, try(each.value.tags, {}))
}
