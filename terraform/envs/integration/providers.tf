terraform {
  required_version = ">=1.3.7"
  required_providers {
    google      = "~>4.15.0"
    google-beta = "~>4.15.0"
    kubernetes  = "~>2.10.0"
  }
}

provider "google" {
  project = var.project
  region  = var.region
  zone    = var.default_zone
}

provider "google-beta" {
  project = var.project
  region  = var.region
  zone    = var.default_zone
}

provider "kubernetes" {
  config_path    = "~/.kube/config"
  config_context = "gke_prestashop-cloud-integration_europe-west4-a_test-cluster-1"
}
