# Blaze Commerce Coupon Modifications

## Overview

This WordPress plugin extends WooCommerce coupon functionality to add a new usage restriction based on composite product component selections. It allows administrators to specify products that must be present as composite components (not standalone) for a coupon to apply.

## Plugin Purpose

When a fixed product discount coupon is configured with this restriction:
- The discount applies to **qualifying component products** when they are part of a composite
- The discount does **NOT** apply to standalone products matching the restriction list
- Uses OR logic: any one matching component qualifies for the discount
- Standard WooCommerce "Products" field restrictions are still respected for other products
- Our composite restriction **overrides** standard restrictions for products in both fields

## File Structure

```
blaze-commerce-coupon-modifications/
├── blaze-commerce-coupon-modifications.php   # Main plugin entry point
├── includes/
│   ├── class-bc-cm-admin.php                 # Admin UI for coupon fields
│   └── class-bc-cm-coupon-validator.php      # Cart validation logic
├── uninstall.php                             # Database cleanup on uninstall
└── CLAUDE.md                                 # This file
```

## Architecture

### Main Plugin File (`blaze-commerce-coupon-modifications.php`)

- Singleton pattern via `BC_CM_Plugin` class
- Dependency checking for WooCommerce and WooCommerce Composite Products
- Constants: `BC_CM_VERSION`, `BC_CM_PLUGIN_DIR`, `BC_CM_PLUGIN_FILE`, `BC_CM_META_KEY`
- Initializes on `plugins_loaded` hook (priority 20) to ensure WooCommerce loads first

### Admin Class (`includes/class-bc-cm-admin.php`)

**Hooks Used:**
- `woocommerce_coupon_options_usage_restriction` - Adds product selector field
- `woocommerce_coupon_options_save` - Saves field data with security checks

**Key Methods:**
- `add_composite_component_field()` - Renders the multi-select product search
- `save_composite_component_field()` - Validates and saves product IDs

### Coupon Validator Class (`includes/class-bc-cm-coupon-validator.php`)

**Hooks Used:**
- `woocommerce_coupon_get_product_ids` - Dynamically adds qualifying components to allowed products
- `woocommerce_coupon_is_valid` - Main coupon validity check
- `woocommerce_coupon_is_valid_for_product` - Per-product eligibility check
- Cart update hooks for cache invalidation

**Key Methods:**
- `get_restricted_products()` - Retrieves our custom restriction list from coupon meta
- `find_qualifying_components()` - Scans cart for qualifying component products
- `is_in_composite_restrictions()` - Checks if a product is in our custom restrictions
- `filter_coupon_product_ids()` - Adds qualifying components to WooCommerce's product_ids
- `validate_coupon()` - Ensures at least one qualifying component or standard product exists
- `validate_coupon_for_product()` - Determines per-product discount eligibility

**Validation Logic Flow:**
1. Get restricted product IDs from our custom coupon meta
2. Scan cart for items matching restricted products
3. For each match, check if it's a composite child using `wc_cp_is_composited_cart_item()`
4. If yes, mark that component as qualifying for the discount
5. Apply discount only to qualifying components (not standalone, not composite parents)
6. For products NOT in our restrictions, use default WooCommerce behavior

## Discount Application Logic

| Product Type | In Our Restrictions | Discount Applied? |
|--------------|---------------------|-------------------|
| Composite component | Yes | YES - Gets discount |
| Standalone product | Yes | NO - Blocked by our restriction |
| Composite parent | Any | NO - Has $0 price (component pricing) |
| Any product | No | Default WooCommerce behavior |

**Why components, not parents?**
Composite products typically use component-level pricing, meaning the composite parent has $0 price and each component has its own price. Applying a discount to a $0 item results in $0 discount.

## Interaction with Standard WooCommerce Restrictions

The plugin respects and extends WooCommerce's standard "Products" field:

1. **Products only in standard "Products" field** → Normal WooCommerce behavior
2. **Products only in our "Composite component products" field** → Only get discount as composite components
3. **Products in BOTH fields** → Our restriction overrides; only get discount as composite components

## Meta Key

- **Key:** `_bc_cm_composite_component_products`
- **Value:** Array of product IDs (integers)
- **Storage:** WooCommerce coupon post meta (`wp_postmeta`)

## Dependencies

- WordPress 5.0+
- WooCommerce 5.0+
- WooCommerce Composite Products
- PHP 7.4+

## WooCommerce Composite Products Integration

**Helper Functions Used:**
| Function | Purpose |
|----------|---------|
| `wc_cp_is_composited_cart_item($item, $cart)` | Check if cart item is a composite child |
| `wc_cp_get_composited_cart_item_container($item, $cart, $return_key)` | Get parent composite |
| `wc_cp_is_composite_container_cart_item($item)` | Check if item is a composite parent |

**Cart Item Data Structure:**
- Composite parent: Has `composite_children` and `composite_data` keys
- Composite child: Has `composite_parent`, `composite_item`, and `composite_data` keys

## Security Implementation

| Measure | Location | Implementation |
|---------|----------|----------------|
| Direct access prevention | All PHP files | `if (!defined('ABSPATH')) exit;` |
| CSRF protection | Admin save | Nonce via `wp_nonce_field()` / `wp_verify_nonce()` |
| Capability check | Admin save | `current_user_can('manage_woocommerce')` |
| Input sanitization | Admin save | `array_map('absint', ...)`, `array_filter()` |
| Output escaping | Admin display | `esc_attr()`, `esc_html()` |

## Naming Conventions

- **Prefix:** `BC_CM_` for classes, functions, constants
- **Text Domain:** `bc_cm_` for i18n
- **Meta Key:** `_bc_cm_composite_component_products` (underscore prefix hides from custom fields UI)

## Development Notes

### Adding New Restrictions

To add additional restriction types:
1. Add field in `BC_CM_Admin::add_composite_component_field()`
2. Save in `BC_CM_Admin::save_composite_component_field()`
3. Add validation logic in `BC_CM_Coupon_Validator`

### Caching Strategy

The validator uses internal caching (`$qualifying_components`, `$restricted_products_cache`) to avoid repeated database queries and cart scans during a single request. Cache is cleared on cart modifications via these hooks:
- `woocommerce_cart_loaded_from_session`
- `woocommerce_add_to_cart`
- `woocommerce_cart_item_removed`
- `woocommerce_cart_item_restored`

### Error Messages

Custom error thrown when coupon has composite restrictions but no qualifying components (and no standard products):
```php
throw new Exception(
    __('This coupon requires specific products to be selected as composite components.', 'bc_cm_'),
    WC_Coupon::E_WC_COUPON_NOT_APPLICABLE
);
```

## Testing Scenarios

1. **Composite with qualifying component** → Coupon applies to the component product
2. **Restricted product standalone** → Coupon does NOT apply to that product
3. **Product in standard "Products" field only** → Coupon applies normally (WooCommerce default)
4. **Product in BOTH fields, as composite component** → Coupon applies
5. **Product in BOTH fields, standalone** → Coupon does NOT apply (our restriction overrides)
6. **Composite parent product** → Coupon does NOT apply ($0 price)
7. **Multiple composites, one with qualifying component** → Coupon applies only to qualifying component
8. **No restrictions set in our field** → Coupon works normally per WooCommerce defaults

## Uninstall Behavior

On plugin deletion via WordPress admin:
- Removes all `_bc_cm_composite_component_products` meta entries from database
- Does NOT modify coupons themselves
- Coupons revert to standard WooCommerce behavior

## Changelog

### 1.0.1
- Fixed discount application to target qualifying components instead of composite parents
- Composite parents have $0 price with component-level pricing, so discount now applies to components
- Added support for standard WooCommerce "Products" field to work alongside our restriction
- Our composite restriction now overrides standard field for products in both
- Added `woocommerce_coupon_get_product_ids` filter to dynamically allow qualifying components
- Added `is_in_composite_restrictions()` helper method

### 1.0.0
- Initial release
- Added composite component products restriction field to coupon Usage Restrictions tab
- Implemented validation logic for composite component discount application
- Added uninstall cleanup
