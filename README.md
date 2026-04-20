# gift-wrap-upsell-plugin-upsell-plugin

# Gift Wrap Upsell Plugin (Phase A)

A standalone WordPress plugin that manages **gift wrap options** using a custom post type, taxonomy, REST API, AJAX, WP-CLI, and cron — built following WordPress best practices.

---

## Features

- Custom Post Type: `gift_wrap_option`
- Custom Taxonomy: `gift_wrap_season`
- Custom Meta Fields:
  - `surcharge` (number)
  - `is_active` (boolean)
  - `expiry_date` (string/date)
- REST API endpoint to fetch active wraps
- AJAX endpoint for preview
- WP-CLI command for CSV import
- Daily cron job to disable expired wraps
- Full i18n support (`gift-wrap-upsell-plugin`)
- Security best practices (nonces, sanitization, escaping)

---

## Installation

1. Clone the repository into your plugins directory:

```bash
cd wp-content/plugins
git clone https://github.com/digvijay-wisetr/gift-wrap-upsell-plugin.git