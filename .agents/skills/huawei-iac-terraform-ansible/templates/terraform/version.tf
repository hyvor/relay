######################
## PROVIDER VERSION ##
######################

terraform {
  required_version = ">= 1.5.0"

  required_providers {

    huaweicloud = {
      source  = "huaweicloud/huaweicloud"
      version = "1.86.0"
    }

    azurerm = {
      source  = "hashicorp/azurerm"
      version = "4.30.0"
    }

    random = {
      source  = "hashicorp/random"
      version = "3.8.1"
    }

  }
}
