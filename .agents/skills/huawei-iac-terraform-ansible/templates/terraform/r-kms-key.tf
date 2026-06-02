#####################
## KMS KEY          ##
#####################

# https://gitlab.com/neogrid1/cloud-services/huawei/terraform-modules/kms

module "huawei_kms_key" {
  source = "git@gitlab.com:neogrid1/cloud-services/huawei/terraform-modules/kms//module?ref=v0.0.1"

  for_each = {
    for key, value in var.kms_key : key => value
    if value.provision == true
  }

  # KMS
  name        = "kms-${var.environment}-${each.key}"
  key_spec    = try(each.value.key_spec, "AES_256")
  pending_days = try(each.value.pending_days, 7)
  is_enabled  = try(each.value.is_enabled, true)

  # Tags
  tags = merge(var.product_tags, var.global_tags, { "environment" = var.environment }, try(each.value.tags, {}))
}
