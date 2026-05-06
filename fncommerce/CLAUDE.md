# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**fncommerce** is an e-commerce module for the FINIS Framework (F.I.N.I.S.: Flatnux Is Now Infinitely Scalable). It provides complete shopping cart functionality, product catalog management, payment processing, and order management capabilities.

Version: 2017-01-25

## Module Architecture

### Core Components

The module follows a modular architecture with distinct layers:

1. **Frontend Module** (`modules/fncommerce/`)
   - Entry point: `section.php` - Main routing logic, handles operations via `?op=` parameter
   - Core logic: `functions/fncommerce.php` - Loads configuration and includes function libraries
   - Functions: `functions/fnc_functions.php` - Business logic (products, orders, cart, shipping)
   - Pages: `functions/fnc_pages.php` - HTML generation and navigation logic

2. **Control Center** (`controlcenter/sections/fnEcommerce/`)
   - Admin interfaces for managing products, categories, orders, shipping, payments
   - Uses `FNCC_XmltableEditor()` for CRUD operations
   - Each section has a `section.php` file that defines the admin interface

3. **Database Schemas** (`misc/fndatabase/`)
   - XML-based table definitions (e.g., `fnc_products.php`, `fnc_orders.php`)
   - Schema files start with `<?php exit(0);?>` security header
   - Define field types, forms, multilanguage support, and relationships

4. **Pluggable Module System** (`modules/fncommerce/modules/`)
   - **Payment Methods**: `payments/` - Paypal, Stripe, NEXI, Bank Transfer, Payment on Delivery
   - **Shipping Methods**: `shippingmethods/` - DHL, Bartolini, Delivery, Lockers
   - **Vouchers**: `vouchers/` - Discount codes and voucher management
   - **Discounts**: `discount/` - Cart discount rules
   - Each module has: `module.php` (class), `config.php` (settings), `languages/*.php` (i18n)

### Key Data Tables

- `fnc_products` - Product catalog with multilanguage support, up to 10 photos, attachments
- `fnc_categories` - Product categories (many-to-many via `fnc_products_to_categories`)
- `fnc_orders` - Order information
- `fnc_cart_items` - Shopping cart items
- `fnc_users` - Customer information
- `fnc_manufacturers` - Product manufacturers/brands
- `fnc_shippingcosts_*` - Shipping cost calculation tables
- `fnc_zones` - Shipping zones
- `fnc_orderstatus` - Order status types
- `fnc_vouchercodes` - Promotional voucher codes

### Operation Flow

The module routes requests via the `op` parameter in `section.php`:

- `op=view` - Display single product
- `op=addtocart` - Add product to cart
- `op=showcart` - Display shopping cart
- `op=shipping` - Shipping information form
- `op=ordersteps` - Checkout process steps
- `op=orderstatus` - View order status
- Default (no op) - Category/product navigation

### Configuration

Main config: `modules/fncommerce/config.php`

Key settings:
- `currency` and `currency_symbol` - Currency configuration
- `fnc_only_catalog` - Disable cart/checkout (catalog-only mode)
- `fnc_show_prices` - Show/hide prices
- `fnc_sort_product` - Product sorting (sort_order, price, name, year, insert)
- `fnc_enable_recipient` - Enable different shipping address

## Working with the Module

### Database Operations

Use FINIS database abstraction layer:
```php
$table = FN_XMDBTable("fnc_products");  // Raw table access
$form = FN_XMDBForm("fnc_products");    // Form-aware access with translations
$records = $table->GetRecords($restr, $start, $length, $orderby);
```

### Multilanguage Support

Products and categories support multilanguage fields:
- Schema defines `frm_multilanguages="auto"` for auto-translation
- Fields have language-suffixed versions (e.g., `name_it`, `name_en`, `description_it`)
- Use `$form->GetRecordTranslated($record)` to get localized version

### Adding Payment/Shipping Methods

Each method needs:
1. Directory in `modules/payments/` or `modules/shippingmethods/`
2. `module.php` - Class extending `fnc_payments` or defining method logic
3. `config.php` - Module configuration settings
4. `languages/*.php` - Translation strings

### Template System

Uses FINIS template engine with `.tp.html` files:
- Variable replacement: `{variable}`
- Conditionals: `<!-- if {condition} -->...<!-- end if {condition} -->`
- Loops: `<!-- foreach {array} -->...<!-- end foreach {array} -->`

### Key Functions

- `fnc_initTables()` - Initialize database tables
- `fnc_add_to_cart($product_id, $quantity)` - Add to cart
- `fnc_getproduct($id)` - Get single product
- `fnc_getcategories()` - Get all categories
- `fnc_format_price($price)` - Format price with currency
- `fnc_get_order_temp()` - Get current cart/order
- `fnc_translate_orderstatus($status)` - Translate order status

## Development Notes

- Module depends on FINIS Framework core (`$_FN` global array)
- All files use `defined('_FNEXEC') or die('Restricted access');` security check
- Configuration values are loaded into `$_FN` array for global access
- Uses session storage for cart management
- Product-to-category relationships are many-to-many
- Image handling supports thumbnails via `thumbsize` and `thumb_listheight` schema properties

## Testing

No formal testing framework found. Manual testing through:
1. Frontend: Navigate to module section in website
2. Admin: Access Control Center → fnEcommerce sections
3. Test payment gateways in sandbox mode when available

## Code Style

Follows FINIS Framework conventions:
- Functions: PascalCase with FN_ prefix (framework) or snake_case with fnc_ prefix (module)
- Variables: snake_case
- Globals: Accessed via `$_FN` array
- Use `FN_GetParam()` for sanitized input retrieval
