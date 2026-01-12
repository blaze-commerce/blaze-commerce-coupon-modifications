<?php
/**
 * Coupon Validator class for Blaze Commerce Coupon Modifications.
 *
 * Handles the logic for determining if a coupon with composite component
 * restrictions should be applied to products in the cart.
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
 * Validates coupon applicability based on composite component product restrictions.
 * The coupon applies to composite component products when they are part of a
 * composite (not standalone). Standard WooCommerce product restrictions still apply
 * for products not in the composite component list.
 *
 * @since 1.0.0
 */
class BC_CM_Coupon_Validator {

	/**
	 * Cache of qualifying component cart item keys.
	 *
	 * Stores cart item keys of composite components that match the restricted
	 * product list. Keyed by coupon ID.
	 *
	 * @var array
	 */
	private $qualifying_components = array();

	/**
	 * Cache of restricted product IDs keyed by coupon ID.
	 *
	 * @var array
	 */
	private $restricted_products_cache = array();

	/**
	 * Constructor. Registers validation hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Filter coupon's product_ids to include qualifying components.
		add_filter( 'woocommerce_coupon_get_product_ids', array( $this, 'filter_coupon_product_ids' ), 10, 2 );

		// Main coupon validity check - determines if coupon can be used at all.
		add_filter( 'woocommerce_coupon_is_valid', array( $this, 'validate_coupon' ), 10, 3 );

		// Per-product validity check - determines which products get the discount.
		add_filter( 'woocommerce_coupon_is_valid_for_product', array( $this, 'validate_coupon_for_product' ), 10, 4 );

		// Clear cache when cart is updated.
		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'clear_cache' ) );
		add_action( 'woocommerce_add_to_cart', array( $this, 'clear_cache' ) );
		add_action( 'woocommerce_cart_item_removed', array( $this, 'clear_cache' ) );
		add_action( 'woocommerce_cart_item_restored', array( $this, 'clear_cache' ) );
	}

	/**
	 * Clear the internal caches.
	 *
	 * Called when cart contents change to ensure fresh validation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function clear_cache(): void {
		$this->qualifying_components     = array();
		$this->restricted_products_cache = array();
	}

	/**
	 * Get restricted product IDs for a coupon (our custom field).
	 *
	 * Retrieves and caches the composite component product restrictions
	 * from coupon meta.
	 *
	 * @since 1.0.0
	 * @param WC_Coupon $coupon The coupon object.
	 * @return array Array of product IDs, empty if no restrictions.
	 */
	private function get_restricted_products( WC_Coupon $coupon ): array {
		$coupon_id = $coupon->get_id();

		// Return from cache if available.
		if ( isset( $this->restricted_products_cache[ $coupon_id ] ) ) {
			return $this->restricted_products_cache[ $coupon_id ];
		}

		// Get from meta.
		$restricted = get_post_meta( $coupon_id, BC_CM_META_KEY, true );

		// Ensure it's an array of integers.
		if ( ! is_array( $restricted ) ) {
			$restricted = array();
		} else {
			$restricted = array_map( 'absint', $restricted );
			$restricted = array_filter( $restricted );
		}

		// Cache for subsequent calls.
		$this->restricted_products_cache[ $coupon_id ] = $restricted;

		return $restricted;
	}

	/**
	 * Build the list of qualifying composite component cart items.
	 *
	 * Scans the cart to find component products that:
	 * 1. Match the restricted product list
	 * 2. Are part of a composite (not standalone)
	 *
	 * @since 1.0.0
	 * @param WC_Coupon $coupon The coupon object.
	 * @return array Array with 'keys' (cart item keys) and 'product_ids' of qualifying components.
	 */
	private function find_qualifying_components( WC_Coupon $coupon ): array {
		$coupon_id = $coupon->get_id();

		// Return from cache if already calculated.
		if ( isset( $this->qualifying_components[ $coupon_id ] ) ) {
			return $this->qualifying_components[ $coupon_id ];
		}

		$qualifying = array(
			'keys'        => array(),
			'product_ids' => array(),
		);

		$restricted = $this->get_restricted_products( $coupon );

		// If no restrictions, no special handling needed.
		if ( empty( $restricted ) ) {
			$this->qualifying_components[ $coupon_id ] = $qualifying;
			return $qualifying;
		}

		// Get cart contents.
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			$this->qualifying_components[ $coupon_id ] = $qualifying;
			return $qualifying;
		}

		$cart_contents = WC()->cart->get_cart();

		// Scan for restricted products that are composite children (components).
		foreach ( $cart_contents as $cart_item_key => $cart_item ) {
			// Get product ID and variation ID.
			$product_id   = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
			$variation_id = isset( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : 0;

			// Check if this product matches our restricted list.
			// Match by product_id (parent) OR variation_id (specific variation).
			$is_restricted_product = in_array( $product_id, $restricted, true )
				|| ( $variation_id > 0 && in_array( $variation_id, $restricted, true ) );

			if ( ! $is_restricted_product ) {
				continue;
			}

			// Check if this is a composite child (component) using Composite Products helper.
			// Only components that are part of a composite qualify for the discount.
			if ( function_exists( 'wc_cp_is_composited_cart_item' ) && wc_cp_is_composited_cart_item( $cart_item, $cart_contents ) ) {
				// This is a composite component that matches our restrictions.
				// Add to qualifying list.
				if ( ! in_array( $cart_item_key, $qualifying['keys'], true ) ) {
					$qualifying['keys'][] = $cart_item_key;
				}

				// Store the actual product ID in cart (could be variation).
				$actual_product_id = $variation_id > 0 ? $variation_id : $product_id;
				if ( ! in_array( $actual_product_id, $qualifying['product_ids'], true ) ) {
					$qualifying['product_ids'][] = $actual_product_id;
				}

				// Also store parent product ID for matching.
				if ( ! in_array( $product_id, $qualifying['product_ids'], true ) ) {
					$qualifying['product_ids'][] = $product_id;
				}
			}
			// Note: If the restricted product is standalone (not a composite child),
			// we don't add it to qualifying. The coupon won't apply to standalone items.
		}

		// Cache the results.
		$this->qualifying_components[ $coupon_id ] = $qualifying;

		return $qualifying;
	}

	/**
	 * Check if a product matches our composite component restrictions.
	 *
	 * @since 1.0.0
	 * @param int       $product_id   The product ID.
	 * @param int       $parent_id    The parent product ID (for variations).
	 * @param int       $variation_id The variation ID.
	 * @param WC_Coupon $coupon       The coupon object.
	 * @return bool True if product is in our composite component restrictions.
	 */
	private function is_in_composite_restrictions( int $product_id, int $parent_id, int $variation_id, WC_Coupon $coupon ): bool {
		$restricted = $this->get_restricted_products( $coupon );

		if ( empty( $restricted ) ) {
			return false;
		}

		return in_array( $product_id, $restricted, true )
			|| ( $parent_id && in_array( $parent_id, $restricted, true ) )
			|| ( $variation_id && in_array( $variation_id, $restricted, true ) );
	}

	/**
	 * Filter coupon's product_ids to include qualifying components.
	 *
	 * This dynamically adds qualifying component product IDs to the
	 * coupon's allowed products list, ensuring WooCommerce's built-in
	 * validation accepts them.
	 *
	 * @since 1.0.0
	 * @param array     $product_ids Original product IDs from coupon.
	 * @param WC_Coupon $coupon      The coupon object.
	 * @return array Modified product IDs.
	 */
	public function filter_coupon_product_ids( array $product_ids, WC_Coupon $coupon ): array {
		$restricted = $this->get_restricted_products( $coupon );

		// No composite component restrictions - return original.
		if ( empty( $restricted ) ) {
			return $product_ids;
		}

		// Find qualifying components.
		$qualifying = $this->find_qualifying_components( $coupon );

		// Add qualifying component product IDs to the allowed list.
		if ( ! empty( $qualifying['product_ids'] ) ) {
			$product_ids = array_merge( $product_ids, $qualifying['product_ids'] );
			$product_ids = array_unique( $product_ids );
		}

		return $product_ids;
	}

	/**
	 * Validate if the coupon can be applied to the cart.
	 *
	 * For coupons with composite component restrictions, this checks if
	 * at least one qualifying component OR one standard product exists in cart.
	 *
	 * @since 1.0.0
	 * @param bool         $valid     Whether the coupon is valid.
	 * @param WC_Coupon    $coupon    The coupon object.
	 * @param WC_Discounts $discounts The discounts object.
	 * @return bool Whether the coupon is valid.
	 */
	public function validate_coupon( bool $valid, WC_Coupon $coupon, WC_Discounts $discounts ): bool {
		// If already invalid, don't process further.
		if ( ! $valid ) {
			return $valid;
		}

		$restricted = $this->get_restricted_products( $coupon );

		// No composite component restrictions - let WooCommerce handle it.
		if ( empty( $restricted ) ) {
			return $valid;
		}

		// Get standard product_ids from coupon (this calls the filtered version).
		// We need the original, unfiltered value to check if there are standard restrictions.
		$standard_product_ids = get_post_meta( $coupon->get_id(), 'product_ids', true );
		if ( ! empty( $standard_product_ids ) && is_string( $standard_product_ids ) ) {
			$standard_product_ids = array_filter( array_map( 'absint', explode( ',', $standard_product_ids ) ) );
		} elseif ( ! is_array( $standard_product_ids ) ) {
			$standard_product_ids = array();
		}

		// Find qualifying composite components.
		$qualifying = $this->find_qualifying_components( $coupon );

		// If we have composite component restrictions but no qualifying components found,
		// check if there are standard product restrictions that might still apply.
		if ( empty( $qualifying['keys'] ) ) {
			// If there are no standard product restrictions either, the coupon requires
			// composite components that aren't in the cart.
			if ( empty( $standard_product_ids ) ) {
				throw new Exception(
					esc_html__(
						'This coupon requires specific products to be selected as composite components.',
						'bc_cm_'
					),
					WC_Coupon::E_WC_COUPON_NOT_APPLICABLE
				);
			}
			// If there ARE standard product restrictions, let WooCommerce validate those.
			// Don't throw an error - the standard products might be in the cart.
		}

		return $valid;
	}

	/**
	 * Validate if the coupon is valid for a specific product.
	 *
	 * Determines whether the coupon discount should be applied to
	 * a particular product in the cart.
	 *
	 * Logic:
	 * - Products in our composite restrictions AND are composite components: ALLOW
	 * - Products in our composite restrictions AND are standalone: DENY
	 * - Products NOT in our composite restrictions: Use default WooCommerce behavior
	 * - Composite parents: DENY (they have $0 price with component pricing)
	 *
	 * @since 1.0.0
	 * @param bool       $valid   Whether the coupon is valid for this product.
	 * @param WC_Product $product The product object.
	 * @param WC_Coupon  $coupon  The coupon object.
	 * @param array      $values  Cart item data.
	 * @return bool Whether the coupon is valid for this product.
	 */
	public function validate_coupon_for_product( bool $valid, WC_Product $product, WC_Coupon $coupon, array $values ): bool {
		$restricted = $this->get_restricted_products( $coupon );

		// No composite component restrictions - use default behavior entirely.
		if ( empty( $restricted ) ) {
			return $valid;
		}

		// Get product IDs.
		$product_id   = $product->get_id();
		$parent_id    = $product->get_parent_id();
		$variation_id = $product->is_type( 'variation' ) ? $product_id : 0;

		// Get cart item key.
		$cart_item_key = isset( $values['key'] ) ? $values['key'] : '';

		// Check if this product is in our composite component restrictions.
		$is_in_our_restrictions = $this->is_in_composite_restrictions( $product_id, $parent_id, $variation_id, $coupon );

		// Check if this cart item is a composite container (parent).
		// Composite parents have $0 price with component pricing, so skip them.
		$is_composite_container = false;
		if ( function_exists( 'wc_cp_is_composite_container_cart_item' ) && ! empty( $values ) ) {
			$is_composite_container = wc_cp_is_composite_container_cart_item( $values );
		}

		if ( $is_composite_container ) {
			// Don't apply discount to composite parent (it has $0 price).
			return false;
		}

		// Check if this is a composite child (component).
		$is_composite_child = false;
		if ( function_exists( 'wc_cp_is_composited_cart_item' ) && ! empty( $values ) ) {
			$cart_contents      = WC()->cart ? WC()->cart->get_cart() : array();
			$is_composite_child = wc_cp_is_composited_cart_item( $values, $cart_contents );
		}

		// If product is in our composite component restrictions...
		if ( $is_in_our_restrictions ) {
			if ( $is_composite_child ) {
				// It's a composite component - check if it qualifies.
				$qualifying = $this->find_qualifying_components( $coupon );

				// Check by cart item key first.
				if ( $cart_item_key && in_array( $cart_item_key, $qualifying['keys'], true ) ) {
					return true;
				}

				// Check by product ID as fallback.
				$is_qualifying = in_array( $product_id, $qualifying['product_ids'], true )
					|| ( $parent_id && in_array( $parent_id, $qualifying['product_ids'], true ) )
					|| ( $variation_id && in_array( $variation_id, $qualifying['product_ids'], true ) );

				return $is_qualifying;
			} else {
				// It's standalone - our restrictions say NO discount for standalone.
				return false;
			}
		}

		// Product is NOT in our composite component restrictions.
		// Let WooCommerce's default product_ids validation handle it.
		// This respects the standard "Products" field in coupon settings.
		return $valid;
	}
}
