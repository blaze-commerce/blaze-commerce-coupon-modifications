<?php
/**
 * Coupon Validator class for Blaze Commerce Coupon Modifications.
 *
 * Handles the logic for determining if a coupon with composite component
 * or Kit Builder restrictions should be applied to products in the cart.
 *
 * @package Blaze_Commerce_Coupon_Modifications
 * @since 1.0.0
 */

// Security: Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coupon validator class.
 *
 * Validates coupon applicability based on:
 * 1. Composite component product restrictions
 * 2. Kit Builder / MyCustomizer property rules
 * 3. Standard WooCommerce product restrictions
 *
 * @since 1.0.0
 */
class BC_CM_Coupon_Validator {

	/**
	 * Cache of qualifying composite component cart item keys.
	 *
	 * @var array
	 */
	private $qualifying_components = array();

	/**
	 * Cache of qualifying Kit Builder cart item keys.
	 *
	 * @var array
	 */
	private $qualifying_kit_builder = array();

	/**
	 * Cache of restricted product IDs keyed by coupon ID.
	 *
	 * @var array
	 */
	private $restricted_products_cache = array();

	/**
	 * Cache of Kit Builder rules keyed by coupon ID.
	 *
	 * @var array
	 */
	private $kit_builder_rules_cache = array();

	/**
	 * Flag to prevent re-entrancy in filter_coupon_product_ids.
	 *
	 * @var bool
	 */
	private $filtering_product_ids = false;

	/**
	 * Constructor. Registers validation hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Skip hook registration entirely for order admin AJAX actions.
		// This is the most aggressive way to prevent any interference.
		if ( $this->is_order_admin_context_static() ) {
			return;
		}

		// Filter coupon's product_ids to include qualifying products.
		add_filter( 'woocommerce_coupon_get_product_ids', array( $this, 'filter_coupon_product_ids' ), 10, 2 );

		// Main coupon validity check.
		// Priority 10001 ensures we run LAST and can force validity for qualifying Kit Builder items.
		add_filter( 'woocommerce_coupon_is_valid', array( $this, 'validate_coupon' ), 10001, 3 );

		// Per-product validity check.
		// Priority 10001 ensures we run LAST after theme's nns_exclude_product_from_coupons (priority 9999).
		// This allows us to override exclusions for Kit Builder items that legitimately qualify.
		add_filter( 'woocommerce_coupon_is_valid_for_product', array( $this, 'validate_coupon_for_product' ), 10001, 4 );

		// Inject Kit Builder items into the items that should receive discounts.
		// This allows native WooCommerce discount calculation for Kit Builder products.
		add_filter( 'woocommerce_coupon_get_items_to_apply', array( $this, 'inject_kit_builder_items_for_discount' ), 10, 3 );

		// Modify items before validation to fix Kit Builder product ID mismatch.
		// This filter runs INSIDE validate_coupon_product_ids() BEFORE the error is thrown.
		// Priority 999 ensures we run LAST after any other plugins that might modify items.
		add_filter( 'woocommerce_coupon_get_items_to_validate', array( $this, 'fix_kit_builder_items_for_validation' ), 999, 2 );

		// Clear cache when cart is updated.
		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'clear_cache' ) );
		add_action( 'woocommerce_add_to_cart', array( $this, 'clear_cache' ) );
		add_action( 'woocommerce_cart_item_removed', array( $this, 'clear_cache' ) );
		add_action( 'woocommerce_cart_item_restored', array( $this, 'clear_cache' ) );
	}

	/**
	 * Static check for order admin context - can be called before instance methods.
	 *
	 * This is intentionally a simple, fast check with no side effects.
	 * Used in constructor to skip hook registration entirely.
	 *
	 * @since 1.2.3
	 * @return bool True if we are in an admin order context.
	 */
	private function is_order_admin_context_static(): bool {
		// Must be admin context.
		if ( ! is_admin() ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';

		if ( empty( $action ) ) {
			return false;
		}

		// List of order-related AJAX actions.
		// Note: WooCommerce uses 'woocommerce_add_coupon_discount' for adding coupons to orders,
		// NOT 'woocommerce_add_order_coupon' which is a common misconception.
		$order_actions = array(
			'woocommerce_add_coupon_discount',    // Add coupon to order (correct action).
			'woocommerce_remove_order_coupon',    // Remove coupon from order.
			'woocommerce_add_order_item',
			'woocommerce_remove_order_item',
			'woocommerce_add_order_item_meta',
			'woocommerce_remove_order_item_meta',
			'woocommerce_add_order_fee',
			'woocommerce_add_order_shipping',
			'woocommerce_add_order_tax',
			'woocommerce_remove_order_tax',
			'woocommerce_calc_line_taxes',
			'woocommerce_calc_order_totals',
			'woocommerce_save_order_items',
			'woocommerce_load_order_items',
			'woocommerce_add_order_note',
			'woocommerce_delete_order_note',
			'woocommerce_refund_line_items',
			'woocommerce_delete_refund',
			'woocommerce_json_search_products_and_variations',
			'woocommerce_get_customer_details',
		);

		if ( in_array( $action, $order_actions, true ) ) {
			return true;
		}

		// Catch-all for any order-related action.
		if ( strpos( $action, 'woocommerce_' ) === 0 && strpos( $action, 'order' ) !== false ) {
			return true;
		}

		return false;
	}

	/**
	 * Clear the internal caches.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function clear_cache(): void {
		$this->qualifying_components     = array();
		$this->qualifying_kit_builder    = array();
		$this->restricted_products_cache = array();
		$this->kit_builder_rules_cache   = array();
	}

	/**
	 * Get restricted product IDs for a coupon (composite component field).
	 *
	 * @since 1.0.0
	 * @param WC_Coupon $coupon The coupon object.
	 * @return array Array of product IDs, empty if no restrictions.
	 */
	private function get_restricted_products( WC_Coupon $coupon ): array {
		$coupon_id = $coupon->get_id();

		if ( isset( $this->restricted_products_cache[ $coupon_id ] ) ) {
			return $this->restricted_products_cache[ $coupon_id ];
		}

		$restricted = get_post_meta( $coupon_id, BC_CM_META_KEY, true );

		if ( ! is_array( $restricted ) ) {
			$restricted = array();
		} else {
			$restricted = array_map( 'absint', $restricted );
			$restricted = array_filter( $restricted );
		}

		$this->restricted_products_cache[ $coupon_id ] = $restricted;

		return $restricted;
	}

	/**
	 * Get Kit Builder rules for a coupon.
	 *
	 * @since 1.1.0
	 * @param WC_Coupon $coupon The coupon object.
	 * @return array Array of rules, each with 'key' and 'value'.
	 */
	private function get_kit_builder_rules( WC_Coupon $coupon ): array {
		$coupon_id = $coupon->get_id();

		if ( isset( $this->kit_builder_rules_cache[ $coupon_id ] ) ) {
			return $this->kit_builder_rules_cache[ $coupon_id ];
		}

		$rules = get_post_meta( $coupon_id, BC_CM_KIT_BUILDER_META_KEY, true );

		if ( ! is_array( $rules ) ) {
			$rules = array();
		}

		$this->kit_builder_rules_cache[ $coupon_id ] = $rules;

		return $rules;
	}

	/**
	 * Check if a cart item is a MyCustomizer / Kit Builder product.
	 *
	 * @since 1.1.0
	 * @param array $cart_item The cart item data.
	 * @return bool True if it's a Kit Builder product.
	 */
	private function is_kit_builder_item( array $cart_item ): bool {
		return isset( $cart_item['mczrMetas'] ) && ! empty( $cart_item['mczrMetas'] );
	}

	/**
	 * Check if a Kit Builder cart item matches all the rules.
	 *
	 * Uses partial/contains matching (case-insensitive) with AND logic between rules.
	 * Supports multiple values per rule using pipe delimiter (|) with OR logic.
	 * Example: "Hyperline|HG2" matches if property contains "Hyperline" OR "HG2".
	 *
	 * @since 1.1.0
	 * @param array $cart_item The cart item data.
	 * @param array $rules     The rules to match against.
	 * @return bool True if all rules match.
	 */
	private function kit_builder_item_matches_rules( array $cart_item, array $rules ): bool {
		if ( empty( $rules ) || ! $this->is_kit_builder_item( $cart_item ) ) {
			return false;
		}

		$properties = isset( $cart_item['mczrMetas']['summary_v2'] )
			? $cart_item['mczrMetas']['summary_v2']
			: array();

		if ( empty( $properties ) ) {
			return false;
		}

		// All rules must match (AND logic between rules).
		foreach ( $rules as $rule ) {
			$rule_key   = isset( $rule['key'] ) ? $rule['key'] : '';
			$rule_value = isset( $rule['value'] ) ? $rule['value'] : '';

			if ( empty( $rule_key ) || empty( $rule_value ) ) {
				continue;
			}

			// Support multiple values with pipe delimiter (OR logic within values).
			$rule_values = array_map( 'trim', explode( '|', $rule_value ) );
			$rule_values = array_filter( $rule_values ); // Remove empty values.

			$rule_matched = false;

			foreach ( $properties as $prop ) {
				$prop_key   = isset( $prop['key'] ) ? $prop['key'] : '';
				$prop_value = isset( $prop['value'] ) ? $prop['value'] : '';

				if ( is_array( $prop_value ) ) {
					$prop_value = implode( ', ', $prop_value );
				}

				// Check if property key matches rule key (partial, case-insensitive).
				if ( stripos( $prop_key, $rule_key ) === false ) {
					continue;
				}

				// Check if property value matches ANY of the rule values (OR logic).
				foreach ( $rule_values as $single_value ) {
					if ( stripos( $prop_value, $single_value ) !== false ) {
						$rule_matched = true;
						break 2; // Break out of both loops.
					}
				}
			}

			if ( ! $rule_matched ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get the original shop product ID from a Kit Builder cart item.
	 *
	 * @since 1.1.0
	 * @param array $cart_item The cart item data.
	 * @return int The shop product ID, or 0 if not found.
	 */
	private function get_kit_builder_shop_product_id( array $cart_item ): int {
		if ( ! $this->is_kit_builder_item( $cart_item ) ) {
			return 0;
		}

		if ( isset( $cart_item['mczrMetas']['shopProductId'] ) ) {
			return absint( $cart_item['mczrMetas']['shopProductId'] );
		}

		return 0;
	}

	/**
	 * Find qualifying Kit Builder cart items.
	 *
	 * Kit Builder items qualify if:
	 * 1. Their shopProductId matches a product in the coupon's product restrictions, AND
	 * 2. Their properties match the Kit Builder rules (if any rules are set)
	 *
	 * @since 1.1.0
	 * @param WC_Coupon $coupon The coupon object.
	 * @return array Array with 'keys' and 'product_ids' of qualifying items.
	 */
	private function find_qualifying_kit_builder( WC_Coupon $coupon ): array {
		$coupon_id = $coupon->get_id();

		// Only use cache if it has NON-EMPTY results.
		// Empty results should NOT be cached to allow re-evaluation.
		if ( isset( $this->qualifying_kit_builder[ $coupon_id ] ) && ! empty( $this->qualifying_kit_builder[ $coupon_id ]['keys'] ) ) {
			return $this->qualifying_kit_builder[ $coupon_id ];
		}

		$qualifying = array(
			'keys'        => array(),
			'product_ids' => array(),
		);

		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			$this->qualifying_kit_builder[ $coupon_id ] = $qualifying;
			return $qualifying;
		}

		// Get coupon's product restrictions (standard WooCommerce field).
		// Use 'edit' context to get raw IDs without triggering our filter.
		$coupon_product_ids = $coupon->get_product_ids( 'edit' );

		// Get Kit Builder rules.
		$rules = $this->get_kit_builder_rules( $coupon );

		// If no product restrictions and no rules, nothing to match.
		if ( empty( $coupon_product_ids ) && empty( $rules ) ) {
			$this->qualifying_kit_builder[ $coupon_id ] = $qualifying;
			return $qualifying;
		}

		$cart_items = WC()->cart->get_cart();

		foreach ( $cart_items as $cart_item_key => $cart_item ) {
			if ( ! $this->is_kit_builder_item( $cart_item ) ) {
				continue;
			}

			$shop_product_id = $this->get_kit_builder_shop_product_id( $cart_item );
			$replica_id      = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
			$variation_id    = isset( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : 0;

			// Also get the data product ID for comparison.
			$data_product_id_int = 0;
			$data_parent_id      = 0;
			if ( isset( $cart_item['data'] ) && is_a( $cart_item['data'], 'WC_Product' ) ) {
				$data_product_id_int = $cart_item['data']->get_id();
				$data_parent_id      = $cart_item['data']->get_parent_id();
			}

			// Ensure coupon product IDs are integers for comparison.
			$coupon_product_ids_int = array_map( 'absint', $coupon_product_ids );

			// Check if the original product (shopProductId) is in coupon's product restrictions.
			$product_matches = ! empty( $coupon_product_ids_int ) &&
				( in_array( $shop_product_id, $coupon_product_ids_int, true ) || in_array( $replica_id, $coupon_product_ids_int, true ) );

			// Check if Kit Builder rules match (if rules are set).
			$rules_match = empty( $rules ) || $this->kit_builder_item_matches_rules( $cart_item, $rules );

			// Qualify if: (product matches OR no product restriction) AND rules match.
			$qualifies = $rules_match && ( $product_matches || empty( $coupon_product_ids ) );

			if ( $qualifies ) {
				$qualifying['keys'][] = $cart_item_key;

				// Add all possible product IDs so WooCommerce will allow it.
				// WooCommerce uses $cart_item['data']->get_id() which might be different.
				// Also add parent IDs since WooCommerce checks both get_id() and get_parent_id().
				$ids_to_add = array();

				if ( $data_product_id_int > 0 ) {
					$ids_to_add[] = $data_product_id_int;
				}
				if ( $data_parent_id > 0 ) {
					$ids_to_add[] = $data_parent_id;
				}
				if ( $variation_id > 0 ) {
					$ids_to_add[] = $variation_id;
				}
				if ( $replica_id > 0 ) {
					$ids_to_add[] = $replica_id;
				}

				// Add all unique IDs.
				$ids_to_add = array_unique( array_filter( $ids_to_add ) );
				foreach ( $ids_to_add as $id ) {
					$qualifying['product_ids'][] = $id;
				}
			}
		}

		$this->qualifying_kit_builder[ $coupon_id ] = $qualifying;

		return $qualifying;
	}

	/**
	 * Find qualifying composite component cart items.
	 *
	 * @since 1.0.0
	 * @param WC_Coupon $coupon The coupon object.
	 * @return array Array with 'keys' and 'product_ids' of qualifying components.
	 */
	private function find_qualifying_components( WC_Coupon $coupon ): array {
		$coupon_id = $coupon->get_id();

		if ( isset( $this->qualifying_components[ $coupon_id ] ) ) {
			return $this->qualifying_components[ $coupon_id ];
		}

		$qualifying = array(
			'keys'        => array(),
			'product_ids' => array(),
		);

		$restricted = $this->get_restricted_products( $coupon );

		if ( empty( $restricted ) || ! WC()->cart || WC()->cart->is_empty() ) {
			$this->qualifying_components[ $coupon_id ] = $qualifying;
			return $qualifying;
		}

		$cart_contents = WC()->cart->get_cart();

		foreach ( $cart_contents as $cart_item_key => $cart_item ) {
			$product_id   = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
			$variation_id = isset( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : 0;

			$is_restricted = in_array( $product_id, $restricted, true )
				|| ( $variation_id > 0 && in_array( $variation_id, $restricted, true ) );

			if ( ! $is_restricted ) {
				continue;
			}

			// Check if composite child.
			if ( function_exists( 'wc_cp_is_composited_cart_item' ) && wc_cp_is_composited_cart_item( $cart_item, $cart_contents ) ) {
				$qualifying['keys'][] = $cart_item_key;

				if ( $variation_id > 0 ) {
					$qualifying['product_ids'][] = $variation_id;
				}
				$qualifying['product_ids'][] = $product_id;
			}
		}

		$this->qualifying_components[ $coupon_id ] = $qualifying;

		return $qualifying;
	}

	/**
	 * Filter coupon's product_ids to include qualifying products.
	 *
	 * Adds qualifying composite component and Kit Builder product IDs to the
	 * coupon's allowed product list. This allows WooCommerce's native validation
	 * to recognize these products.
	 *
	 * Note: This method only operates in cart context. When editing orders in admin,
	 * we return the original product_ids unchanged.
	 *
	 * @since 1.0.0
	 * @param array     $product_ids Original product IDs from coupon.
	 * @param WC_Coupon $coupon      The coupon object.
	 * @return array Modified product IDs.
	 */
	public function filter_coupon_product_ids( array $product_ids, WC_Coupon $coupon ): array {
		$coupon_id = $coupon->get_id();

		// Re-entrancy guard: prevent infinite loop if our code triggers get_product_ids().
		if ( $this->filtering_product_ids ) {
			return $product_ids;
		}

		// Clear Kit Builder cache for this coupon to ensure fresh evaluation.
		if ( isset( $this->qualifying_kit_builder[ $coupon_id ] ) ) {
			unset( $this->qualifying_kit_builder[ $coupon_id ] );
		}

		// Only process in cart context - admin order editing doesn't have cart data.
		if ( ! $this->is_cart_context() ) {
			return $product_ids;
		}

		// Set re-entrancy guard before any code that might call get_product_ids().
		$this->filtering_product_ids = true;

		try {
			// Add qualifying composite components.
			$restricted = $this->get_restricted_products( $coupon );

			if ( ! empty( $restricted ) ) {
				$qualifying_components = $this->find_qualifying_components( $coupon );

				if ( ! empty( $qualifying_components['product_ids'] ) ) {
					$product_ids = array_merge( $product_ids, $qualifying_components['product_ids'] );
				}
			}

			// Add qualifying Kit Builder products.
			$qualifying_kit_builder = $this->find_qualifying_kit_builder( $coupon );

			if ( ! empty( $qualifying_kit_builder['product_ids'] ) ) {
				$product_ids = array_merge( $product_ids, $qualifying_kit_builder['product_ids'] );
			}
		} finally {
			// Always reset the guard, even if an exception occurs.
			$this->filtering_product_ids = false;
		}

		return array_map( 'absint', array_unique( $product_ids ) );
	}

	/**
	 * Check if we are in a cart context (not admin order editing).
	 *
	 * When editing orders in WooCommerce admin, WC_Discounts is used with order items
	 * rather than cart items. In this context, WC()->cart may not exist or may not
	 * contain the relevant items. Our Kit Builder and composite restrictions only
	 * apply to cart context.
	 *
	 * @since 1.2.1
	 * @return bool True if we are in a valid cart context.
	 */
	private function is_cart_context(): bool {
		// Check if WooCommerce is available.
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			return false;
		}

		// Check if we're in the admin and editing an order.
		// When in admin order editing, we should not process Kit Builder/composite logic.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}

		// For AJAX requests in admin (like adding/removing coupons from orders),
		// check the action to determine context.
		if ( is_admin() && wp_doing_ajax() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';

			// List of admin order-related AJAX actions where cart doesn't exist.
			// Note: WooCommerce uses 'woocommerce_add_coupon_discount' for adding coupons to orders,
			// NOT 'woocommerce_add_order_coupon'.
			$order_actions = array(
				'woocommerce_add_order_item',
				'woocommerce_remove_order_item',
				'woocommerce_add_coupon_discount',  // Add coupon to order (correct action).
				'woocommerce_remove_order_coupon',  // Remove coupon from order.
				'woocommerce_calc_line_taxes',
				'woocommerce_save_order_items',
				'woocommerce_load_order_items',
				'woocommerce_add_order_fee',
				'woocommerce_add_order_shipping',
				'woocommerce_add_order_tax',
				'woocommerce_remove_order_tax',
				'woocommerce_calc_order_totals',
			);

			if ( in_array( $action, $order_actions, true ) ) {
				return false;
			}
		}

		// Check if cart exists and is valid.
		if ( ! isset( WC()->cart ) || ! WC()->cart instanceof WC_Cart ) {
			return false;
		}

		return true;
	}

	/**
	 * Inject Kit Builder items into the list of items that should receive discounts.
	 *
	 * This filter runs after WooCommerce's is_valid_for_product check has filtered
	 * items. Kit Builder items may fail that check because the replica product ID
	 * may not be recognized by WooCommerce's standard validation. By injecting them
	 * here, we allow WooCommerce's native discount calculation to apply to Kit Builder
	 * products.
	 *
	 * Kit Builder items qualify if:
	 * 1. Their shopProductId matches a product in the coupon's Products field, AND
	 * 2. (No Kit Builder rules are set OR the item's properties match all rules)
	 *
	 * This ensures discounts appear as proper coupon discounts (not fees), which:
	 * - Shows correctly in cart totals
	 * - Shows correctly in order totals
	 * - Shows correctly in admin order editing
	 * - Gets included in "Total Discounts" calculations
	 *
	 * Note: This method only operates in cart context. When editing orders in admin,
	 * we return the original items unchanged to avoid errors from missing cart data.
	 *
	 * @since 1.2.0
	 * @param array        $items_to_apply Items that will receive the coupon discount.
	 * @param WC_Coupon    $coupon         The coupon being applied.
	 * @param WC_Discounts $discounts      The WC_Discounts instance.
	 * @return array Modified items array with Kit Builder items included.
	 */
	public function inject_kit_builder_items_for_discount( array $items_to_apply, WC_Coupon $coupon, WC_Discounts $discounts ): array {
		// Only process in cart context - admin order editing doesn't have cart data.
		if ( ! $this->is_cart_context() ) {
			return $items_to_apply;
		}

		// Get qualifying Kit Builder items.
		// Items qualify if their shopProductId matches a product in the coupon's Products field,
		// AND (rules are empty OR rules match).
		$qualifying = $this->find_qualifying_kit_builder( $coupon );
		if ( empty( $qualifying['keys'] ) ) {
			return $items_to_apply;
		}

		// Get all items from the discounts object.
		$all_items = $discounts->get_items();

		// Get cart item keys that are already in the items_to_apply list.
		$existing_keys = array();
		foreach ( $items_to_apply as $item ) {
			if ( isset( $item->key ) ) {
				$existing_keys[] = $item->key;
			}
		}

		// Add qualifying Kit Builder items that aren't already in the list.
		foreach ( $qualifying['keys'] as $cart_item_key ) {
			// Skip if already in the list.
			if ( in_array( $cart_item_key, $existing_keys, true ) ) {
				continue;
			}

			// Find the item in all_items.
			if ( isset( $all_items[ $cart_item_key ] ) ) {
				// Clone to avoid modifying the original.
				$items_to_apply[] = clone $all_items[ $cart_item_key ];
			}
		}

		return $items_to_apply;
	}

	/**
	 * Validate if the coupon can be applied to the cart.
	 *
	 * Note: This method only operates in cart context. When editing orders in admin,
	 * we return the original validity unchanged.
	 *
	 * @since 1.0.0
	 * @param bool         $valid     Whether the coupon is valid.
	 * @param WC_Coupon    $coupon    The coupon object.
	 * @param WC_Discounts $discounts The discounts object.
	 * @return bool Whether the coupon is valid.
	 */
	public function validate_coupon( bool $valid, WC_Coupon $coupon, WC_Discounts $discounts ): bool {
		// Only process in cart context - admin order editing doesn't have cart data.
		if ( ! $this->is_cart_context() ) {
			return $valid;
		}

		if ( ! $valid ) {
			// Check if we have qualifying Kit Builder items that should make this valid.
			// This handles cases where the filter might not have caught the Kit Builder items
			// (e.g., when Kit Builder is the only item in the cart).
			$qualifying = $this->find_qualifying_kit_builder( $coupon );

			if ( ! empty( $qualifying['keys'] ) ) {
				return true; // Force valid if we have qualifying Kit Builder items.
			}

			// Also check for qualifying composite components.
			$qualifying_components = $this->find_qualifying_components( $coupon );

			if ( ! empty( $qualifying_components['keys'] ) ) {
				return true; // Force valid if we have qualifying composite components.
			}
		}

		return $valid;
	}

	/**
	 * Validate if the coupon is valid for a specific product.
	 *
	 * This is a critical filter for Kit Builder products. WooCommerce's
	 * validate_coupon_excluded_items() calls is_valid_for_product() which
	 * passes $item->object (cart item data) as $values. Even after our
	 * fix_kit_builder_items_for_validation swaps $item->product, the $values
	 * still contain the Kit Builder mczrMetas data.
	 *
	 * This filter must handle Kit Builder items even when no rules are configured,
	 * because the default WooCommerce validation will fail for replica products.
	 *
	 * Note: This method only operates in cart context. When editing orders in admin,
	 * we return the original validity unchanged.
	 *
	 * @since 1.0.0
	 * @param bool       $valid   Whether the coupon is valid for this product.
	 * @param WC_Product $product The product object.
	 * @param WC_Coupon  $coupon  The coupon object.
	 * @param array      $values  Cart item data.
	 * @return bool Whether the coupon is valid for this product.
	 */
	public function validate_coupon_for_product( bool $valid, WC_Product $product, WC_Coupon $coupon, array $values ): bool {
		// Only process in cart context - admin order editing doesn't have cart data.
		if ( ! $this->is_cart_context() ) {
			return $valid;
		}

		$restricted = $this->get_restricted_products( $coupon );
		$kit_rules  = $this->get_kit_builder_rules( $coupon );
		$is_kit     = $this->is_kit_builder_item( $values );

		// Handle Kit Builder items - this is critical for validation to pass.
		// Even when no rules are configured, we need to validate Kit Builder items
		// based on their shopProductId matching the coupon's Products field.
		if ( $is_kit ) {
			$shop_product_id = $this->get_kit_builder_shop_product_id( $values );

			// Get coupon's product restrictions (use 'edit' to avoid triggering our filter).
			$coupon_product_ids = $coupon->get_product_ids( 'edit' );

			// If coupon has product restrictions, check if shopProductId matches.
			if ( ! empty( $coupon_product_ids ) ) {
				$coupon_product_ids_int = array_map( 'absint', $coupon_product_ids );
				$product_matches        = in_array( $shop_product_id, $coupon_product_ids_int, true );

				// If Kit Builder rules are set, also check if they match.
				if ( ! empty( $kit_rules ) ) {
					$rules_match = $this->kit_builder_item_matches_rules( $values, $kit_rules );
					// Product must match AND rules must match (if rules exist).
					return $product_matches && $rules_match;
				}

				// No rules configured - just check product match.
				return $product_matches;
			}

			// No product restrictions on coupon - check rules only if they exist.
			if ( ! empty( $kit_rules ) ) {
				return $this->kit_builder_item_matches_rules( $values, $kit_rules );
			}

			// No restrictions and no rules - allow all Kit Builder items.
			return true;
		}

		// Non-Kit Builder items: No custom restrictions - use default behavior.
		if ( empty( $restricted ) && empty( $kit_rules ) ) {
			return $valid;
		}

		// Handle composite products.
		if ( ! empty( $restricted ) ) {
			$product_id   = $product->get_id();
			$parent_id    = $product->get_parent_id();
			$variation_id = $product->is_type( 'variation' ) ? $product_id : 0;

			$is_in_our_restrictions = in_array( $product_id, $restricted, true )
				|| ( $parent_id && in_array( $parent_id, $restricted, true ) )
				|| ( $variation_id && in_array( $variation_id, $restricted, true ) );

			// Composite container - no discount (has $0 price).
			if ( function_exists( 'wc_cp_is_composite_container_cart_item' ) && wc_cp_is_composite_container_cart_item( $values ) ) {
				return false;
			}

			// Product in our composite restrictions.
			if ( $is_in_our_restrictions ) {
				// Check if it's a composite child.
				if ( function_exists( 'wc_cp_is_composited_cart_item' ) && ! empty( $values ) ) {
					// Use is_cart_context check already passed, so WC()->cart should be valid.
					$cart_contents = WC()->cart->get_cart();
					if ( wc_cp_is_composited_cart_item( $values, $cart_contents ) ) {
						return true; // Valid as composite component.
					}
				}
				return false; // Standalone - not valid.
			}
		}

		return $valid;
	}

	/**
	 * Fix Kit Builder items for WooCommerce coupon validation.
	 *
	 * This filter runs INSIDE validate_coupon_product_ids() BEFORE any exception is thrown.
	 * Kit Builder creates "replica" products with different IDs than the original product.
	 * WooCommerce's validation checks if $item->product->get_id() is in $coupon->get_product_ids().
	 * For Kit Builder items, we swap the replica product with the original shop product so the
	 * validation will find a match.
	 *
	 * This is the ONLY way to prevent the "coupon not valid with certain products" error
	 * because the woocommerce_coupon_error filter cannot actually suppress errors - it only
	 * modifies the message, but WP_Error is still returned.
	 *
	 * @since 1.3.5
	 * @param array        $items     Items to validate.
	 * @param WC_Discounts $discounts The discounts object.
	 * @return array Modified items with Kit Builder products swapped.
	 */
	public function fix_kit_builder_items_for_validation( array $items, WC_Discounts $discounts ): array {
		foreach ( $items as $key => $item ) {
			// Check if this is a Kit Builder item.
			if ( ! isset( $item->object ) || ! is_array( $item->object ) ) {
				continue;
			}

			if ( ! $this->is_kit_builder_item( $item->object ) ) {
				continue;
			}

			$shop_product_id = $this->get_kit_builder_shop_product_id( $item->object );

			if ( $shop_product_id <= 0 ) {
				continue;
			}

			// Get the original shop product.
			$shop_product = wc_get_product( $shop_product_id );

			if ( ! $shop_product ) {
				continue;
			}

			// Clone the item and swap the product with the original shop product.
			// This way WooCommerce's validation will see the original product ID
			// and match it against the coupon's product_ids list.
			$items[ $key ]          = clone $item;
			$items[ $key ]->product = $shop_product;
		}

		return $items;
	}
}
