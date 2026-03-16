# Migration Cleanup

## Goal

Simplify the project schema so a fresh install starts directly from the current `business_id` model without carrying historical migration steps that no longer represent the active architecture.

## What changed

- Consolidated the active business schema into the earliest migration steps that still run.
- Left later historical migrations as no-op steps so `migrate:fresh` does not recreate deprecated intermediate states.
- Kept the current runtime fully centered on `business_id`.
- Removed remaining tenancy terminology from repository content and architecture docs.

## Active schema now starts with

1. `businesses`
2. `users` business fields and roles
3. catalog tables: `suppliers`, `categories`, `products`
4. operation tables: `business_document_sequences`, `sales`, `sale_items`, `purchases`, `purchase_items`, `stock_movements`

## Integrity covered from the base schema

- Unique identifiers per business for:
  - `products.slug`
  - `products.sku`
  - `products.barcode`
  - `sales.sale_number`
  - `purchases.purchase_number`
- Composite foreign keys enforce same-business relations for:
  - product -> category
  - product -> supplier
  - sale -> user
  - sale item -> sale
  - sale item -> product
  - purchase -> user
  - purchase -> supplier
  - purchase item -> purchase
  - purchase item -> product
  - stock movement -> product
  - stock movement -> creator

## Validation performed

- `php artisan migrate:fresh --seed`
- `php artisan test`
- `npm run build`

All passed after the cleanup.

## Practical impact

- New environments start clean without old architectural baggage.
- The schema is easier to reason about.
- Runtime no longer depends on historical migration hardening to achieve business isolation.
