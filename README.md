# Blaze Commerce Coupon Modifications

A custom WooCommerce plugin built for **Safe Life Defense** that extends coupon functionality with composite product component restrictions.

## Purpose

This plugin adds a new "Composite component products" field to WooCommerce coupon usage restrictions. It allows coupons to apply **only when specific products are selected as part of a composite product**, blocking the discount when those same products are purchased standalone.

## Features

- New "Composite component products" restriction field in coupon settings
- Discount applies to qualifying component products within composites
- Blocks discount for standalone purchases of restricted products
- Works alongside standard WooCommerce "Products" restrictions
- OR logic: any matching component qualifies for the discount
- Full support for variable products and variations

## Requirements

- WordPress 5.0+
- WooCommerce 5.0+
- WooCommerce Composite Products
- PHP 7.4+

## Installation

1. Download or clone this repository
2. Upload the `blaze-commerce-coupon-modifications` folder to `/wp-content/plugins/`
3. Activate the plugin through the WordPress admin **Plugins** menu
4. Ensure WooCommerce and WooCommerce Composite Products are installed and activated

## Usage

### Setting Up a Coupon

1. Navigate to **Marketing → Coupons** in WordPress admin
2. Create a new coupon or edit an existing one
3. Set the **Discount type** to "Fixed product discount"
4. Enter your desired **Coupon amount**
5. Go to the **Usage restriction** tab
6. Find the **"Composite component products"** field
7. Search and select the product(s) that must be composite components
8. Save the coupon

### How It Works

**Discount WILL apply when:**
- Customer adds a composite product with the restricted component selected

**Discount will NOT apply when:**
- Customer adds the restricted product as a standalone item
- Customer adds a composite without the restricted component selected

**Standard WooCommerce behavior:**
- Products in the standard "Products" field work normally
- If a product is in BOTH fields, the composite restriction takes priority

## Example

**Scenario:** Create a $50 discount on "Hyperline™ Level IIIA Panels" only when selected as part of the "Unity™ Hybrid Armor System" composite product.

**Configuration:**
- Coupon code: `NEWGEAR`
- Discount type: Fixed product discount
- Amount: $50
- Composite component products: "Hyperline™ Level IIIA Panels"

**Result:**
- Customer buys composite with Hyperline Panels → $50 off the panels
- Customer buys Hyperline Panels standalone → No discount

## File Structure

```
blaze-commerce-coupon-modifications/
├── blaze-commerce-coupon-modifications.php   # Main plugin file
├── includes/
│   ├── class-bc-cm-admin.php                 # Admin coupon fields
│   └── class-bc-cm-coupon-validator.php      # Cart validation logic
├── uninstall.php                             # Cleanup on uninstall
├── CLAUDE.md                                 # Technical documentation
└── README.md                                 # This file
```

## Technical Details

### Hooks Used

- `woocommerce_coupon_options_usage_restriction` - Adds the product selector field
- `woocommerce_coupon_options_save` - Saves field data
- `woocommerce_coupon_get_product_ids` - Dynamically adds qualifying components
- `woocommerce_coupon_is_valid` - Main coupon validity check
- `woocommerce_coupon_is_valid_for_product` - Per-product eligibility check

### Meta Key

- **Key:** `_bc_cm_composite_component_products`
- **Value:** Array of product IDs
- **Storage:** Coupon post meta

## Uninstall

When the plugin is deleted through WordPress admin:
- All `_bc_cm_composite_component_products` meta entries are removed
- Coupons revert to standard WooCommerce behavior
- No other data is modified

## Troubleshooting

**Coupon shows $0.00 discount**
The product must be added as a composite component, not standalone. Composite parents have $0 price with component-level pricing.

**"Coupon is not applicable" error**
The restricted product is not in the cart as a composite component.

**Discount not applying to expected products**
Verify the correct product (parent, not variation) is selected in the restriction field.

## Changelog

### 1.0.1
- Fixed discount application to target qualifying components instead of composite parents
- Added support for standard WooCommerce "Products" field alongside composite restrictions
- Composite restriction now overrides standard field for products in both

### 1.0.0
- Initial release
- Added composite component products restriction field
- Implemented validation logic for composite component discounts

## Built By

[Blaze Commerce](https://blazecommerce.io)

## License

GPL-2.0+
