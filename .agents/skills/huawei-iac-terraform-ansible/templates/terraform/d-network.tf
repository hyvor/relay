#########################
## NETWORK DATA IMPORT ##
#########################

# data vpc main #
data "huaweicloud_vpc" "vpc_main_01" {
  name = "vpc-${var.environment}-${replace(var.region, "-", "")}-${var.product}-01"
}

# data subnet cce k8s #
data "huaweicloud_vpc_subnet" "snet_cce_01" {
  count = var.data_snet_cce_01_provision == true ? 1 : 0
  name  = "snet-${var.environment}-${replace(var.region, "-", "")}-devops-01"
}

# data subnet database #
data "huaweicloud_vpc_subnet" "snet_database_01" {
  count = var.data_snet_database_01_provision == true ? 1 : 0
  name  = "snet-${var.environment}-${replace(var.region, "-", "")}-database-01"
  vpc_id = data.huaweicloud_vpc.vpc_main_01.id
}

# data security group cce #
data "huaweicloud_networking_secgroup" "secgroup_cce_01" {
  name = var.secgroup_cce_name
}

# data security group database #
data "huaweicloud_networking_secgroup" "secgroup_database_01" {
  name = var.secgroup_database_name
}
