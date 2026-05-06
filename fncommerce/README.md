# fncommerce

A complete e-commerce extension for the FINIS Framework. Provides a full-featured shopping cart, product catalog management, payment processing, shipping integration, and order management.

## Extension Loading (No File Copying Required)

fncommerce can be loaded as a **FINIS extension** without copying any files to your application root. Simply register the extension path and FINIS will:

- **Dynamically load** modules and includes from the extension directory
- **Automatically copy** only sections and database schemas on first install
- **Keep your application clean** - no need to merge fncommerce files into your codebase

```php
<?php
require_once "path/to/finis/src/FINIS.php";

$FINIS = new FINIS(array("src_application" => "."));
$FINIS->addExtension("path/to/fncommerce/");  // That's it!
$FINIS->finis();
```

This approach allows you to:

- Keep fncommerce in a separate directory or git submodule
- Update fncommerce independently from your application
- Maintain multiple sites using the same fncommerce installation

## Features

- **Product Catalog**: Multilingual products with up to 10 images, categories, manufacturers, and tiered pricing
- **Shopping Cart**: Session-based cart with quantity management
- **Checkout**: Multi-step wizard with shipping/billing address support
- **Payments**: Pluggable payment gateways (PayPal, Stripe, Bank Transfer, Cash on Delivery)
- **Shipping**: Pluggable shipping methods (DHL, Bartolini, Store Pickup, Lockers)
- **Promotions**: Voucher codes with usage limits, validity windows, and minimum purchase requirements
- **Order Management**: Complete order tracking with configurable statuses
- **Admin Interface**: Full CRUD operations for all entities
- **Internationalization**: 5 languages (Italian, English, Spanish, French, German)

## Requirements

- FINIS Framework (version 2017+)
- PHP 7.0+
- MySQL/MariaDB

## Installation

### As an Extension (Recommended)

fncommerce can be loaded as a FINIS extension without copying its code:

```php
<?php
require_once "path/to/finis/src/FINIS.php";

$FINIS = new FINIS(array("src_application" => "."));
$FINIS->addExtension("path/to/fncommerce/");
$FINIS->finis();
```

On first install, FINIS will automatically:
- Copy sections to your application
- Copy database schemas from `misc/fndatabase`
- Keep modules and includes referenced from the extension path

### Manual Installation

1. Copy `sections/fncommerce` to your application's `sections/` directory
2. Copy `controlcenter/sections/fnEcommerce` to your controlcenter sections
3. Copy `misc/fndatabase/fnc_*` files to your application's `misc/fndatabase/`
4. The `modules/fncommerce` directory can remain in place (referenced dynamically)

## Directory Structure

```
fncommerce/
├── modules/fncommerce/           # Frontend module
│   ├── config.php               # Main configuration
│   ├── section.php              # Request router
│   ├── functions/               # Core business logic
│   │   ├── fncommerce.php       # Bootstrap/config loader
│   │   ├── fnc_functions.php    # Business logic (products, cart, orders)
│   │   └── fnc_pages.php        # UI generation and templates
│   ├── modules/                 # Plugin system
│   │   ├── payments/            # Payment gateways
│   │   ├── shippingmethods/     # Shipping providers
│   │   ├── discount/            # Discount rules
│   │   └── vouchers/            # Voucher codes
│   ├── pages/                   # HTML templates (.tp.html)
│   └── languages/               # Translation files (CSV)
├── controlcenter/               # Admin interface
│   └── sections/fnEcommerce/    # 18 admin sections
├── misc/fndatabase/             # Database schemas (XML)
├── sections/fncommerce/         # Frontend section definition
└── README.md
```

## Configuration

Edit `modules/fncommerce/config.php`:

```php
$config['currency'] = "EUR";                    // Currency code
$config['currency_symbol'] = "&euro;";          // HTML symbol
$config['fnc_only_catalog'] = "0";              // 0=shop, 1=catalog only
$config['fnc_show_prices'] = "1";               // Show prices
$config['fnc_sort_product'] = "sort_order";     // Sort: sort_order|price|name|year
$config['fnc_enable_recipient'] = "";           // Allow different shipping address
```

## Frontend URLs

The module responds to the following operations via `?op=` parameter:

| URL Parameter | Description |
|---------------|-------------|
| `?op=` (empty) | Category/product browsing |
| `?op=view&id=X` | Single product detail |
| `?op=addtocart&id=X` | Add product to cart |
| `?op=showcart` | View shopping cart |
| `?op=shipping` | Shipping selection |
| `?op=ordersteps` | Checkout process |
| `?op=confirmorder` | Order confirmation |
| `?op=orderstatus` | View order history |
| `?op=offers` | Special offers |

## Admin Sections

Access via Control Center under "fnEcommerce":

| Section | Description |
|---------|-------------|
| Products | Manage product catalog |
| Categories | Product category hierarchy |
| Manufacturers | Brand management |
| Orders | View and manage orders |
| Order Status | Configure order statuses |
| Users | Customer accounts |
| Vouchers | Discount codes |
| Shipping Costs | Shipping rate tables |
| Shipping Zones | Geographic zones |
| Modules | Enable/disable payment and shipping plugins |

## Payment Methods

Available payment gateways in `modules/fncommerce/modules/payments/`:

- **PayPal** - PayPal integration with IPN support
- **Stripe** - Modern Stripe payment gateway
- **Bank_transfert** - Bank wire transfer
- **Payment_on_delivery** - Cash on delivery
- **NEXI** - Italian payment processor
- **Payment_on_lockers** - Locker payment

Enable/disable via `modules/payments/config.php`:

```php
$list_enabled_modules = "Bank_transfert,Paypal,STRIPE";
```

## Shipping Methods

Available shipping providers in `modules/fncommerce/modules/shippingmethods/`:

- **SHIPPING** - Standard configurable shipping
- **STOREPICKUP** - Customer store pickup
- **DHL** - DHL courier integration
- **Bartolini** - Italian parcel service
- **LOCKERS** - Parcel locker delivery

Enable/disable via `modules/shippingmethods/config.php`:

```php
$list_enabled_modules = "SHIPPING,STOREPICKUP,DHL";
```

## Database Tables

Core tables (prefixed with `fnc_`):

| Table | Description |
|-------|-------------|
| `fnc_products` | Product catalog |
| `fnc_categories` | Product categories |
| `fnc_manufacturers` | Brands |
| `fnc_orders` | Customer orders |
| `fnc_orderstatus` | Order status types |
| `fnc_users` | Customer information |
| `fnc_vouchercodes` | Discount vouchers |
| `fnc_shippingcosts_*` | Shipping rate tables |
| `fnc_shippingzones_*` | Shipping zone definitions |
| `fnc_products_to_categories` | Product-category relationships |

## Adding a Custom Payment Method

1. Create directory: `modules/fncommerce/modules/payments/YourPayment/`

2. Create `module.php`:
```php
<?php
defined('_FNEXEC') or die('Restricted access');

class fnc_payments_YourPayment extends fnc_payments
{
    function getHtml($order)
    {
        // Return payment form HTML
    }

    function processPayment($order)
    {
        // Handle payment processing
    }
}
```

3. Create `config.php` with your settings

4. Add language files in `languages/en/lang.csv` and `languages/it/lang.csv`

5. Enable in `modules/payments/config.php`

## Adding a Custom Shipping Method

1. Create directory: `modules/fncommerce/modules/shippingmethods/YourShipping/`

2. Create `module.php`:
```php
<?php
defined('_FNEXEC') or die('Restricted access');

class fnc_shippingmethods_YourShipping extends fnc_shippingmethods
{
    function calculateCost($order, $zone)
    {
        // Return shipping cost
    }

    function getHtml($order)
    {
        // Return shipping option HTML
    }
}
```

3. Create `config.php` with your settings

4. Add language files

5. Enable in `modules/shippingmethods/config.php`

## Template Customization

Templates are located in `modules/fncommerce/pages/` using FINIS template syntax:

- `product.tp.html` - Product detail page
- `shoppingcart.tp.html` - Shopping cart
- `order_confirm.tp.html` - Order confirmation
- `navigation.tp.html` - Category/product browsing
- `shipping_form.tp.html` - Shipping form

Template syntax:
```html
{variable}                              <!-- Variable substitution -->
{i18n:translation_key}                  <!-- Translation -->
<!-- if {condition} -->...<!-- end if --> <!-- Conditional -->
<!-- foreach {array} -->...<!-- end foreach --> <!-- Loop -->
```


## License

GNU General Public License (GPL)

## Author

Alessandro Vernassa <speleoalex@gmail.com>

## Version

2017-01-25