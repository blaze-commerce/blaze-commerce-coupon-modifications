# Blaze Commerce Coupon Modifications

## Overview

This WordPress plugin extends WooCommerce coupon functionality to add usage restrictions based on:

1. **Composite Product Component Selections** - Products that must be present as composite components (not standalone) for a coupon to apply
2. **Kit Builder / MyCustomizer Property Rules** - Property-based rules that must match for Kit Builder products

## Plugin Purpose

When a fixed product discount coupon is configured with these restrictions:

### Composite Component Restrictions
- The discount applies to **qualifying component products** when they are part of a composite
- The discount does **NOT** apply to standalone products matching the restriction list
- Uses OR logic: any one matching component qualifies for the discount

### Kit Builder Property Rules
- The discount applies to **Kit Builder products** whose properties match all configured rules
- Uses AND logic between rules: all rules must match for the coupon to apply
- Uses OR logic within a value: use pipe `|` delimiter to specify multiple acceptable values (e.g., `Hyperline|HG2`)
- Uses partial/contains matching (case-insensitive) for flexibility
- Example: Rule 1 = `Armor Type: Hyperline|HG2`, Rule 2 = `Size: Large` → Matches Hyperline+Large OR HG2+Large

### General Behavior
- Standard WooCommerce "Products" field restrictions are still respected for other products
- Our custom restrictions **override** standard restrictions for products in both fields

## File Structure

```
blaze-commerce-coupon-modifications/
├── blaze-commerce-coupon-modifications.php   # Main plugin entry point
├── includes/
│   ├── class-bc-cm-admin.php                 # Admin UI for coupon fields
│   ├── class-bc-cm-coupon-validator.php      # Cart validation logic
│   └── class-bc-cm-documentation.php         # Admin documentation page
├── uninstall.php                             # Database cleanup on uninstall
└── CLAUDE.md                                 # This file
```

## Architecture

### Main Plugin File (`blaze-commerce-coupon-modifications.php`)

- Singleton pattern via `BC_CM_Plugin` class
- Dependency checking for WooCommerce (required)
- WooCommerce Composite Products and MyCustomizer are optional (features enabled based on availability)
- Constants:
  - `BC_CM_VERSION` - Plugin version (1.3.8)
  - `BC_CM_PLUGIN_DIR` - Plugin directory path
  - `BC_CM_PLUGIN_FILE` - Plugin file path
  - `BC_CM_META_KEY` - Composite component products meta key
  - `BC_CM_KIT_BUILDER_META_KEY` - Kit Builder rules meta key
- Initializes on `plugins_loaded` hook (priority 20) to ensure WooCommerce loads first

### Admin Class (`includes/class-bc-cm-admin.php`)

**Hooks Used:**
- `woocommerce_coupon_options_usage_restriction` - Adds product selector and Kit Builder rules fields (priorities 10 and 11)
- `woocommerce_coupon_options_save` - Saves field data with security checks (priority 10)
- `admin_enqueue_scripts` - Adds JavaScript for Kit Builder rules UI

**Key Methods:**
- `add_composite_component_field()` - Renders the multi-select product search
- `add_kit_builder_rules_field()` - Renders the Kit Builder property rules UI
- `save_composite_component_field()` - Validates and saves product IDs
- `save_kit_builder_rules_field()` - Validates and saves Kit Builder rules
- `get_kit_builder_inline_script()` - JavaScript for add/remove rule functionality

### Coupon Validator Class (`includes/class-bc-cm-coupon-validator.php`)

**Constructor-Level Admin Order Bypass:**

The validator performs an early check in the constructor via `is_order_admin_context_static()` to completely skip hook registration when processing admin order AJAX actions. This is the most aggressive approach to prevent any interference with order editing functionality. The bypassed actions include:

- `woocommerce_add_coupon_discount` - Adding coupons to orders in admin
- `woocommerce_remove_order_coupon` - Removing coupons from orders
- `woocommerce_add_order_item`, `woocommerce_remove_order_item`
- `woocommerce_calc_line_taxes`, `woocommerce_calc_order_totals`
- `woocommerce_save_order_items`, `woocommerce_load_order_items`
- And other order-related AJAX actions

**Hooks Used (cart context only):**
- `woocommerce_coupon_get_product_ids` - Dynamically adds qualifying products to allowed list (priority 10)
- `woocommerce_coupon_is_valid` - Main coupon validity check (priority 10001 - runs after theme filters)
- `woocommerce_coupon_is_valid_for_product` - Per-product eligibility check (priority 10001 - runs after theme's `nns_exclude_product_from_coupons` at priority 9999)
- `woocommerce_coupon_get_items_to_apply` - Injects Kit Builder items into native discount calculation (priority 10)
- `woocommerce_coupon_get_items_to_validate` - Swaps Kit Builder replica products with original products during validation (priority 999)
- Cart update hooks for cache invalidation:
  - `woocommerce_cart_loaded_from_session`
  - `woocommerce_add_to_cart`
  - `woocommerce_cart_item_removed`
  - `woocommerce_cart_item_restored`

**Key Methods for Composite Products:**
- `get_restricted_products()` - Retrieves composite component restriction list from coupon meta
- `find_qualifying_components()` - Scans cart for qualifying component products

**Key Methods for Kit Builder:**
- `get_kit_builder_rules()` - Retrieves Kit Builder rules from coupon meta
- `is_kit_builder_item()` - Checks if cart item is a MyCustomizer/Kit Builder product
- `kit_builder_item_matches_rules()` - Checks if item properties match all rules
- `find_qualifying_kit_builder()` - Scans cart for qualifying Kit Builder products
- `get_kit_builder_shop_product_id()` - Gets the original product ID from Kit Builder item
- `inject_kit_builder_items_for_discount()` - Injects Kit Builder items into WooCommerce's native discount calculation

**Shared Methods:**
- `filter_coupon_product_ids()` - Adds qualifying products to WooCommerce's product_ids (includes re-entrancy guard)
- `validate_coupon()` - Ensures at least one qualifying item or standard product exists
- `validate_coupon_for_product()` - Determines per-product discount eligibility
- `fix_kit_builder_items_for_validation()` - Swaps Kit Builder replica products with original shop products during validation
- `is_cart_context()` - Checks if we're in a valid cart context (not admin order editing)
- `is_order_admin_context_static()` - Static check for admin order AJAX actions (used in constructor)
- `clear_cache()` - Clears internal caches on cart modifications

**Re-entrancy Guard:**

The `filter_coupon_product_ids()` method includes a re-entrancy guard via the `$filtering_product_ids` flag. This prevents infinite loops that could occur when:
1. `filter_coupon_product_ids()` is called
2. Our code calls `$coupon->get_product_ids('edit')` to get original product IDs
3. WooCommerce triggers the filter again
4. Without the guard, this would create an infinite loop

The guard ensures we return the original product IDs immediately if we're already processing the filter.

**Validation Logic Flow:**
1. Check if we're in admin order context (constructor) - skip all hooks if so
2. Get restrictions (composite products and/or Kit Builder rules) from coupon meta
3. Scan cart for items matching restrictions
4. For composite restrictions:
   - Check if item is a composite child using `wc_cp_is_composited_cart_item()`
   - If yes, add product ID to allowed list (WooCommerce handles discount natively)
5. For Kit Builder rules:
   - Check if item has `mczrMetas` (Kit Builder product)
   - Check if `shopProductId` matches a product in coupon's Products field
   - Check if item's `summary_v2` properties match all rules (partial matching, AND logic)
   - If yes, inject item into `woocommerce_coupon_get_items_to_apply` filter for native discount calculation
6. For products NOT in our restrictions, use default WooCommerce behavior

## Discount Application Logic

| Product Type | In Our Restrictions | Discount Applied? |
|--------------|---------------------|-------------------|
| Composite component | Yes (component field) | YES - Gets discount (native WooCommerce) |
| Standalone product | Yes (component field) | NO - Blocked by our restriction |
| Composite parent | Any | NO - Has $0 price (component pricing) |
| Kit Builder product | Yes (rules match) | YES - Gets discount (native WooCommerce via item injection) |
| Kit Builder product | Yes (rules don't match) | NO - Rules not satisfied |
| Any product | No | Default WooCommerce behavior |

**Why components, not parents?**
Composite products typically use component-level pricing, meaning the composite parent has $0 price and each component has its own price. Applying a discount to a $0 item results in $0 discount.

## Interaction with Standard WooCommerce Restrictions

The plugin respects and extends WooCommerce's standard "Products" field:

1. **Products only in standard "Products" field** - Normal WooCommerce behavior
2. **Products only in "Composite component products" field** - Only get discount as composite components
3. **Products in BOTH fields** - Our restriction overrides; only get discount as composite components
4. **Kit Builder products with rules configured** - Must have original product in "Products" field AND match all Kit Builder rules; discount applied via native WooCommerce calculation

## Meta Keys

### Composite Component Products
- **Key:** `_bc_cm_composite_component_products`
- **Value:** Array of product IDs (integers)
- **Storage:** WooCommerce coupon post meta (`wp_postmeta`)

### Kit Builder Rules
- **Key:** `_bc_cm_kit_builder_rules`
- **Value:** Array of rule objects, each with `key` and `value` properties
- **Storage:** WooCommerce coupon post meta (`wp_postmeta`)
- **Example:**
```php
array(
    array( 'key' => 'Armor Type', 'value' => 'Hyperline' ),
    array( 'key' => 'Size', 'value' => 'Large' ),
)
```

## Dependencies

- WordPress 5.0+
- WooCommerce 5.0+ (required)
- WooCommerce Composite Products (optional - composite features enabled if present)
- MyCustomizer / Kit Builder by GoKickFlip (optional - Kit Builder features enabled if present)
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

## MyCustomizer / Kit Builder Integration

**Cart Item Data Structure:**
Kit Builder products have `mczrMetas` in their cart item data:
```php
$cart_item['mczrMetas'] = array(
    'summary_v2' => array(
        array(
            'key'   => 'Armor Type',
            'value' => 'Hyperline Level IIIA',
        ),
        array(
            'key'   => 'Size',
            'value' => 'Large',
        ),
        // ... more properties
    ),
    'shopProductId' => 12345, // Original product ID
    // ... other mczr data
);
```

**Matching Logic:**
- **Partial/Contains Matching:** Rule key "Armor" matches property key "Armor Type"
- **Case-Insensitive:** "hyperline" matches "Hyperline"
- **AND Logic:** All configured rules must match for the coupon to apply
- **Array Values:** If property value is an array, it's joined with ", " for matching

**Native Discount Injection Approach:**

Kit Builder products create new product IDs (replica products) when added to cart. WooCommerce's `WC_Discounts::get_items_to_apply_coupon()` method normally excludes these because `is_valid_for_product()` returns false for replica products. To solve this:

1. The original Kit Builder product must be added to WooCommerce's "Products" field in the coupon
2. When `woocommerce_coupon_get_product_ids` filter runs:
   - Plugin checks cart for Kit Builder items with matching `shopProductId`
   - If Kit Builder rules are set, checks if item properties match all rules
   - Adds qualifying replica product IDs to the allowed list (for validation purposes)
3. When `woocommerce_coupon_get_items_to_apply` filter runs:
   - Plugin gets all items from `WC_Discounts->get_items()`
   - Finds qualifying Kit Builder items that weren't included by default validation
   - Injects them into the items array that will receive the discount
4. WooCommerce's native discount calculation (`apply_coupon_percent`, `apply_coupon_fixed_product`, etc.) processes Kit Builder items normally

**Benefits of this approach:**
- Discounts appear as proper coupon discounts (not fees)
- Shows correctly in cart totals
- Shows correctly in order totals
- Shows correctly in admin order editing
- Gets included in "Total Discounts" calculations
- Works with both `fixed_product` and `percent` discount types

## Security Implementation

| Measure | Location | Implementation |
|---------|----------|----------------|
| Direct access prevention | All PHP files | `if (!defined('ABSPATH')) exit;` |
| CSRF protection | Admin save | Nonce via `wp_nonce_field()` / `wp_verify_nonce()` |
| Capability check | Admin save | `current_user_can('manage_woocommerce')` |
| Input sanitization | Admin save | `array_map('absint', ...)` for IDs, `sanitize_text_field()` for rules |
| Output escaping | Admin display | `esc_attr()`, `esc_html()` |

## Naming Conventions

- **Prefix:** `BC_CM_` for classes, functions, constants
- **Text Domain:** `bc_cm_` for i18n
- **Meta Keys:**
  - `_bc_cm_composite_component_products` (underscore prefix hides from custom fields UI)
  - `_bc_cm_kit_builder_rules`

## Development Notes

### Adding New Restrictions

To add additional restriction types:
1. Add constant for new meta key in main plugin file
2. Add admin field in `BC_CM_Admin` class
3. Add save method in `BC_CM_Admin` class
4. Add validation logic in `BC_CM_Coupon_Validator` class
5. Update `filter_coupon_product_ids()` to include new qualifying products
6. Update `validate_coupon()` and `validate_coupon_for_product()` methods

### Caching Strategy

The validator uses internal caching to avoid repeated database queries and cart scans during a single request:
- `$qualifying_components` - Cached qualifying composite component cart items (keyed by coupon ID)
- `$qualifying_kit_builder` - Cached qualifying Kit Builder cart items (keyed by coupon ID)
- `$restricted_products_cache` - Cached composite component restrictions by coupon ID
- `$kit_builder_rules_cache` - Cached Kit Builder rules by coupon ID
- `$filtering_product_ids` - Re-entrancy guard flag for `filter_coupon_product_ids()`

Cache is cleared on cart modifications via these hooks:
- `woocommerce_cart_loaded_from_session`
- `woocommerce_add_to_cart`
- `woocommerce_cart_item_removed`
- `woocommerce_cart_item_restored`

### Error Messages

Custom error thrown when coupon has custom restrictions but no qualifying items (and no standard products):
```php
throw new Exception(
    __('This coupon requires specific product configurations that are not in your cart.', 'bc_cm_'),
    WC_Coupon::E_WC_COUPON_NOT_APPLICABLE
);
```

## Testing Scenarios

### Composite Component Restrictions
1. **Composite with qualifying component** - Coupon applies to the component product
2. **Restricted product standalone** - Coupon does NOT apply to that product
3. **Product in standard "Products" field only** - Coupon applies normally (WooCommerce default)
4. **Product in BOTH fields, as composite component** - Coupon applies
5. **Product in BOTH fields, standalone** - Coupon does NOT apply (our restriction overrides)
6. **Composite parent product** - Coupon does NOT apply ($0 price)
7. **Multiple composites, one with qualifying component** - Coupon applies only to qualifying component

### Kit Builder Property Rules
8. **Kit Builder product matching all rules with original product in Products field** - Discount applied as native coupon discount
9. **Kit Builder product matching only some rules** - Coupon does NOT apply (AND logic)
10. **Kit Builder product with no matching rules** - Coupon does NOT apply
11. **Kit Builder product not in Products field** - Coupon does NOT apply (shopProductId must match)
12. **Regular product (non-Kit Builder) with Kit Builder rules set** - Falls through to default behavior
13. **Partial key match ("Armor" matches "Armor Type")** - Coupon applies

### Combined Restrictions
14. **Both composite and Kit Builder restrictions set, composite matches** - Coupon applies to composite component
15. **Both restrictions set, Kit Builder matches** - Coupon applies to Kit Builder product (native discount)
16. **Both restrictions set, neither matches** - Coupon not applicable
17. **No restrictions set in our fields** - Coupon works normally per WooCommerce defaults

### Admin Order Editing
18. **Adding coupon to order in admin** - Plugin hooks are bypassed, standard WooCommerce behavior
19. **Removing coupon from order in admin** - Plugin hooks are bypassed, standard WooCommerce behavior
20. **Editing order items with coupon applied** - Plugin does not interfere with order calculations

## Uninstall Behavior

On plugin deletion via WordPress admin:
- Removes all `_bc_cm_composite_component_products` meta entries from database
- Removes all `_bc_cm_kit_builder_rules` meta entries from database
- Does NOT modify coupons themselves
- Coupons revert to standard WooCommerce behavior

## Changelog

### 1.3.8
- **Feature:** Added support for multiple values in Kit Builder property rules using pipe delimiter
- Use `|` to specify multiple acceptable values in a single rule (OR logic)
- Example: `Hyperline|HG2` matches if property contains "Hyperline" OR "HG2"
- AND logic between rules is preserved: all rules must still match
- Example scenario: Rule 1 = `Armor Type: Hyperline|HG2`, Rule 2 = `Size: Large`
  - ✓ Hyperline + Large
  - ✓ HG2 + Large
  - ✗ Hyperline + Medium
  - ✗ HG2 + Medium
- Updated version to 1.3.8

### 1.3.7
- **Bug fix:** Fixed Kit Builder coupon validation conflict with theme's `nns_exclude_product_from_coupons` filter
- **Root cause:** The child theme (`elessi-theme-child/functions.php`) has a function `nns_exclude_product_from_coupons` hooked at priority 9999 on `woocommerce_coupon_is_valid_for_product`. This function had the Kit Builder shop product ID (1338903) in its exclusion array. When our `fix_kit_builder_items_for_validation` swapped the replica product to the original shop product, the theme's filter saw it and returned `false`.
- **Fix:** Changed filter priorities to run AFTER the theme's filter:
  - `woocommerce_coupon_is_valid` - priority 10001 (was 10)
  - `woocommerce_coupon_is_valid_for_product` - priority 10001 (was 10)
  - `woocommerce_coupon_get_items_to_validate` - priority 999 (was 10)
- Our `validate_coupon_for_product` method does NOT use the incoming `$valid` value for Kit Builder items - it calculates its own result. By running at priority 10001, we override the theme's `false` decision for qualifying Kit Builder items.
- **Code cleanup:** Removed all debug logging statements for production release
- Updated version to 1.3.7

### 1.3.6
- **Bug fix:** Fixed "Coupon not valid with certain products" error (code 109) when Kit Builder is the ONLY item in cart
- **Root cause:** WooCommerce's `validate_coupon_excluded_items()` calls `is_valid_for_product()` which passes `$item->object` (cart item data) as `$values`. Even after swapping `$item->product`, the `$values` still contains the Kit Builder `mczrMetas` data. The previous `validate_coupon_for_product` filter only handled Kit Builder items when rules were configured, causing items without rules to fall through to default validation which fails for replica products.
- **Fix:** Completely rewrote `validate_coupon_for_product()` to handle Kit Builder items regardless of whether rules are configured:
  - Kit Builder items are now validated based on their `shopProductId` matching the coupon's Products field
  - If Kit Builder rules exist, both product match AND rules match are required
  - If no rules exist, only product match is required
  - If no product restrictions on coupon, rules are checked (if they exist)
  - If no restrictions and no rules, all Kit Builder items are allowed
- **Code cleanup:** Removed all debug logging statements (`error_log()` calls) for production release
- Updated version constant to stable 1.3.6

### 1.3.5
- **Bug fix:** Fixed "Coupon not valid with certain products" error when Kit Builder is the ONLY item in cart
- **Root cause identified:** The `woocommerce_coupon_error` filter can only modify error messages, NOT prevent the error from being returned. WooCommerce still returns a `WP_Error` object which causes the coupon to be rejected.
- **New approach:** Added `woocommerce_coupon_get_items_to_validate` filter hook via `fix_kit_builder_items_for_validation()` method
- This filter runs INSIDE `validate_coupon_product_ids()` BEFORE any exception is thrown
- For Kit Builder items, we swap the replica product with the original shop product temporarily during validation
- WooCommerce's validation then sees the original product ID (from `shopProductId`) and matches it against the coupon's Products field
- Removed unused `maybe_suppress_product_validation_error()` method and `woocommerce_coupon_error` hook
- Removed all debug logging statements for production release
- Updated version constant to stable 1.3.5

### 1.3.4
- **Bug fix:** Fixed "Coupon not valid with certain products" error for Kit Builder items
- WooCommerce's `validate_product_ids()` throws an exception BEFORE our validation hooks fire
- Added `woocommerce_coupon_error` filter hook to intercept and suppress this error when we have qualifying Kit Builder items
- Added `maybe_suppress_product_validation_error()` method to handle the error suppression
- Suppresses error codes 100 (E_WC_COUPON_NOT_APPLICABLE) and 113 (E_WC_COUPON_EXCLUDED_PRODUCTS)
- Removed remaining debug logging statements from `is_kit_builder_item()` and `find_qualifying_kit_builder()`
- Updated version constant to stable 1.3.4

### 1.3.1
- **Bug fix:** Fixed Kit Builder coupon validation when Kit Builder is the only item in cart
- Previously, coupons would only work when another valid product (like a composite component) was in the cart
- Kit Builder replica product IDs are now always added to the coupon's product_ids filter
- Removed requirement for Kit Builder rules to be set for the product_ids filter to work
- Kit Builder items now qualify based on `shopProductId` matching the coupon's Products field, regardless of rules
- Updated `inject_kit_builder_items_for_discount()` to not require rules for item injection
- Updated `validate_coupon()` to also check for qualifying composite components when forcing validity
- Improved documentation in method docblocks

### 1.3.0
- Internal version bump (no public changes documented)

### 1.2.3
- **Code cleanup:** Removed all debug logging statements (`error_log()` calls)
- **Documentation:** Updated CLAUDE.md with comprehensive documentation of current implementation
- Production-ready release with no debug code

### 1.2.2
- Fixed admin order editing interference by adding constructor-level hook bypass
- Added `is_order_admin_context_static()` method for early AJAX action detection
- Hooks are now completely skipped for admin order AJAX actions including:
  - `woocommerce_add_coupon_discount` (correct action for adding coupons to orders)
  - `woocommerce_remove_order_coupon`
  - And other order-related AJAX actions
- Added re-entrancy guard (`$filtering_product_ids` flag) in `filter_coupon_product_ids()` to prevent infinite loops
- Uses `$coupon->get_product_ids('edit')` to get raw IDs without triggering our filter
- Added comprehensive `is_cart_context()` checks to all public filter/action methods

### 1.2.1
- Added `is_cart_context()` method to properly detect cart vs admin order context
- Fixed issues with Kit Builder discounts not applying in cart
- Improved context detection for AJAX requests

### 1.2.0
- **BREAKING CHANGE:** Replaced fee-based discount approach with native WooCommerce discount injection
- Kit Builder discounts now appear as proper coupon discounts instead of separate fee lines
- Added `woocommerce_coupon_get_items_to_apply` filter hook for injecting Kit Builder items into discount calculation
- Added `inject_kit_builder_items_for_discount()` method to handle item injection
- Removed `maybe_add_kit_builder_fee()` method (no longer needed)
- Discounts now correctly appear in:
  - Cart totals
  - Order totals in admin
  - Order editing in admin
  - "Total Discounts" field calculations
- Better compatibility with WooCommerce's discount calculation flow

### 1.1.0
- Added Kit Builder / MyCustomizer property rules support
- New admin field for configuring property name/value rules
- AND logic for multiple rules (all must match)
- Partial/contains matching for flexibility (case-insensitive)
- Fee-based discount approach for Kit Builder products (shows as "Coupon: CODE (Kit Builder)" in cart)
- Kit Builder items matched via `shopProductId` against coupon's standard Products field
- Discount calculated and applied as negative cart fee during `woocommerce_coupon_get_product_ids` filter
- Supports both `fixed_product` and `percent` discount types for Kit Builder
- WooCommerce Composite Products now optional (features enabled if present)
- MyCustomizer / Kit Builder optional (features enabled if present)
- Added caching for Kit Builder rules and qualifying items

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
