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
		// Add custom fields to Usage Restriction tab.
		add_action( 'woocommerce_coupon_options_usage_restriction', array( $this, 'add_composite_component_field' ), 10, 2 );
		add_action( 'woocommerce_coupon_options_usage_restriction', array( $this, 'add_kit_builder_rules_field' ), 11, 2 );

		// Save custom field data.
		add_action( 'woocommerce_coupon_options_save', array( $this, 'save_composite_component_field' ), 10, 2 );
		add_action( 'woocommerce_coupon_options_save', array( $this, 'save_kit_builder_rules_field' ), 10, 2 );

		// Enqueue admin scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Enqueue admin scripts for the coupon edit page.
	 *
	 * @since 1.1.0
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_scripts( string $hook ): void {
		global $post;

		// Only load on coupon edit page.
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		if ( ! $post || 'shop_coupon' !== $post->post_type ) {
			return;
		}

		// Inline script for Kit Builder rules functionality.
		wp_add_inline_script( 'jquery', $this->get_kit_builder_inline_script() );
	}

	/**
	 * Get inline JavaScript for Kit Builder rules management.
	 *
	 * @since 1.1.0
	 * @return string JavaScript code.
	 */
	private function get_kit_builder_inline_script(): string {
		return "
		jQuery(document).ready(function($) {
			// Add new rule row.
			$('#bc_cm_add_kit_builder_rule').on('click', function(e) {
				e.preventDefault();
				var index = $('.bc_cm_kit_builder_rule_row').length;
				var newRow = '<div class=\"bc_cm_kit_builder_rule_row\" style=\"margin-bottom: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd;\">' +
					'<input type=\"text\" name=\"bc_cm_kit_builder_rules[' + index + '][key]\" placeholder=\"Property Name (e.g., Armor Type)\" style=\"width: 45%; margin-right: 5px;\" />' +
					'<input type=\"text\" name=\"bc_cm_kit_builder_rules[' + index + '][value]\" placeholder=\"Value (e.g., Hyperline|HG2)\" style=\"width: 45%; margin-right: 5px;\" />' +
					'<button type=\"button\" class=\"button bc_cm_remove_rule\" style=\"color: #a00;\">&times;</button>' +
				'</div>';
				$('#bc_cm_kit_builder_rules_container').append(newRow);
			});

			// Remove rule row.
			$(document).on('click', '.bc_cm_remove_rule', function(e) {
				e.preventDefault();
				$(this).closest('.bc_cm_kit_builder_rule_row').remove();
			});
		});
		";
	}

	/**
	 * Add the composite component products field to the Usage Restriction tab.
	 *
	 * Uses WooCommerce's built-in product search functionality for a consistent UX.
	 * The field allows selecting multiple products that must be present as
	 * composite components for the coupon to apply.
	 *
	 * @since 1.0.0
	 * @param int       $coupon_id The coupon post ID.
	 * @param WC_Coupon $coupon    The coupon object.
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
						'Select products that must be present as composite product components (not standalone) for this coupon to apply. The discount will be applied to the component product. If any of these products are added to the cart as standalone items, the coupon will not apply to them.',
						'bc_cm_'
					)
				);
				?>
			</p>
			<p class="form-field bc_cm_composite_help_link" style="margin-left: 150px; margin-top: -5px;">
				<a href="<?php echo esc_url( BC_CM_Documentation::get_documentation_url( 'composite-products' ) ); ?>" target="_blank" style="font-size: 12px; text-decoration: none;">
					<span class="dashicons dashicons-editor-help" style="font-size: 14px; vertical-align: text-bottom;"></span>
					<?php esc_html_e( 'How does this work?', 'bc_cm_' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Add the Kit Builder rules field to the Usage Restriction tab.
	 *
	 * Allows admins to define property name/value rules that must match
	 * for MyCustomizer/Kit Builder products.
	 *
	 * @since 1.1.0
	 * @param int       $coupon_id The coupon post ID.
	 * @param WC_Coupon $coupon    The coupon object.
	 * @return void
	 */
	public function add_kit_builder_rules_field( int $coupon_id, WC_Coupon $coupon ): void {
		// Get saved rules from coupon meta.
		$saved_rules = get_post_meta( $coupon_id, BC_CM_KIT_BUILDER_META_KEY, true );

		// Ensure it's an array.
		if ( ! is_array( $saved_rules ) ) {
			$saved_rules = array();
		}
		?>
		<div class="options_group">
			<p class="form-field">
				<label><?php esc_html_e( 'Kit Builder property rules', 'bc_cm_' ); ?></label>
				<span class="description" style="display: block; margin-bottom: 10px;">
					<?php esc_html_e( 'Define property rules for Kit Builder / MyCustomizer products. All rules must match (AND logic). Use | to specify multiple values (OR logic within a rule).', 'bc_cm_' ); ?>
				</span>
			</p>

			<div id="bc_cm_kit_builder_rules_container" style="margin-left: 150px; margin-bottom: 15px;">
				<?php if ( ! empty( $saved_rules ) ) : ?>
					<?php foreach ( $saved_rules as $index => $rule ) : ?>
						<div class="bc_cm_kit_builder_rule_row" style="margin-bottom: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd;">
							<input
								type="text"
								name="bc_cm_kit_builder_rules[<?php echo esc_attr( $index ); ?>][key]"
								value="<?php echo esc_attr( $rule['key'] ?? '' ); ?>"
								placeholder="<?php esc_attr_e( 'Property Name (e.g., Armor Type)', 'bc_cm_' ); ?>"
								style="width: 45%; margin-right: 5px;"
							/>
							<input
								type="text"
								name="bc_cm_kit_builder_rules[<?php echo esc_attr( $index ); ?>][value]"
								value="<?php echo esc_attr( $rule['value'] ?? '' ); ?>"
								placeholder="<?php esc_attr_e( 'Value (e.g., Hyperline|HG2)', 'bc_cm_' ); ?>"
								style="width: 45%; margin-right: 5px;"
							/>
							<button type="button" class="button bc_cm_remove_rule" style="color: #a00;">&times;</button>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<p class="form-field" style="margin-left: 150px;">
				<button type="button" id="bc_cm_add_kit_builder_rule" class="button">
					<?php esc_html_e( '+ Add Rule', 'bc_cm_' ); ?>
				</button>
				<?php
				echo wc_help_tip(
					__(
						'Add property rules that must match for Kit Builder / MyCustomizer products. For example, add "Armor Type" as Property Name and "Hyperline" as Property Value. Use | to allow multiple values (e.g., "Hyperline|HG2" matches either). All rules must match (AND logic), but multiple values within a rule use OR logic.',
						'bc_cm_'
					)
				);
				?>
			</p>
			<p class="form-field bc_cm_kit_builder_help_link" style="margin-left: 150px; margin-top: 5px;">
				<a href="<?php echo esc_url( BC_CM_Documentation::get_documentation_url( 'kit-builder' ) ); ?>" target="_blank" style="font-size: 12px; text-decoration: none;">
					<span class="dashicons dashicons-editor-help" style="font-size: 14px; vertical-align: text-bottom;"></span>
					<?php esc_html_e( 'How does this work?', 'bc_cm_' ); ?>
				</a>
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
	 * @param int       $coupon_id The coupon post ID.
	 * @param WC_Coupon $coupon    The coupon object.
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

	/**
	 * Save the Kit Builder rules field data.
	 *
	 * Performs security checks (nonce, capability) and sanitizes input
	 * before saving to post meta.
	 *
	 * @since 1.1.0
	 * @param int       $coupon_id The coupon post ID.
	 * @param WC_Coupon $coupon    The coupon object.
	 * @return void
	 */
	public function save_kit_builder_rules_field( int $coupon_id, WC_Coupon $coupon ): void {
		// Security: Verify nonce to prevent CSRF attacks.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( wp_unslash( $_POST[ self::NONCE_NAME ] ), self::NONCE_ACTION ) ) {
			return;
		}

		// Security: Verify user has permission to edit coupons.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Get and sanitize the submitted rules.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$rules = isset( $_POST['bc_cm_kit_builder_rules'] )
			? wp_unslash( $_POST['bc_cm_kit_builder_rules'] )
			: array();

		$sanitized_rules = array();

		if ( is_array( $rules ) ) {
			foreach ( $rules as $rule ) {
				// Sanitize key and value as text fields.
				$key   = isset( $rule['key'] ) ? sanitize_text_field( $rule['key'] ) : '';
				$value = isset( $rule['value'] ) ? sanitize_text_field( $rule['value'] ) : '';

				// Only save rules that have both key and value.
				if ( ! empty( $key ) && ! empty( $value ) ) {
					$sanitized_rules[] = array(
						'key'   => $key,
						'value' => $value,
					);
				}
			}
		}

		// Save to post meta.
		// If empty array, delete the meta to keep database clean.
		if ( empty( $sanitized_rules ) ) {
			delete_post_meta( $coupon_id, BC_CM_KIT_BUILDER_META_KEY );
		} else {
			update_post_meta( $coupon_id, BC_CM_KIT_BUILDER_META_KEY, $sanitized_rules );
		}
	}
}
