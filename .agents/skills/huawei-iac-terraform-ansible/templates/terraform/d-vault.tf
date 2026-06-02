##########################
## AZURE KEY VAULT DATA ##
##########################

data "azurerm_key_vault" "vault_main" {
  name                = var.key_vault_name
  resource_group_name = var.key_vault_rg
}

data "azurerm_key_vault_secret" "ecs" {
  name         = var.key_vault_secret_ecs
  key_vault_id = data.azurerm_key_vault.vault_main.id
}
