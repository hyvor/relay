#######################
## IAM IDENTITY       ##
#######################

# https://gitlab.com/neogrid1/cloud-services/huawei/terraform-modules/iam

module "huawei_iam_identity" {
  source = "git@gitlab.com:neogrid1/cloud-services/huawei/terraform-modules/iam//module?ref=v0.0.4"

  for_each = {
    for key, value in var.iam_identity : key => value
    if value.provision == true
  }

  # IAM
  name         = each.key
  enabled      = try(each.value.enabled, true)
  type         = try(each.value.type, "user")
  resource     = try(each.value.resource, null)
  description  = try(each.value.description, "")
  custom_policy = try(each.value.custom_policy, false)
  policy       = try(each.value.policy, {})

  # Tags
  tags = merge(var.product_tags, var.global_tags, { "environment" = var.environment }, try(each.value.tags, {}))
}
