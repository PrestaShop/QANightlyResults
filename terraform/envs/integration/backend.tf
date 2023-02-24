terraform {
  backend "gcs" {
    prefix = "app-api-qanightlyresults"
    bucket = "terraform-testing-migration"
  }
}
