# Categories and Business Hardening

## What changed

### 1. Categories module
- Added a `categories` table scoped by `business_id`.
- Added `category_id` on `products`.
- Added category CRUD for business admins.
- Added category filters in the products listing.
- Added category selection in product create/edit forms.

### 2. Business isolation
- Category reads and writes are scoped to the current business.
- Product category assignment now validates that the category belongs to the same business.
- Existing business integrity hardening for operational items remains in place through the pending migration set.

## Files involved
- `app/Http/Controllers/Categories/CategoryController.php`
- `app/Http/Requests/Categories/StoreCategoryRequest.php`
- `app/Http/Requests/Categories/UpdateCategoryRequest.php`
- `app/Models/Category.php`
- `app/Models/Business.php`
- `app/Models/Product.php`
- `app/Http/Controllers/Products/ProductController.php`
- `app/Http/Requests/Products/StoreProductRequest.php`
- `resources/js/Pages/Categories/Index.vue`
- `resources/js/Pages/Categories/Create.vue`
- `resources/js/Pages/Categories/Edit.vue`
- `resources/js/Pages/Products/Index.vue`
- `resources/js/Pages/Products/Create.vue`
- `resources/js/Pages/Products/Edit.vue`
- `resources/js/Layouts/AuthenticatedLayout.vue`
- `routes/web.php`
- `database/migrations/2026_03_16_000700_create_categories_table_and_add_category_id_to_products_table.php`

## Why this was necessary
- Products were already the main module, but the catalog had no internal grouping.
- As the product list grows, searching everything manually becomes slower and more error-prone.
- Categories are part of the current MVP priorities and fit the shared-database `business_id` model cleanly.

## What to run

Apply pending migrations:

```bash
php artisan migrate
```

## Validation performed
- `php artisan test`
- `npm run build`

Both passed after these changes.

## Current status
- The project now has a usable category layer within the shared `business_id` model.
- `business_id` remains the isolation boundary.
- The next logical functional step after this is refining category usage in sales and stock reports if needed.
