## Requirements

| Name | Version |
|------|---------|
| <a name="requirement_terraform"></a> [terraform](#requirement\_terraform) | >=1.3.7 |
| <a name="requirement_google"></a> [google](#requirement\_google) | ~>4.15.0 |
| <a name="requirement_google-beta"></a> [google-beta](#requirement\_google-beta) | ~>4.15.0 |
| <a name="requirement_kubernetes"></a> [kubernetes](#requirement\_kubernetes) | ~>2.10.0 |

## Providers

No providers.

## Modules

| Name | Source | Version |
|------|--------|---------|
| <a name="module_apps"></a> [apps](#module\_apps) | ../../modules/apps | n/a |

## Resources

No resources.

## Inputs

| Name | Description | Type | Default | Required |
|------|-------------|------|---------|:--------:|
| <a name="input_app_version"></a> [app\_version](#input\_app\_version) | Application tag | `string` | `"latest"` | no |
| <a name="input_default_zone"></a> [default\_zone](#input\_default\_zone) | Belgium zone will be the default zone | `string` | `"europe-west4-a"` | no |
| <a name="input_environment"></a> [environment](#input\_environment) | Project environment | `string` | `"integration"` | no |
| <a name="input_hash_id"></a> [hash\_id](#input\_hash\_id) | Github commit hash | `string` | `"latest"` | no |
| <a name="input_project"></a> [project](#input\_project) | GCP Project name | `string` | `"prestashop-cloud-integration"` | no |
| <a name="input_region"></a> [region](#input\_region) | Where the cluster will live | `string` | `"europe-west4"` | no |
| <a name="input_service_account_key"></a> [service\_account\_key](#input\_service\_account\_key) | Service account key | `any` | n/a | yes |

## Outputs

No outputs.
