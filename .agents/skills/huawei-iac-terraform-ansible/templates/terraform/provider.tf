########################
## TERRAFORM PROVIDER ##
########################

# https://registry.terraform.io/providers/huaweicloud/huaweicloud/latest/docs

provider "huaweicloud" {
  region     = var.region
  access_key = var.hw_access_key
  secret_key = var.hw_secret_key
}

# https://registry.terraform.io/providers/hashicorp/azurerm/latest/docs

provider "azurerm" {
  features {}
  subscription_id = var.subscription_id
}
