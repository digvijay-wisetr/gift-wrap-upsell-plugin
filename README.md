# Gift Wrap Upsell

A WordPress + WooCommerce plugin that lets a store offer gift wrap as a checkout add-on. Store owners configure available wrap options (name, image, surcharge). Customers pick one at checkout. The surcharge is added to the cart. A daily picklist of gift-wrapped orders is emailed to the fulfillment team.

Built across three phases as part of a structured onboarding assignment.

---

## Table of contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Phase A — WordPress core features](#phase-a--wordpress-core-features)
- [Phase B — WooCommerce integration](#phase-b--woocommerce-integration)
- [Phase C — Production hardening](#phase-c--production-hardening)
- [Testing each phase](#testing-each-phase)
- [Failure modes and how we handle them](#failure-modes-and-how-we-handle-them)
- [Plugin structure](#plugin-structure)

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.0+ |
| WordPress | 6.4+ |
| WooCommerce | 8.0+ (Phase B and C) |
| Action Scheduler | 3.6+ (bundled with WooCommerce, Phase C) |
| WP-CLI | 2.8+ (for import command and test scripts) |

---

## Installation

1. Clone into your plugins directory:

```bash
cd wp-content/plugins
git clone https://github.com/digvijay-wisetr/gift-wrap-upsell-plugin.git
```

2. Activate the plugin from **Plugins → Installed Plugins**, or via CLI:

```bash
wp plugin activate gift-wrap-upsell-plugin
```

3. Activation automatically:
   - Registers the `gift_wrap_option` CPT and `gift_wrap_season` taxonomy
   - Flushes rewrite rules (once, not on every request)
   - Schedules the daily expiry check and picklist job via Action Scheduler

---

## Phase A — WordPress core features

### What it does

- Registers a `gift_wrap_option` custom post type with `show_in_rest: true`
- Registers a `gift_wrap_season` taxonomy (Christmas, Valentine's, Year-round, etc.)
- Stores three custom meta fields per wrap: `surcharge`, `is_active`, `expiry_date`
- Admin settings page with an Add New form (nonce + capability check + sanitization + escaping throughout)
- REST endpoint `GET /wp-json/gift-wrap-upsell-plugin/v1/options` — returns all active wraps, supports `?season=` and `?per_page=` filters
- AJAX endpoint `gift_wrap_preview` — returns an HTML preview card for a given wrap ID
- WP-CLI command `wp gift-wrap-upsell-plugin import --csv=wraps.csv` — bulk imports wraps from CSV
- Daily WP-Cron job that auto-disables seasonal wraps past their expiry date
- Full i18n with the `gift-wrap-upsell-plugin` text domain

### Setup steps

No extra configuration needed beyond activation. The admin UI appears under **Gift Wraps → Manage Wraps** in the WordPress sidebar.

To generate the `.pot` file:

```bash
wp i18n make-pot . languages/gift-wrap-upsell-plugin.pot
```

### REST API

```
GET /wp-json/gift-wrap-upsell-plugin/v1/options
```

Optional query parameters:

| Parameter | Type | Default | Description |
|---|---|---|---|
| `season` | string | — | Filter by `gift_wrap_season` taxonomy slug |
| `per_page` | integer | 20 | Results per page (max 100) |
| `page` | integer | 1 | Page number |

The endpoint is gated by a site-level toggle (`gwu_api_enabled` option). To disable it:

```bash
wp option update gwu_api_enabled 0
```

### AJAX preview

Localized nonce is passed to JS via `wp_localize_script`. The endpoint checks `check_ajax_referer` before doing any work and requires the `edit_posts` capability.

### WP-CLI import

```bash
# Dry run — shows what would be imported without writing anything
wp gift-wrap-upsell-plugin import --csv=wraps.csv --dry-run

# Real import
wp gift-wrap-upsell-plugin import --csv=wraps.csv
```

Expected CSV format:

```
title,surcharge,is_active,expiry_date
Christmas Gold,4.99,1,2025-12-26
Valentine Hearts,3.50,1,2025-02-15
Year-Round Classic,2.00,1,
```

The importer skips duplicate rows (matched by title + surcharge + expiry), validates dates, shows a progress bar, and reports imported/skipped/error counts.

---

## Phase B — WooCommerce integration

### What it adds

- Gift wrap selector field at checkout (via `woocommerce_checkout_fields` filter)
- Surcharge added as a cart fee (via `woocommerce_cart_calculate_fees`)
- Selected wrap saved to order meta using HPOS-compatible `$order->update_meta_data()` + `$order->save()`
- Wrap displayed on the order-received thank-you page
- Wrap shown in the customer order email via a template override
- Admin order metabox showing the selected wrap and surcharge
- Idempotent `woocommerce_order_status_changed` handler (safe to fire twice for the same transition)

### WooCommerce setup

1. Install and activate WooCommerce.
2. Create at least one `gift_wrap_option` post (mark it Active, set a surcharge).
3. Go to checkout — the gift wrap selector appears above the order summary.

### Checkout compatibility

The gift wrap field is added via the `woocommerce_checkout_fields` filter, which works on the **shortcode checkout** (`[woocommerce_checkout]`).

Block checkout (introduced in WooCommerce 8.3) uses a different extension API. The current implementation supports shortcode checkout only. Block checkout support is planned for a future phase.

### Email template override

The template lives at `templates/emails/gift-wrap-notice.php`. To override it from your theme, copy it to:

```
yourtheme/woocommerce/emails/gift-wrap-notice.php
```

WooCommerce's template loader (`wc_get_template()`) will pick up the theme version automatically.

### HPOS compatibility

All order reads use `wc_get_orders()`. All order writes use `$order->update_meta_data()` + `$order->save()`. There are no raw `update_post_meta( $post_id, '_gwu_*', ... )` calls on order data.

---

## Phase C — Production hardening

### What it adds

- **Daily picklist job** via Action Scheduler — generates a CSV of yesterday's gift-wrapped orders and emails it to the store admin
- **Batch processing with resume-from-checkpoint** — if the job is killed mid-run, the next run resumes from the last processed order ID, not from the start
- **Webhook endpoint** `POST /wp-json/gift-wrap-upsell-plugin/v1/webhook/shipped` — marks a wrap as shipped with full event-ID deduplication
- **Multi-currency surcharge display** — tested with CURCY (WooMultiCurrency); surcharge displays in the customer's selected currency
- **Structured logging** throughout via `wc_get_logger()` with source channel `gift-wrap`
- **WP_Filesystem** for all file writes — no raw `file_put_contents`

### Viewing scheduled actions

Go to **WooCommerce → Status → Scheduled Actions** and filter by group `gift-wrap`. You should see:

- `gwu_daily_expiry_check` — daily, group: gift-wrap
- `gwu_generate_picklist` — daily at 06:00, group: gift-wrap

To fire the picklist job manually:

```bash
wp action-scheduler run --hook=gwu_generate_picklist
```

The CSV is written to `wp-content/uploads/gift-wrap-picklist-YYYY-MM-DD.csv`.

### Webhook endpoint

```
POST /wp-json/gift-wrap-upsell-plugin/v1/webhook/shipped
```

Required capability: `manage_woocommerce`.

Request body (JSON):

```json
{
  "event_id": "vendor-event-abc-123",
  "order_id": 42,
  "wrap_id": 7,
  "tracking": "TRACK-XYZ-001"
}
```

Response codes:

| Code | Meaning |
|---|---|
| 200 | Wrap marked as shipped |
| 409 | Duplicate event ID — already processed, no-op |
| 404 | Order not found |

### Multi-currency

Tested with the **CURCY (WooMultiCurrency)** plugin. The wrap surcharge converts automatically because WooCommerce fees pass through `wc_price()`, which CURCY filters.

For historical orders, the exchange rate at order time is stored in `_gwu_wrap_exchange_rate` and `_gwu_wrap_currency` order meta. Displaying a historical order always uses the stored rate, not today's rate.

YITH Multi Currency uses a different filter (`ywmc_convert_price`). Integration with YITH is documented but not yet tested end-to-end.

### Viewing logs

**WooCommerce → Status → Logs** — select source `gift-wrap` from the dropdown. All plugin activity (job runs, skipped duplicates, webhook events, errors) is written here.

---

## Testing each phase

### Phase A tests

**Cron:**
```bash
# Confirm the job is scheduled
wp action-scheduler list --group=gift-wrap

# Fire it manually and check output
wp action-scheduler run --hook=gwu_daily_expiry_check
```

**REST endpoint:**
```bash
curl http://yoursite.local/wp-json/gift-wrap-upsell-plugin/v1/options
curl http://yoursite.local/wp-json/gift-wrap-upsell-plugin/v1/options?season=christmas
```

**CLI import:**
```bash
wp gift-wrap-upsell-plugin import --csv=sample-wraps.csv --dry-run
wp gift-wrap-upsell-plugin import --csv=sample-wraps.csv
```

**Debug check:**
Enable `WP_DEBUG` and `WP_DEBUG_LOG` in `wp-config.php`. Load the admin page and checkout. There should be zero PHP warnings or notices in the log.

### Phase B tests

**Checkout flow:**
1. Add a product to cart.
2. Go to checkout — confirm the gift wrap selector appears.
3. Select a wrap — confirm the surcharge appears in the order summary.
4. Complete the order.
5. Check the thank-you page — confirm the wrap is shown.
6. Check the order email — confirm the wrap notice appears.
7. Open the order in WP admin — confirm the metabox shows the wrap.

**HPOS check:**
```bash
# Confirm no raw post_meta calls on orders
grep -r "update_post_meta.*_gwu_" includes/
# Should return nothing
```

**Status transition idempotency:**
Manually move an order from Processing → On Hold → Processing twice. The `_gwu_wrap_processing_noted` meta should be set exactly once.

### Phase C tests

**Crash and resume:**
```bash
wp eval-file tests/test-crash-resume.php
```

This script creates test orders, sets the checkpoint to the midpoint, runs the job, and verifies the CSV contains only the second half of orders.

**Webhook deduplication:**
```bash
wp eval-file tests/test-webhook-dedup.php
```

This script sends the same webhook payload twice. The first call returns 200, the second returns 409. The order's `_gwu_wrap_shipped` meta is set exactly once.

**Memory test:**
```bash
wp eval 'gwu_run_picklist_job();' --debug
```

Watch RSS memory in the debug output. Memory should stay flat between batches thanks to `wp_suspend_cache_addition()` and `wp_cache_flush_runtime()`.

---

## Failure modes and how we handle them

### Batch job killed mid-run

**Problem:** A PHP timeout or `kill -9` kills the process after processing 500 of 1,000 orders. A naive implementation restarts from scratch, double-processing the first 500.

**Solution:** After each batch of 50 orders, the last-processed order ID is written to `wp_options` as a checkpoint. On the next run, the query uses `WHERE ID > $last_id`, so only unprocessed orders are touched. The checkpoint is deleted at the end of a successful run.

### Two Action Scheduler workers running simultaneously

**Problem:** Under load, AS can dispatch the same action to two workers at once. Both read the checkpoint, both process the same orders, resulting in duplicate CSV rows.

**Solution:** A lock is stored in `wp_options` with a timestamp. Any worker that finds a lock newer than one hour exits immediately. The lock is deleted at the end of the job. The one-hour TTL ensures a crashed worker doesn't block the job forever.

### Duplicate webhook events

**Problem:** A vendor retries a webhook delivery. The same `event_id` arrives five times. Without deduplication, the wrap is marked shipped five times and five log entries are written.

**Solution:** On the first successful processing of an event, the `event_id` is stored as a transient with a 24-hour TTL. Subsequent requests with the same ID return HTTP 409 immediately without touching the order.

### Cart surcharge on a wrap that has since been deactivated

**Problem:** Customer adds a wrap to their session, the store owner deactivates that wrap before the customer checks out. The fee would be added for a wrap that no longer exists.

**Solution:** `gwu_apply_wrap_fee()` re-validates the wrap post against `is_active` meta on every cart calculation. If the wrap is gone or inactive, the session is cleared and no fee is added.

### Object cache memory growth during batch

**Problem:** Looping over 10,000 orders without cache management causes the object cache to grow unboundedly, eventually triggering PHP's memory limit.

**Solution:** `wp_suspend_cache_addition(true)` is called before the batch loop. `wp_cache_flush_runtime()` is called after each batch of 50 to release accumulated cache entries. `wp_suspend_cache_addition(false)` restores normal behaviour after the loop.

---

## Plugin structure

```
gift-wrap-upsell-plugin/
├── gift-wrap-upsell-plugin.php   # Main plugin file, activation/deactivation hooks
├── includes/
│   ├── post-types.php            # CPT and taxonomy registration
│   ├── meta.php                  # register_post_meta() for surcharge, is_active, expiry_date
│   ├── admin.php                 # Admin pages, add-new form, form submission handler
│   ├── class-gwu-wraps-table.php # WP_List_Table subclass for the wrap list
│   ├── enqueue.php               # Admin and frontend script/style registration
│   ├── ajax-handler.php          # AJAX preview endpoint
│   ├── rest-api.php              # REST routes: /options and /webhook/shipped
│   ├── gwu-cli.php               # WP-CLI import command
│   ├── cron.php                  # Daily expiry check (Action Scheduler in Phase C)
│   ├── checkout.php              # WooCommerce checkout integration (Phase B+)
│   ├── helpers.php               # gwu_sanitize_float(), gwu_can_edit()
│   └── picklist.php              # Daily picklist batch job (Phase C)
├── templates/
│   └── emails/
│       └── gift-wrap-notice.php  # Email template (overrideable from theme)
├── assets/
│   ├── js/
│   │   ├── admin.js              # Media uploader + AJAX preview
│   │   └── frontend.js           # Checkout wrap selector UI
│   └── css/
│       └── frontend.css          # Checkout styles
├── languages/
│   └── gift-wrap-upsell-plugin.pot
├── tests/
│   ├── test-crash-resume.php     # Phase C: proves checkpoint resume works
│   └── test-webhook-dedup.php    # Phase C: proves webhook deduplication works
└── README.md
```