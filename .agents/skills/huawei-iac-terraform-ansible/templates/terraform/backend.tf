###################
## AZURE BACKEND ##
###################

terraform {
  backend "azurerm" {}
}

# Backend configurado via CLI no init:
# terraform init -input=false -reconfigure -upgrade \
#   -backend-config="storage_account_name=$AZURE_STORAGE_NAME" \
#   -backend-config="container_name=$AZURE_STORAGE_CONTAINER" \
#   -backend-config="key=${CLOUD}/${ACCOUNT}/iac-shared-${PRODUCT}-tfstate"
