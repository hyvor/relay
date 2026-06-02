#####################
## OBS BUCKET       ##
#####################

# https://gitlab.com/neogrid1/cloud-services/huawei/terraform-modules/obs

module "huawei_obs_bucket" {
  source = "git@gitlab.com:neogrid1/cloud-services/huawei/terraform-modules/obs//module?ref=v1.0.0"

  for_each = {
    for key, value in var.obs_bucket : key => value
    if value.provision == true
  }

  # OBS
  name         = "bucket-${var.environment}-${var.product}-${each.key}"
  user_domain  = try(each.value.user_domain, [])
  website      = try(each.value.website, {})
  encryption   = try(each.value.encryption, {})
  lifecycle    = try(each.value.lifecycle, {})

  # Tags
  tags = merge(var.product_tags, var.global_tags, { "environment" = var.environment }, try(each.value.tags, {}))
}
