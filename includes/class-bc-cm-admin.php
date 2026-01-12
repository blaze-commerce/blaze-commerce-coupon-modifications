<?php
/**
 * Admin class for Blaze Commerce Coupon Modifications.
 *
 * Handles adding custom fields to the WooCommerce coupon edit screen
 * in the Usage Restriction tab.
 *
 * @package Blaze_Commerce_Coupon_Modifications
 * @since 1.0.0
 */

// Security: Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class for coupon meta box fields.
 *
 * @since 1.0.0
 */
class BC_CM_Admin {

	/**
	 * Nonce action name for form security.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'bc_cm_save_coupon_data';

	/**
	 * Nonce field name.
	 *
	 * @var string
	 */
	const NONCE_NAME = 'bc_cm_coupon_nonce';

	/**
	 * Constructor. Registers admin hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Add custom field to Usage Restriction tab.
		add_action( 'woocommerce_coupon_options_usage_restriction', array( $this, 'add_composite_component_field' ), 10, 2 );

		// Save custom field data.
		add_action( 'woocommerce_coupon_options_save', array( $this, 'save_composite_component_field' ), 10, 2 );
	}

	/**
	 * Add the composite component products field to the Usage Restriction tab.
	 *
	 * Uses WooCommerce's built-in product search functionality for a consistent UX.
	 * The field allows selecting multiple products that must be present as
	 * composite components for the coupon to apply.
	 *
	 * @since 1.0.0
	 * @param int      $coupon_id The coupon post ID.
	 * @param WC_Coupon $coupon   The coupon object.
	 * @return void
	 */
	public function add_composite_component_field( int $coupon_id, WC_Coupon $coupon ): void {
		// Get saved product IDs from coupon meta.
		$selected_products = get_post_meta( $coupon_id, BC_CM_META_KEY, true );

		// Ensure it's an array.
		if ( ! is_array( $selected_products ) ) {
			$selected_products = array();
		}

		// Output nonce field for security verification on save.
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<div class="options_group">
			<p class="form-field bc_cm_composite_component_products_field">
				<label for="bc_cm_composite_component_products">
					<?php esc_html_e( 'Composite component products', 'bc_cm_' ); ?>
				</label>
				<select
					class="wc-product-search"
					multiple="multiple"
					style="width: 50%;"
					id="bc_cm_composite_component_products"
					name="bc_cm_composite_component_products[]"
					data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'bc_cm_' ); ?>"
					data-action="woocommerce_json_search_products_and_variations"
					data-allow_clear="true"
				>
					<?php
					// Pre-populate selected products.
					foreach ( $selected_products as $product_id ) {
						$product = wc_get_product( $product_id );
						if ( is_object( $product ) ) {
							printf(
								'<option value="%s" selected="selected">%s</option>',
								esc_attr( $product_id ),
								esc_html( wp_strip_all_tags( $product->get_formatted_name() ) )
							);
						}
					}
					?>
				</select>
				<?php
				// Tooltip icon with help text.
				echo wc_help_tip(
					__(
						'Select products that must be present as composite product components (not standalone) for this coupon to apply. The discount will be applied to the composite parent product. If any of these products are added to the cart as standalone items, the coupon will not apply to them.',
						'bc_cm_'
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Save the composite component products field data.
	 *
	 * Performs security checks (nonce, capability) and sanitizes input
	 * before saving to post meta.
	 *
	 * @since 1.0.0
	 * @param int      $coupon_id The coupon post ID.
	 * @param WC_Coupon $coupon   The coupon object.
	 * @return void
	 */
	public function save_composite_component_field( int $coupon_id, WC_Coupon $coupon ): void {
		// Security: Verify nonce to prevent CSRF attacks.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( wp_unslash( $_POST[ self::NONCE_NAME ] ), self::NONCE_ACTION ) ) {
			return;
		}

		// Security: Verify user has permission to edit coupons.
		// Using manage_woocommerce as it's the standard capability for coupon management.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Get and sanitize the submitted product IDs.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$product_ids = isset( $_POST['bc_cm_composite_component_products'] )
			? wp_unslash( $_POST['bc_cm_composite_component_products'] )
			: array();

		// Sanitize: Ensure all values are positive integers.
		// absint() returns 0 for non-numeric/negative values, array_filter removes those.
		$sanitized_ids = array_filter( array_map( 'absint', (array) $product_ids ) );

		// Remove duplicate IDs.
		$sanitized_ids = array_unique( $sanitized_ids );

		// Re-index array for clean storage.
		$sanitized_ids = array_values( $sanitized_ids );

		// Save to post meta.
		// If empty array, delete the meta to keep database clean.
		if ( empty( $sanitized_ids ) ) {
			delete_post_meta( $coupon_id, BC_CM_META_KEY );
		} else {
			update_post_meta( $coupon_id, BC_CM_META_KEY, $sanitized_ids );
		}
	}
}
