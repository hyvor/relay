#####################
## EVS VOLUME       ##
#####################

# https://gitlab.com/neogrid1/cloud-services/huawei/terraform-modules/evs

module "huawei_evs_volume" {
  source = "git@gitlab.com:neogrid1/cloud-services/huawei/terraform-modules/evs//module?ref=v0.0.3"

  for_each = {
    for key, value in var.evs_volume : key => value
    if value.provision == true
  }

  # EVS
  name               = each.key
  size               = each.value.size
  volume_type        = try(each.value.volume_type, "GPSSD")
  availability_zone  = try(each.value.availability_zone, "la-south-2a")

  # KMS
  kms_key_id = try(each.value.kms_key_id, null)

  # Tags
  tags = merge(var.product_tags, var.global_tags, { "environment" = var.environment }, try(each.value.tags, {}))
}
