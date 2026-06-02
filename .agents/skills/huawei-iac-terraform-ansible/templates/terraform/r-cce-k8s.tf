######################
## CCE K8S CREATION ##
######################

# https://gitlab.com/neogrid1/cloud-services/huawei/terraform-modules/cce

module "huawei_cce_k8s" {
  source = "git@gitlab.com:neogrid1/cloud-services/huawei/terraform-modules/cce//module?ref=v2.0.2"

  for_each = {
    for key, value in var.cce_k8s : key => value
    if value.provision == true
  }

  # Global
  environment = var.environment
  region      = var.region

  # CCE
  name      = each.key
  flavor_id = each.value.flavor_id
  tags      = merge(var.product_tags, var.global_tags, { "environment" = var.environment })

  # CCE Nodes
  cce_nodes = try(each.value.node, {})

  # CCE Node Pools
  cce_node_pools = try(each.value.node_pools, {})

  # CCE Node Attachs
  cce_node_attachs = try(each.value.node_attach, {})

  # CCE Namespaces
  cce_namespaces = try(each.value.namespaces, {})

  # CCE PVCs
  cce_pvcs = try(each.value.pvcs, {})

  # CCE Addons
  cce_addons = try(each.value.addons, {})

  # Networking
  container_network_type = each.value.container_network_type
  vpc_id                 = data.huaweicloud_vpc.vpc_main_01.id
  subnet_id              = try(each.value.subnet_id, data.huaweicloud_vpc_subnet.snet_cce_01[0].id)
  container_network_cidr = try(each.value.container_network_cidr, null)
  service_network_cidr   = try(each.value.service_network_cidr, null)

  # Security Group
  security_group_id = try(each.value.security_group_id, data.huaweicloud_networking_secgroup.secgroup_cce_01.id)

  # Vault
  password = data.azurerm_key_vault_secret.ecs.value

  # KMS
  kms_default_ecs = module.huawei_kms_key["ecs"].name

}
