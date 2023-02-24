variable "labels" {
  description = "Ressources labels"
  type        = map(string)
  default     = {}
}

variable "app_version" {
  description = "Application version name"
  type        = string
  default     = "latest"
}

variable "image" {
  description = "Image registry name"
  type        = string
  default     = ""
}

variable "project" {
  type        = string
  description = "GCP project"
}

variable "service_account_email" {
  type        = string
  description = "Google ESP service account email address"
}

variable "service_account_key" {
  type        = string
  description = "Service account key"
}
