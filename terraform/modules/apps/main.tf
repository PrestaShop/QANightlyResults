module "secrets" {
  source    = "git@github.com:PrestaShopCorp/terraform-secrets.git?ref=v2.3.0"
  namespace = "qanightlyresults"
  labels    = var.labels
  prefix    = "qanightlyresults-test"
  secrets = [
    {
      kube_name = "env-variable"
      kube_type = "secret"
      kube_data = {
        "QANB_API_DOMAIN" = "integration-nightly.prestashop.net"
        "QANB_DB_USERNAME" = "qanightlyresults_user"
        "QANB_DB_PASSWORD" = "jjYhuQ9aUmxyX9c"
        "QANB_DB_NAME" = "qanightlyresults"
        "QANB_TOKEN" = "NR10UBC7UDPRnGocK2XPXWepkvyLVWM9WagOeqNMlvUuucsQNhFZ5bzXevYzo369MsYpiEWIeNWOv6dq2U7tqG7HBU"
        "QANB_GA" = "UA-2753771-46"
        "QANB_ENV" = "staging"
      }
    },
    {
      kube_name = "service-account"
      kube_type = "secret"
      kube_data = {
        "credentials.json" = var.service_account_key
      }
    }
  ]
}

module "deployement" {
  source     = "git@github.com:PrestaShopCorp/terraform-deployments.git?ref=v2.3.0"
  namespace  = "qanightlyresults"
  labels     = var.labels
  gcp_secret = module.secrets.secrets_names_maps["service-account"]
  deploys = [
    {
      name     = "api-qanightlyresults-test"
      strategy = "RollingUpdate"
      endpoint = {
        enable   = true
        external = false
        port     = 8081
      }
      secrets = [
        module.secrets.secrets_names_maps["env-variable"]
      ]
      esp = {
        enable = true
        port   = 3000
      }
      containers = [{
        name           = "api-qanightlyresults-test-pod"
        image          = var.image
        version        = var.app_version
        cpu_request    = "100m"
        cpu_limits     = "150m"
        memory_request = "200Mi"
        memory_limits  = "300Mi"
        port           = 3000
      }]
    },
  ]
}
