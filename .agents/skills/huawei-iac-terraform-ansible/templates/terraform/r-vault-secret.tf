#########################
## AZURE VAULT SECRETS ##
#########################

# https://gitlab.com/neogrid1/cloud-services/azure/terraform-modules/vault-secret

module "azure_vault_secret" {
  source = "git@gitlab.com:neogrid1/cloud-services/azure/terraform-modules/vault-secret//module?ref=v0.0.2"

  for_each = {
    for key, value in var.key_vault_secret : key => value
    if value.provision == true
  }

  # Azure
  key_vault_name = var.key_vault_name
  resource_group = var.key_vault_rg

  # Secret
  name     = each.key
  value    = try(each.value.value, null)
  password = try(each.value.password, null)

  # Tags
  tags = merge(var.product_tags, var.global_tags, { "environment" = var.environment }, try(each.value.tags, {}))
}
