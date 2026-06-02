#!/bin/bash
# scripts/load-env.sh
# Environment loader for Huawei Cloud Terraform
# Loads secrets from Azure Key Vault into env vars
#
# Usage:
#   source scripts/load-env.sh prd          # Load PRD environment
#   source scripts/load-env.sh prd plan     # Load PRD and run plan
#   source scripts/load-env.sh cleanup      # Clean all env vars

set -euo pipefail

AMBIENTE="${1:-}"
ACAO="${2:-}"

# ============================================================================
# VALIDATION
# ============================================================================

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    echo "Error: use 'source $0 [prd|pre|dev] [plan|apply]' or 'source $0 cleanup'"
    exit 1
fi

if [ "$AMBIENTE" = "cleanup" ]; then
    echo "Cleaning up all Terraform environment variables..."
    unset PRODUCT ACCOUNT CLOUD AMBIENTE WORKSPACE
    unset ARM_ACCESS_KEY ARM_TENANT_ID ARM_CLIENT_ID ARM_CLIENT_SECRET
    unset AZURE_STORAGE_CONTAINER AZURE_STORAGE_NAME
    unset TF_VAR_subscription_id TF_VAR_hw_access_key TF_VAR_hw_secret_key
    echo "Done. All variables cleaned."
    return 0 2>/dev/null || exit 0
fi

if [ -z "$AMBIENTE" ]; then
    echo "Usage: source $0 [prd|pre|dev] [plan|apply] or source $0 cleanup"
    return 1 2>/dev/null || exit 1
fi

case "$AMBIENTE" in
    prd|pre|dev) ;;
    *)
        echo "Error: Environment must be 'prd', 'pre', or 'dev'"
        return 1 2>/dev/null || exit 1
        ;;
esac

# Check required tools
for tool in az terraform; do
    if ! command -v "$tool" &> /dev/null; then
        echo "Error: $tool is not installed"
        return 1 2>/dev/null || exit 1
    fi
done

# Check Azure login
if ! az account show &>/dev/null; then
    echo "Error: Not logged into Azure. Run 'az login' first."
    return 1 2>/dev/null || exit 1
fi

# ============================================================================
# LOAD SECRETS FROM AZURE KEY VAULT
# ============================================================================

load_secret() {
    local vault="$1"
    local secret_name="$2"
    local var_name="$3"
    local value
    value=$(az keyvault secret show --vault-name "$vault" --name "$secret_name" --query value -o tsv 2>/dev/null)
    if [ -n "$value" ]; then
        export "$var_name"="$value"
        echo "  ✓ $var_name"
    else
        echo "  ✗ Failed to load $secret_name from $vault"
        return 1
    fi
}

echo "Loading secrets from Azure Key Vault..."

# Global secrets (from PRD vault)
GLOBAL_VAULT="kv-prd-eastus2-cloud"
load_secret "$GLOBAL_VAULT" "AZURE-STORAGE-NAME" "AZURE_STORAGE_NAME"
load_secret "$GLOBAL_VAULT" "AZURE-STORAGE-CONTAINER" "AZURE_STORAGE_CONTAINER"
load_secret "$GLOBAL_VAULT" "ARM-ACCESS-KEY" "ARM_ACCESS_KEY"
load_secret "$GLOBAL_VAULT" "ARM-TENANT-ID" "ARM_TENANT_ID"

# Per-environment secrets
ENV_VAULT="kv-${AMBIENTE}-eastus2-cloud"
load_secret "$ENV_VAULT" "arm-client-id-${AMBIENTE}" "ARM_CLIENT_ID"
load_secret "$ENV_VAULT" "arm-client-secret-${AMBIENTE}" "ARM_CLIENT_SECRET"
load_secret "$ENV_VAULT" "subscription-id-${AMBIENTE}" "TF_VAR_subscription_id"

# Huawei Cloud credentials
ACCOUNT="${ACCOUNT:-oem}"
load_secret "$ENV_VAULT" "${ACCOUNT}-iam-usr-${AMBIENTE}-terraform-ak" "TF_VAR_hw_access_key"
load_secret "$ENV_VAULT" "${ACCOUNT}-iam-usr-${AMBIENTE}-terraform-sk" "TF_VAR_hw_secret_key"

# Project configuration
export PRODUCT="${PRODUCT:-cloud-services}"
export CLOUD="${CLOUD:-huawei}"
export ACCOUNT="${ACCOUNT:-oem}"
export WORKSPACE="${AMBIENTE}"

echo ""
echo "========================================="
echo "Environment: $AMBIENTE"
echo "Cloud:       $CLOUD"
echo "Product:     $PRODUCT"
echo "Account:     $ACCOUNT"
echo "Workspace:   $WORKSPACE"
echo "========================================="
echo ""

# ============================================================================
# TERRAFORM COMMANDS
# ============================================================================

if [ "$ACAO" = "plan" ]; then
    echo "Running terraform init..."
    terraform init -input=false -reconfigure -upgrade \
        -backend-config="storage_account_name=$AZURE_STORAGE_NAME" \
        -backend-config="container_name=$AZURE_STORAGE_CONTAINER" \
        -backend-config="key=${CLOUD}/${ACCOUNT}/iac-shared-${PRODUCT}-tfstate"

    terraform workspace new "$WORKSPACE" 2>/dev/null || terraform workspace select "$WORKSPACE"

    echo "Running terraform plan..."
    terraform plan -input=false -refresh -out plan.out -var-file="e-${WORKSPACE}.tfvars"

elif [ "$ACAO" = "apply" ]; then
    echo "Running terraform apply..."
    terraform apply -input=false plan.out
fi
