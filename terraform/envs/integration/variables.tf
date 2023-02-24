variable "project" {
  default     = "prestashop-cloud-integration"
  description = "GCP Project name"
}
variable "region" {
  default     = "europe-west4"
  description = "Where the cluster will live"
}

variable "default_zone" {
  default     = "europe-west4-a"
  description = "Belgium zone will be the default zone"
}

variable "app_version" {
  description = "Application tag"
  default     = "latest"
}

variable "hash_id" {
  description = "Github commit hash"
  default     = "latest"
}

variable "environment" {
  description = "Project environment"
  default     = "integration"
}

variable "service_account_key" {
  description = "Service account key"
}