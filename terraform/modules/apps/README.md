## Requirements

No requirements.

## Providers

No providers.

## Modules

| Name | Source | Version |
|------|--------|---------|
| <a name="module_deployement"></a> [deployement](#module\_deployement) | git@github.com:PrestaShopCorp/terraform-deployments.git | v2.3.0 |
| <a name="module_secrets"></a> [secrets](#module\_secrets) | git@github.com:PrestaShopCorp/terraform-secrets.git | v2.3.0 |

## Resources

No resources.

## Inputs

| Name | Description | Type | Default | Required |
|------|-------------|------|---------|:--------:|
| <a name="input_app_version"></a> [app\_version](#input\_app\_version) | Application version name | `string` | `"latest"` | no |
| <a name="input_image"></a> [image](#input\_image) | Image registry name | `string` | `""` | no |
| <a name="input_labels"></a> [labels](#input\_labels) | Ressources labels | `map(string)` | `{}` | no |
| <a name="input_project"></a> [project](#input\_project) | GCP project | `string` | n/a | yes |
| <a name="input_service_account_email"></a> [service\_account\_email](#input\_service\_account\_email) | Google ESP service account email address | `string` | n/a | yes |
| <a name="input_service_account_key"></a> [service\_account\_key](#input\_service\_account\_key) | Service account key | `string` | n/a | yes |

## Outputs

No outputs.
