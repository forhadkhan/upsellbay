# Logging System Architecture

This document describes the design and implementation of the UpsellBay logging system. It details how system events, API activity, exceptions, and configuration changes are tracked for diagnostics, auditing, and support purposes.

## Overview

The logging system acts as the diagnostic brain of UpsellBay. It uses a bespoke database table instead of standard WordPress logging (like the `debug.log` file) or WooCommerce's native logger (`WC_Logger`) for several reasons:

1. **Rich Context**: Support for structured JSON columns like `request_data`, `response_data`, and `metadata` allows us to capture the exact payload shapes without mixing them into a giant string block.
2. **UI Integration**: We provide a dedicated UI (UpsellBay -> Settings -> Logs) where merchants and support teams can browse, filter, search, and export logs natively.
3. **Automated Maintenance**: A scheduled job automatically cleans up older logs, ensuring the database footprint doesn't grow unbounded.

## Database Schema

The system uses a custom table `{$wpdb->prefix}upsellbay_logs`. It is created in `app/Core/Installer.php`.

### Columns
- `id` (bigint): Primary Key.
- `created_at` (datetime): UTC timestamp of the event.
- `log_type` (varchar): Categorization of the log severity (`info`, `warning`, `error`).
- `title` (text): A short, human-readable summary of the event (e.g., "License activation failed").
- `description` (longtext): Optional longer description.
- `request_data` (longtext): JSON encoded payload sent *to* an API or the server.
- `response_data` (longtext): JSON encoded payload received *from* an API or an internal service.
- `metadata` (longtext): JSON encoded associative array for extra context. Often used to hold PHP `Throwable` exception traces, error messages from `WP_Error`, or system states.
- `object_type` (varchar): The entity involved (e.g., `offer`, `settings`, `order`).
- `object_id` (bigint): The ID of the related object (e.g., Offer ID, Order ID).
- `ip_address` (varchar): The client's IP address.
- `user_id` (bigint): The ID of the WordPress user who initiated the action, or `0` for system-level background events.
- `status` (varchar): The status classification (e.g., `success`, `failed`, `exception`, `invalid`).

## Core Abstractions

### `LoggerInterface`
`WPAnchorBay\UpsellBay\Domain\Logging\LoggerInterface` defines a PSR-3 inspired interface but is strongly typed for our `upsellbay_logs` table schema.

It requires three methods:
- `info( string $message, array $context = array() )`
- `warning( string $message, array $context = array() )`
- `error( string $message, array $context = array() )`

The `$context` array maps directly to the columns defined in the schema (e.g., `$context['metadata']`, `$context['status']`, `$context['request_data']`).

### `DatabaseLogger`
`WPAnchorBay\UpsellBay\Domain\Logging\DatabaseLogger` implements the `LoggerInterface`. It delegates database execution to `LogRepository`. It automatically sanitizes non-scalar data passed into the JSON columns via `wp_json_encode()`.

### `LogRepository`
`WPAnchorBay\UpsellBay\Data\LogRepository` performs raw DB access:
- `insert()`: Writes the log.
- `get_logs()`: Returns paginated records (used by `LogsListTable`).
- `get_log()`: Retrieves a single log entry by ID for the details view.
- `bulk_delete()` & `delete()`: Removes logs.
- `delete_logs_older_than()`: Background cleanup query.

## Implementation Paradigms

The logging system employs two distinct logging paradigms depending on the context:

### 1. Event-Driven Logging (The `Hooks` wrapper)
For standard domain events (like settings changes, offer CRUD operations, rule execution, and license state changes), we decouple the logger from the service logic via WordPress hooks.

Services broadcast events:
```php
Hooks::action( 'offer_created', $offer_id, $args );
```

The `Core\Plugin` container listens to these internal hooks and writes the log:
```php
add_action( Constants::hook_name( 'offer_created' ), array( $this, 'log_offer_created' ), 10, 2 );

public function log_offer_created( int $offer_id, array $args ): void {
    $this->container->get( LoggerInterface::class )->info( 'Offer created', [
        'object_type' => 'offer',
        'object_id'   => $offer_id,
        'metadata'    => $args,
    ]);
}
```
**Benefits:** Keeps domain services agnostic of the logging implementation and reduces dependency injection bloat.

### 2. Direct Dependency Injection (For Exceptions)
For places where unexpected system errors, fatal exceptions, or complex API failures occur that do *not* naturally map to a domain event, the `LoggerInterface` is injected directly into the class.

For example, `WizardController.php` and `OfferEditPage.php` inject `LoggerInterface` specifically to log `Throwable` stack traces inside `catch` blocks:
```php
catch ( Throwable $throwable ) {
    $this->logger->error( 'Failed to save offer', array(
        'status'   => 'exception',
        'metadata' => array(
            'exception' => get_class( $throwable ),
            'file'      => $throwable->getFile(),
            'line'      => $throwable->getLine(),
            'trace'     => $throwable->getTraceAsString(),
        )
    ));
}
```

## Admin View and Export

The Logs UI is situated at **WooCommerce -> UpsellBay -> Settings -> Logs**.

- **List Table:** Powered by `LogsListTable`, extending `WP_List_Table`.
  - Supports filtering by `status`.
  - Displays Title, Date, Type, Status, and Event ID.
- **Details View:** Clicking "Details" drops into `LogsSectionRenderer->render_details()`.
  - JSON columns (`metadata`, `request_data`, `response_data`) are detected, decoded, and pretty-printed into preformatted boxes.
- **Export "Copy to Clipboard":** Each log provides an export mechanism. The log data is serialized into a plain text summary, and an integrated Backbone/Clipboard.js action copies the content safely for support debugging.

## Background Maintenance

Log tables grow infinitely without bounds. To prevent database bloat, an automated Action Scheduler job runs daily.
- **Action:** `upsellbay_cleanup_logs`
- **Execution:** Calls `$repository->delete_logs_older_than( 30 )`.
- **Retention Limit:** 30 days.

This is registered inside `Plugin.php` during the `admin_init` hooks.
