locals {
  labels = {
    project     = var.project
    tribe       = "test"
    squad       = "QaNightlyresults"
    environment = var.environment
    github_hash = var.hash_id
  }
}

module "apps" {
  source                = "../../modules/apps"
  labels                = local.labels
  app_version           = var.app_version
  project               = var.project
  image                 = "gcr.io/prestashop-cloud-integration/testing-qanightly-migration/api-qanightlyresults"
  service_account_email = "builder-qanighlty@prestashop-cloud-integration.iam.gserviceaccount.com"
  service_account_key   = var.service_account_key
}
