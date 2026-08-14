# WooCommerce Delivery Scheduling & Capacity

A WooCommerce extension for stores that need controlled local-delivery dates, time windows, service areas, and daily order limits.

The project treats checkout delivery selection as an operational constraint, not only as a calendar field. Every submitted value is validated again on the server and persisted through WooCommerce CRUD APIs.

## Capabilities

- Delivery date with closed weekdays and blackout dates.
- Merchant-defined time windows and accepted service areas.
- Configurable maximum orders per delivery date.
- Server-side validation for classic and block checkout.
- High-Performance Order Storage (HPOS) compatibility.
- WooCommerce Additional Checkout Fields API integration for Checkout Blocks.
- Delivery data in order administration and transactional emails.
- Sanitized, capability-protected settings under **WooCommerce → Delivery capacity**.

## Architecture

```text
Merchant settings
      │
      ▼
AvailabilityRules ──────► Checkout validation
      │                         │
      │                         ├── Classic checkout
      │                         └── Checkout Blocks
      ▼
OrderCapacity ──────────► WC_Order_Query / HPOS
                                │
                                ▼
                         WC_Order metadata
                                │
                         Admin + email output
```

The domain rules are isolated from WordPress so dates, time windows, and locations can be unit tested without booting WooCommerce. Infrastructure classes translate those decisions into WooCommerce hooks and CRUD operations.

## Requirements

- WordPress 6.5+
- WooCommerce 8.9+
- PHP 8.2+

## Installation

1. Download the repository as a ZIP.
2. Upload it from **Plugins → Add New → Upload Plugin**.
3. Activate the plugin.
4. Configure delivery rules under **WooCommerce → Delivery capacity**.

## Development

```bash
composer install
composer test
composer lint
```

## Capacity semantics

Orders in `pending`, `on-hold`, processing, or paid states consume capacity. Cancelled, failed, refunded, and trashed orders do not. Capacity is checked on each server-side checkout validation request.

For very high-concurrency stores, a dedicated reservation table with expiring pre-checkout holds would be the next architectural step. This version deliberately documents that boundary rather than claiming distributed locking it does not provide.

## Security and data handling

- Checkout values are never trusted from the browser.
- Dates must be valid ISO dates and cannot be in the past, blocked, or on a closed weekday.
- Times and locations must match merchant-controlled allowlists.
- Settings use a typed sanitization callback.
- Order access uses WooCommerce CRUD APIs and is HPOS-safe.

## License

GPL-2.0-or-later.
