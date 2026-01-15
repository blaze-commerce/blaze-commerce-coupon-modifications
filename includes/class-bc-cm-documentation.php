<?php
/**
 * Documentation page class for Blaze Commerce Coupon Modifications.
 *
 * Handles the admin documentation page that provides comprehensive
 * instructions on how to use the plugin features.
 *
 * @package Blaze_Commerce_Coupon_Modifications
 * @since 1.3.0
 */

// Security: Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Documentation page class.
 *
 * @since 1.3.0
 */
class BC_CM_Documentation {

	/**
	 * Documentation page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'bc-cm-documentation';

	/**
	 * Constructor. Registers admin hooks.
	 *
	 * @since 1.3.0
	 */
	public function __construct() {
		// Register admin menu page.
		add_action( 'admin_menu', array( $this, 'register_documentation_page' ) );

		// Enqueue admin styles for documentation page.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_documentation_styles' ) );
	}

	/**
	 * Get the documentation page URL.
	 *
	 * @since 1.3.0
	 * @param string $section Optional section anchor to jump to.
	 * @return string The documentation page URL.
	 */
	public static function get_documentation_url( string $section = '' ): string {
		$url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );

		if ( ! empty( $section ) ) {
			$url .= '#' . $section;
		}

		return $url;
	}

	/**
	 * Register the documentation page as a hidden admin page.
	 *
	 * Uses null as parent slug to create a page that is accessible via URL
	 * but does not appear in the admin menu. The page is accessible at:
	 * wp-admin/admin.php?page=bc-cm-documentation
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public function register_documentation_page(): void {
		add_submenu_page(
			null, // Hidden page - no menu item.
			__( 'Coupon Modifications Documentation', 'bc_cm_' ),
			__( 'Coupon Mods Docs', 'bc_cm_' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_documentation_page' )
		);
	}

	/**
	 * Enqueue styles for the documentation page.
	 *
	 * @since 1.3.0
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_documentation_styles( string $hook ): void {
		// Only load on our documentation page.
		// Hidden pages (null parent) use 'admin_page_' prefix instead of parent-based hook.
		if ( 'admin_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		// Inline styles for documentation page.
		wp_add_inline_style( 'wp-admin', $this->get_documentation_styles() );
	}

	/**
	 * Get inline CSS styles for the documentation page.
	 *
	 * @since 1.3.0
	 * @return string CSS styles.
	 */
	private function get_documentation_styles(): string {
		return '
			.bc-cm-docs-wrap {
				max-width: 1200px;
				margin: 20px 20px 20px 0;
			}
			.bc-cm-docs-header {
				background: #fff;
				border: 1px solid #c3c4c7;
				border-left: 4px solid #2271b1;
				padding: 20px 25px;
				margin-bottom: 20px;
			}
			.bc-cm-docs-header h1 {
				margin: 0 0 10px 0;
				font-size: 24px;
				font-weight: 600;
				color: #1d2327;
			}
			.bc-cm-docs-header p {
				margin: 0;
				font-size: 14px;
				color: #50575e;
			}
			.bc-cm-docs-card {
				background: #fff;
				border: 1px solid #c3c4c7;
				padding: 0;
				margin-bottom: 20px;
			}
			.bc-cm-docs-card-header {
				background: #f6f7f7;
				border-bottom: 1px solid #c3c4c7;
				padding: 15px 20px;
			}
			.bc-cm-docs-card-header h2 {
				margin: 0;
				font-size: 16px;
				font-weight: 600;
				color: #1d2327;
			}
			.bc-cm-docs-card-body {
				padding: 20px;
			}
			.bc-cm-docs-card-body h3 {
				margin: 20px 0 10px 0;
				font-size: 14px;
				font-weight: 600;
				color: #1d2327;
			}
			.bc-cm-docs-card-body h3:first-child {
				margin-top: 0;
			}
			.bc-cm-docs-card-body p {
				margin: 0 0 15px 0;
				font-size: 13px;
				line-height: 1.6;
				color: #50575e;
			}
			.bc-cm-docs-card-body ul,
			.bc-cm-docs-card-body ol {
				margin: 0 0 15px 20px;
				font-size: 13px;
				line-height: 1.8;
				color: #50575e;
			}
			.bc-cm-docs-card-body li {
				margin-bottom: 5px;
			}
			.bc-cm-docs-screenshot {
				text-align: center;
				margin: 15px 0;
			}
			.bc-cm-docs-screenshot img {
				max-width: 100%;
				height: auto;
				border: 1px solid #c3c4c7;
				box-shadow: 0 1px 3px rgba(0,0,0,0.1);
			}
			.bc-cm-docs-code {
				background: #f6f7f7;
				border: 1px solid #c3c4c7;
				padding: 15px;
				margin: 15px 0;
				font-family: Consolas, Monaco, monospace;
				font-size: 12px;
				line-height: 1.6;
				overflow-x: auto;
				white-space: pre-wrap;
				word-wrap: break-word;
			}
			.bc-cm-docs-table {
				width: 100%;
				border-collapse: collapse;
				margin: 15px 0;
				font-size: 13px;
			}
			.bc-cm-docs-table th,
			.bc-cm-docs-table td {
				border: 1px solid #c3c4c7;
				padding: 10px 12px;
				text-align: left;
			}
			.bc-cm-docs-table th {
				background: #f6f7f7;
				font-weight: 600;
			}
			.bc-cm-docs-table tr:nth-child(even) td {
				background: #f9f9f9;
			}
			.bc-cm-docs-note {
				background: #fff8e5;
				border-left: 4px solid #dba617;
				padding: 12px 15px;
				margin: 15px 0;
				font-size: 13px;
			}
			.bc-cm-docs-note strong {
				color: #1d2327;
			}
			.bc-cm-docs-tip {
				background: #e7f6e7;
				border-left: 4px solid #00a32a;
				padding: 12px 15px;
				margin: 15px 0;
				font-size: 13px;
			}
			.bc-cm-docs-warning {
				background: #fcf0f1;
				border-left: 4px solid #d63638;
				padding: 12px 15px;
				margin: 15px 0;
				font-size: 13px;
			}
			.bc-cm-docs-toc {
				background: #f6f7f7;
				border: 1px solid #c3c4c7;
				padding: 15px 20px;
				margin-bottom: 20px;
			}
			.bc-cm-docs-toc h3 {
				margin: 0 0 10px 0;
				font-size: 14px;
				font-weight: 600;
			}
			.bc-cm-docs-toc ul {
				margin: 0;
				padding: 0;
				list-style: none;
			}
			.bc-cm-docs-toc li {
				margin: 5px 0;
			}
			.bc-cm-docs-toc a {
				text-decoration: none;
				font-size: 13px;
			}
			.bc-cm-docs-toc a:hover {
				text-decoration: underline;
			}
			.bc-cm-docs-badge {
				display: inline-block;
				background: #2271b1;
				color: #fff;
				padding: 2px 8px;
				border-radius: 3px;
				font-size: 11px;
				font-weight: 600;
				text-transform: uppercase;
				margin-left: 8px;
				vertical-align: middle;
			}
			.bc-cm-docs-badge.optional {
				background: #dba617;
			}
			.bc-cm-docs-badge.required {
				background: #d63638;
			}
		';
	}

	/**
	 * Render the documentation page content.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public function render_documentation_page(): void {
		?>
		<div class="wrap bc-cm-docs-wrap">
			<?php $this->render_header(); ?>
			<?php $this->render_table_of_contents(); ?>
			<?php $this->render_overview_section(); ?>
			<?php $this->render_composite_section(); ?>
			<?php $this->render_kit_builder_section(); ?>
			<?php $this->render_matching_logic_section(); ?>
			<?php $this->render_examples_section(); ?>
			<?php $this->render_troubleshooting_section(); ?>
		</div>
		<?php
	}

	/**
	 * Render the page header.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	private function render_header(): void {
		?>
		<div class="bc-cm-docs-header">
			<h1><?php esc_html_e( 'Blaze Commerce Coupon Modifications', 'bc_cm_' ); ?></h1>
			<p><?php esc_html_e( 'Comprehensive documentation for extending WooCommerce coupon functionality with Composite Product and Kit Builder restrictions.', 'bc_cm_' ); ?></p>
			<p style="margin-top: 10px; font-size: 12px; color: #787c82;">
				<?php
				printf(
					/* translators: %s: plugin version */
					esc_html__( 'Version %s', 'bc_cm_' ),
					esc_html( BC_CM_VERSION )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the table of contents.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	private function render_table_of_contents(): void {
		?>
		<div class="bc-cm-docs-toc">
			<h3><?php esc_html_e( 'Table of Contents', 'bc_cm_' ); ?></h3>
			<ul>
				<li><a href="#overview"><?php esc_html_e( '1. Overview', 'bc_cm_' ); ?></a></li>
				<li><a href="#composite-products"><?php esc_html_e( '2. Composite Component Restrictions', 'bc_cm_' ); ?></a></li>
				<li><a href="#kit-builder"><?php esc_html_e( '3. Kit Builder / MyCustomizer Rules', 'bc_cm_' ); ?></a></li>
				<li><a href="#matching-logic"><?php esc_html_e( '4. Matching Logic Explained', 'bc_cm_' ); ?></a></li>
				<li><a href="#examples"><?php esc_html_e( '5. Examples and Use Cases', 'bc_cm_' ); ?></a></li>
				<li><a href="#troubleshooting"><?php esc_html_e( '6. Troubleshooting', 'bc_cm_' ); ?></a></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Render the overview section.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	private function render_overview_section(): void {
		?>
		<div id="overview" class="bc-cm-docs-card">
			<div class="bc-cm-docs-card-header">
				<h2><?php esc_html_e( '1. Overview', 'bc_cm_' ); ?></h2>
			</div>
			<div class="bc-cm-docs-card-body">
				<p><?php esc_html_e( 'This plugin extends WooCommerce coupon functionality to add usage restrictions based on:', 'bc_cm_' ); ?></p>

				<ol>
					<li>
						<strong><?php esc_html_e( 'Composite Product Component Selections', 'bc_cm_' ); ?></strong>
						<span class="bc-cm-docs-badge optional"><?php esc_html_e( 'Optional', 'bc_cm_' ); ?></span>
						<?php esc_html_e( ' - Products that must be present as composite components (not standalone) for a coupon to apply.', 'bc_cm_' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Kit Builder / MyCustomizer Property Rules', 'bc_cm_' ); ?></strong>
						<span class="bc-cm-docs-badge optional"><?php esc_html_e( 'Optional', 'bc_cm_' ); ?></span>
						<?php esc_html_e( ' - Property-based rules that must match for Kit Builder products.', 'bc_cm_' ); ?>
					</li>
				</ol>

				<h3><?php esc_html_e( 'Plugin Dependencies', 'bc_cm_' ); ?></h3>
				<table class="bc-cm-docs-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Plugin', 'bc_cm_' ); ?></th>
							<th><?php esc_html_e( 'Status', 'bc_cm_' ); ?></th>
							<th><?php esc_html_e( 'Purpose', 'bc_cm_' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><?php esc_html_e( 'WooCommerce', 'bc_cm_' ); ?></td>
							<td><span class="bc-cm-docs-badge required"><?php esc_html_e( 'Required', 'bc_cm_' ); ?></span></td>
							<td><?php esc_html_e( 'Core e-commerce functionality and coupon system', 'bc_cm_' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'WooCommerce Composite Products', 'bc_cm_' ); ?></td>
							<td><span class="bc-cm-docs-badge optional"><?php esc_html_e( 'Optional', 'bc_cm_' ); ?></span></td>
							<td><?php esc_html_e( 'Enables composite component restriction features', 'bc_cm_' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'MyCustomizer / Kit Builder', 'bc_cm_' ); ?></td>
							<td><span class="bc-cm-docs-badge optional"><?php esc_html_e( 'Optional', 'bc_cm_' ); ?></span></td>
							<td><?php esc_html_e( 'Enables Kit Builder property rule features', 'bc_cm_' ); ?></td>
						</tr>
					</tbody>
				</table>

				<div class="bc-cm-docs-tip">
					<strong><?php esc_html_e( 'Tip:', 'bc_cm_' ); ?></strong>
					<?php esc_html_e( 'You can use this plugin with just WooCommerce installed. Features for Composite Products and Kit Builder will automatically enable when those plugins are activated.', 'bc_cm_' ); ?>
				</div>

				<h3><?php esc_html_e( 'Where to Find the Fields', 'bc_cm_' ); ?></h3>
				<p><?php esc_html_e( 'The custom restriction fields are located in the coupon edit screen:', 'bc_cm_' ); ?></p>
				<ol>
					<li><?php esc_html_e( 'Go to Marketing > Coupons (or WooCommerce > Coupons)', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Create a new coupon or edit an existing one', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Click on the "Usage restriction" tab', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Scroll down to find "Composite component products" and "Kit Builder property rules" fields', 'bc_cm_' ); ?></li>
				</ol>

				<div class="bc-cm-docs-screenshot">
					<img
						src="<?php echo esc_url( plugins_url( 'assets/images/screenshot-overview-1.png', BC_CM_PLUGIN_FILE ) ); ?>"
						alt="<?php esc_attr_e( 'Coupon Usage Restriction tab - Part 1', 'bc_cm_' ); ?>"
						style="max-width: 100%; height: auto; border: 1px solid #c3c4c7; margin-bottom: 15px;"
					/>
					<img
						src="<?php echo esc_url( plugins_url( 'assets/images/screenshot-overview-2.png', BC_CM_PLUGIN_FILE ) ); ?>"
						alt="<?php esc_attr_e( 'Coupon Usage Restriction tab - Part 2 showing custom fields', 'bc_cm_' ); ?>"
						style="max-width: 100%; height: auto; border: 1px solid #c3c4c7;"
					/>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the composite products section.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	private function render_composite_section(): void {
		?>
		<div id="composite-products" class="bc-cm-docs-card">
			<div class="bc-cm-docs-card-header">
				<h2><?php esc_html_e( '2. Composite Component Restrictions', 'bc_cm_' ); ?></h2>
			</div>
			<div class="bc-cm-docs-card-body">
				<p><?php esc_html_e( 'This feature allows you to create coupons that only apply to products when they are part of a composite product, not when purchased as standalone items.', 'bc_cm_' ); ?></p>

				<h3><?php esc_html_e( 'How It Works', 'bc_cm_' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'The discount applies to qualifying component products when they are part of a composite', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'The discount does NOT apply to standalone products matching the restriction list', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Uses OR logic: any one matching component qualifies for the discount', 'bc_cm_' ); ?></li>
				</ul>

				<h3><?php esc_html_e( 'Setup Instructions', 'bc_cm_' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Create or edit a coupon', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Set the discount type (e.g., "Fixed product discount" or "Percentage discount")', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Go to the "Usage restriction" tab', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'In the "Composite component products" field, search for and select the products that should only receive the discount when part of a composite', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Save the coupon', 'bc_cm_' ); ?></li>
				</ol>

				<div class="bc-cm-docs-screenshot">
					<img
						src="<?php echo esc_url( plugins_url( 'assets/images/screenshot-composite-1.png', BC_CM_PLUGIN_FILE ) ); ?>"
						alt="<?php esc_attr_e( 'Composite component products field with product search', 'bc_cm_' ); ?>"
						style="max-width: 100%; height: auto; border: 1px solid #c3c4c7; margin-bottom: 15px;"
					/>
					<p style="text-align: center; font-weight: 600; color: #00a32a; margin: 10px 0 20px 0; font-size: 14px;">
						<?php esc_html_e( '✓ Coupon applies when selected product is part of a composite in cart', 'bc_cm_' ); ?>
					</p>
					<img
						src="<?php echo esc_url( plugins_url( 'assets/images/screenshot-composite-2.png', BC_CM_PLUGIN_FILE ) ); ?>"
						alt="<?php esc_attr_e( 'Composite component products field showing selected products', 'bc_cm_' ); ?>"
						style="max-width: 100%; height: auto; border: 1px solid #c3c4c7; margin-bottom: 15px;"
					/>
					<p style="text-align: center; font-weight: 600; color: #d63638; margin: 10px 0 20px 0; font-size: 14px;">
						<?php esc_html_e( '✗ Coupon does NOT apply to standalone products', 'bc_cm_' ); ?>
					</p>
					<img
						src="<?php echo esc_url( plugins_url( 'assets/images/screenshot-composite-3.png', BC_CM_PLUGIN_FILE ) ); ?>"
						alt="<?php esc_attr_e( 'Standalone product does not receive discount', 'bc_cm_' ); ?>"
						style="max-width: 100%; height: auto; border: 1px solid #c3c4c7;"
					/>
				</div>

				<h3><?php esc_html_e( 'Why Components, Not Parents?', 'bc_cm_' ); ?></h3>
				<div class="bc-cm-docs-note">
					<strong><?php esc_html_e( 'Important:', 'bc_cm_' ); ?></strong>
					<?php esc_html_e( 'Composite products typically use component-level pricing, meaning the composite parent has $0 price and each component has its own price. Applying a discount to a $0 item results in $0 discount. This is why the plugin targets components, not parents.', 'bc_cm_' ); ?>
				</div>

				<h3><?php esc_html_e( 'Discount Application Table', 'bc_cm_' ); ?></h3>
				<table class="bc-cm-docs-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Product Type', 'bc_cm_' ); ?></th>
							<th><?php esc_html_e( 'In Restrictions?', 'bc_cm_' ); ?></th>
							<th><?php esc_html_e( 'Discount Applied?', 'bc_cm_' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Composite component', 'bc_cm_' ); ?></td>
							<td><?php esc_html_e( 'Yes', 'bc_cm_' ); ?></td>
							<td><strong style="color: #00a32a;"><?php esc_html_e( 'YES', 'bc_cm_' ); ?></strong></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Standalone product', 'bc_cm_' ); ?></td>
							<td><?php esc_html_e( 'Yes', 'bc_cm_' ); ?></td>
							<td><strong style="color: #d63638;"><?php esc_html_e( 'NO', 'bc_cm_' ); ?></strong></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Composite parent', 'bc_cm_' ); ?></td>
							<td><?php esc_html_e( 'Any', 'bc_cm_' ); ?></td>
							<td><strong style="color: #d63638;"><?php esc_html_e( 'NO ($0 price)', 'bc_cm_' ); ?></strong></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Any product', 'bc_cm_' ); ?></td>
							<td><?php esc_html_e( 'No', 'bc_cm_' ); ?></td>
							<td><?php esc_html_e( 'Default WooCommerce behavior', 'bc_cm_' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Kit Builder section.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	private function render_kit_builder_section(): void {
		?>
		<div id="kit-builder" class="bc-cm-docs-card">
			<div class="bc-cm-docs-card-header">
				<h2><?php esc_html_e( '3. Kit Builder / MyCustomizer Rules', 'bc_cm_' ); ?></h2>
			</div>
			<div class="bc-cm-docs-card-body">
				<p><?php esc_html_e( 'This feature allows you to create coupons that only apply to Kit Builder / MyCustomizer products when their configured properties match specific rules.', 'bc_cm_' ); ?></p>

				<h3><?php esc_html_e( 'How It Works', 'bc_cm_' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'The discount applies to Kit Builder products whose properties match ALL configured rules', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Uses AND logic between rules: all rules must match for the coupon to apply', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Uses OR logic within a value: use | to specify multiple acceptable values (e.g., "Hyperline|HG2")', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Uses partial/contains matching (case-insensitive) for flexibility', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'The original Kit Builder product must be in the standard WooCommerce "Products" field', 'bc_cm_' ); ?></li>
				</ul>

				<h3><?php esc_html_e( 'Setup Instructions', 'bc_cm_' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Create or edit a coupon', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Set the discount type (e.g., "Fixed product discount" or "Percentage discount")', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Go to the "Usage restriction" tab', 'bc_cm_' ); ?></li>
					<li>
						<strong><?php esc_html_e( 'Important:', 'bc_cm_' ); ?></strong>
						<?php esc_html_e( 'Add the original Kit Builder product to the standard "Products" field', 'bc_cm_' ); ?>
					</li>
					<li><?php esc_html_e( 'In the "Kit Builder property rules" section, click "+ Add Rule"', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Enter a Property Name (e.g., "Armor Type") and Property Value (e.g., "Hyperline" or "Hyperline|HG2" for multiple options)', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Add additional rules as needed (all rules must match, but values separated by | use OR logic)', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Save the coupon', 'bc_cm_' ); ?></li>
				</ol>

				<div class="bc-cm-docs-screenshot">
					<img
						src="<?php echo esc_url( plugins_url( 'assets/images/screenshot-kit-1.png', BC_CM_PLUGIN_FILE ) ); ?>"
						alt="<?php esc_attr_e( 'Kit Builder property rules configuration', 'bc_cm_' ); ?>"
						style="max-width: 100%; height: auto; border: 1px solid #c3c4c7; margin-bottom: 15px;"
					/>
					<img
						src="<?php echo esc_url( plugins_url( 'assets/images/screenshot-kit-2.png', BC_CM_PLUGIN_FILE ) ); ?>"
						alt="<?php esc_attr_e( 'Kit Builder property rules with multiple rules configured', 'bc_cm_' ); ?>"
						style="max-width: 100%; height: auto; border: 1px solid #c3c4c7; margin-bottom: 15px;"
					/>
					<p style="text-align: center; font-weight: 600; color: #00a32a; margin: 10px 0 20px 0; font-size: 14px;">
						<?php esc_html_e( '✓ Coupon applies - All property rules match', 'bc_cm_' ); ?>
					</p>
					<img
						src="<?php echo esc_url( plugins_url( 'assets/images/screenshot-kit-3.png', BC_CM_PLUGIN_FILE ) ); ?>"
						alt="<?php esc_attr_e( 'Kit Builder product with matching properties receives discount', 'bc_cm_' ); ?>"
						style="max-width: 100%; height: auto; border: 1px solid #c3c4c7; margin-bottom: 15px;"
					/>
					<p style="text-align: center; font-weight: 600; color: #d63638; margin: 10px 0 20px 0; font-size: 14px;">
						<?php esc_html_e( '✗ Coupon does NOT apply - Properties do not match rules', 'bc_cm_' ); ?>
					</p>
					<img
						src="<?php echo esc_url( plugins_url( 'assets/images/screenshot-kit-4.png', BC_CM_PLUGIN_FILE ) ); ?>"
						alt="<?php esc_attr_e( 'Kit Builder product without matching properties does not receive discount', 'bc_cm_' ); ?>"
						style="max-width: 100%; height: auto; border: 1px solid #c3c4c7;"
					/>
				</div>

				<div class="bc-cm-docs-warning">
					<strong><?php esc_html_e( 'Important:', 'bc_cm_' ); ?></strong>
					<?php esc_html_e( 'You MUST add the original Kit Builder product to the standard "Products" field for the rules to work. The rules alone are not sufficient.', 'bc_cm_' ); ?>
				</div>

				<h3><?php esc_html_e( 'Understanding Kit Builder Products', 'bc_cm_' ); ?></h3>
				<p><?php esc_html_e( 'Kit Builder products create "replica" products when added to cart. Each replica has properties from customer selections. The plugin:', 'bc_cm_' ); ?></p>
				<ol>
					<li><?php esc_html_e( 'Identifies Kit Builder items by their "shopProductId" (original product)', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Checks the "summary_v2" properties against your configured rules', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'If all rules match, injects the item into WooCommerce\'s native discount calculation', 'bc_cm_' ); ?></li>
				</ol>

				<h3><?php esc_html_e( 'Discount Application Table', 'bc_cm_' ); ?></h3>
				<table class="bc-cm-docs-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Scenario', 'bc_cm_' ); ?></th>
							<th><?php esc_html_e( 'Discount Applied?', 'bc_cm_' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Kit Builder product in Products field + all rules match', 'bc_cm_' ); ?></td>
							<td><strong style="color: #00a32a;"><?php esc_html_e( 'YES', 'bc_cm_' ); ?></strong></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Kit Builder product in Products field + only some rules match', 'bc_cm_' ); ?></td>
							<td><strong style="color: #d63638;"><?php esc_html_e( 'NO', 'bc_cm_' ); ?></strong></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Kit Builder product NOT in Products field', 'bc_cm_' ); ?></td>
							<td><strong style="color: #d63638;"><?php esc_html_e( 'NO', 'bc_cm_' ); ?></strong></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Regular product (non-Kit Builder) with rules set', 'bc_cm_' ); ?></td>
							<td><?php esc_html_e( 'Default WooCommerce behavior', 'bc_cm_' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the matching logic section.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	private function render_matching_logic_section(): void {
		?>
		<div id="matching-logic" class="bc-cm-docs-card">
			<div class="bc-cm-docs-card-header">
				<h2><?php esc_html_e( '4. Matching Logic Explained', 'bc_cm_' ); ?></h2>
			</div>
			<div class="bc-cm-docs-card-body">
				<h3><?php esc_html_e( 'Composite Component Matching (OR Logic)', 'bc_cm_' ); ?></h3>
				<p><?php esc_html_e( 'For composite restrictions, the plugin uses OR logic:', 'bc_cm_' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'If ANY selected product is found as a composite component, the coupon applies', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'You can select multiple products - the customer only needs one in their cart as a component', 'bc_cm_' ); ?></li>
				</ul>

				<div class="bc-cm-docs-code"><?php esc_html_e( 'Example: Products A, B, C selected in restrictions.
Cart contains Composite X with component B.
Result: Coupon applies to component B.', 'bc_cm_' ); ?></div>

				<h3><?php esc_html_e( 'Kit Builder Rule Matching', 'bc_cm_' ); ?></h3>
				<p><?php esc_html_e( 'For Kit Builder rules, the plugin uses a combination of AND and OR logic:', 'bc_cm_' ); ?></p>
				<ul>
					<li><strong><?php esc_html_e( 'Between rules: AND logic', 'bc_cm_' ); ?></strong> - <?php esc_html_e( 'ALL configured rules must match', 'bc_cm_' ); ?></li>
					<li><strong><?php esc_html_e( 'Within a value: OR logic', 'bc_cm_' ); ?></strong> - <?php esc_html_e( 'Use | to specify multiple acceptable values (any one must match)', 'bc_cm_' ); ?></li>
				</ul>

				<div class="bc-cm-docs-code"><?php esc_html_e( 'Example Rules:
  1. Property Name: "Armor Type" | Value: "Hyperline|HG2"
  2. Property Name: "Size" | Value: "Large"

Cart Item Properties:
  - Armor Type: Hyperline Level IIIA
  - Size: Large

Result: MATCH (Armor Type contains "Hyperline", Size contains "Large")

Another Cart Item:
  - Armor Type: HG2 Standard
  - Size: Large

Result: MATCH (Armor Type contains "HG2", Size contains "Large")

Another Cart Item:
  - Armor Type: SRT Basic
  - Size: Large

Result: NO MATCH (Armor Type does not contain "Hyperline" or "HG2")', 'bc_cm_' ); ?></div>

				<h3><?php esc_html_e( 'Partial / Contains Matching', 'bc_cm_' ); ?></h3>
				<p><?php esc_html_e( 'Kit Builder rules use partial matching for flexibility:', 'bc_cm_' ); ?></p>

				<table class="bc-cm-docs-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Rule Key', 'bc_cm_' ); ?></th>
							<th><?php esc_html_e( 'Property Key', 'bc_cm_' ); ?></th>
							<th><?php esc_html_e( 'Match?', 'bc_cm_' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>Armor</code></td>
							<td><code>Armor Type</code></td>
							<td><strong style="color: #00a32a;"><?php esc_html_e( 'YES', 'bc_cm_' ); ?></strong> <?php esc_html_e( '("Armor" is in "Armor Type")', 'bc_cm_' ); ?></td>
						</tr>
						<tr>
							<td><code>armor type</code></td>
							<td><code>Armor Type</code></td>
							<td><strong style="color: #00a32a;"><?php esc_html_e( 'YES', 'bc_cm_' ); ?></strong> <?php esc_html_e( '(case-insensitive)', 'bc_cm_' ); ?></td>
						</tr>
						<tr>
							<td><code>Type</code></td>
							<td><code>Armor Type</code></td>
							<td><strong style="color: #00a32a;"><?php esc_html_e( 'YES', 'bc_cm_' ); ?></strong> <?php esc_html_e( '("Type" is in "Armor Type")', 'bc_cm_' ); ?></td>
						</tr>
					</tbody>
				</table>

				<table class="bc-cm-docs-table" style="margin-top: 20px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Rule Value', 'bc_cm_' ); ?></th>
							<th><?php esc_html_e( 'Property Value', 'bc_cm_' ); ?></th>
							<th><?php esc_html_e( 'Match?', 'bc_cm_' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>Hyperline</code></td>
							<td><code>Hyperline Level IIIA</code></td>
							<td><strong style="color: #00a32a;"><?php esc_html_e( 'YES', 'bc_cm_' ); ?></strong> <?php esc_html_e( '("Hyperline" is in full value)', 'bc_cm_' ); ?></td>
						</tr>
						<tr>
							<td><code>hyperline</code></td>
							<td><code>Hyperline Level IIIA</code></td>
							<td><strong style="color: #00a32a;"><?php esc_html_e( 'YES', 'bc_cm_' ); ?></strong> <?php esc_html_e( '(case-insensitive)', 'bc_cm_' ); ?></td>
						</tr>
						<tr>
							<td><code>Level IIIA</code></td>
							<td><code>Hyperline Level IIIA</code></td>
							<td><strong style="color: #00a32a;"><?php esc_html_e( 'YES', 'bc_cm_' ); ?></strong> <?php esc_html_e( '("Level IIIA" is in full value)', 'bc_cm_' ); ?></td>
						</tr>
						<tr>
							<td><code>Level IV</code></td>
							<td><code>Hyperline Level IIIA</code></td>
							<td><strong style="color: #d63638;"><?php esc_html_e( 'NO', 'bc_cm_' ); ?></strong> <?php esc_html_e( '("Level IV" not found)', 'bc_cm_' ); ?></td>
						</tr>
					</tbody>
				</table>

				<div class="bc-cm-docs-tip">
					<strong><?php esc_html_e( 'Tip:', 'bc_cm_' ); ?></strong>
					<?php esc_html_e( 'Use specific enough values to avoid unintended matches. For example, "Level IIIA" is more specific than just "Level" which might match "Level II", "Level IIIA", etc.', 'bc_cm_' ); ?>
				</div>

				<h3><?php esc_html_e( 'Multiple Values with Pipe Delimiter', 'bc_cm_' ); ?></h3>
				<p><?php esc_html_e( 'Use the pipe character (|) to specify multiple acceptable values for a single rule:', 'bc_cm_' ); ?></p>

				<table class="bc-cm-docs-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Rule Value', 'bc_cm_' ); ?></th>
							<th><?php esc_html_e( 'Property Value', 'bc_cm_' ); ?></th>
							<th><?php esc_html_e( 'Match?', 'bc_cm_' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>Hyperline|HG2</code></td>
							<td><code>Hyperline Level IIIA</code></td>
							<td><strong style="color: #00a32a;"><?php esc_html_e( 'YES', 'bc_cm_' ); ?></strong> <?php esc_html_e( '(contains "Hyperline")', 'bc_cm_' ); ?></td>
						</tr>
						<tr>
							<td><code>Hyperline|HG2</code></td>
							<td><code>HG2 Standard</code></td>
							<td><strong style="color: #00a32a;"><?php esc_html_e( 'YES', 'bc_cm_' ); ?></strong> <?php esc_html_e( '(contains "HG2")', 'bc_cm_' ); ?></td>
						</tr>
						<tr>
							<td><code>Hyperline|HG2</code></td>
							<td><code>SRT Basic</code></td>
							<td><strong style="color: #d63638;"><?php esc_html_e( 'NO', 'bc_cm_' ); ?></strong> <?php esc_html_e( '(contains neither)', 'bc_cm_' ); ?></td>
						</tr>
						<tr>
							<td><code>Large|XL|XXL</code></td>
							<td><code>Extra Large (XL)</code></td>
							<td><strong style="color: #00a32a;"><?php esc_html_e( 'YES', 'bc_cm_' ); ?></strong> <?php esc_html_e( '(contains "XL")', 'bc_cm_' ); ?></td>
						</tr>
					</tbody>
				</table>

				<div class="bc-cm-docs-note">
					<strong><?php esc_html_e( 'Note:', 'bc_cm_' ); ?></strong>
					<?php esc_html_e( 'Spaces around the pipe are trimmed, so "Hyperline | HG2" works the same as "Hyperline|HG2".', 'bc_cm_' ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the examples section.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	private function render_examples_section(): void {
		?>
		<div id="examples" class="bc-cm-docs-card">
			<div class="bc-cm-docs-card-header">
				<h2><?php esc_html_e( '5. Examples and Use Cases', 'bc_cm_' ); ?></h2>
			</div>
			<div class="bc-cm-docs-card-body">
				<h3><?php esc_html_e( 'Example 1: Bundle-Only Discount', 'bc_cm_' ); ?></h3>
				<p><strong><?php esc_html_e( 'Scenario:', 'bc_cm_' ); ?></strong> <?php esc_html_e( 'You want to offer 20% off on a specific product, but only when purchased as part of a bundle (composite product).', 'bc_cm_' ); ?></p>
				<p><strong><?php esc_html_e( 'Setup:', 'bc_cm_' ); ?></strong></p>
				<ol>
					<li><?php esc_html_e( 'Create coupon "BUNDLE20"', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Set discount type: Percentage discount, 20%', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'In "Composite component products", select the target product', 'bc_cm_' ); ?></li>
				</ol>
				<p><strong><?php esc_html_e( 'Result:', 'bc_cm_' ); ?></strong> <?php esc_html_e( 'Product gets 20% off only when part of a composite. Standalone purchases do not qualify.', 'bc_cm_' ); ?></p>

				<hr style="margin: 30px 0; border: none; border-top: 1px solid #c3c4c7;">

				<h3><?php esc_html_e( 'Example 2: Multiple Armor Types with Pipe Delimiter', 'bc_cm_' ); ?></h3>
				<p><strong><?php esc_html_e( 'Scenario:', 'bc_cm_' ); ?></strong> <?php esc_html_e( 'You want to offer $50 off customizable body armor for both "Hyperline" and "HG2" armor types, but only in "Large" size.', 'bc_cm_' ); ?></p>
				<p><strong><?php esc_html_e( 'Setup:', 'bc_cm_' ); ?></strong></p>
				<ol>
					<li><?php esc_html_e( 'Create coupon "ARMOR50"', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Set discount type: Fixed product discount, $50', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'In "Products" field, add the Kit Builder body armor product', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Add Kit Builder rule: Property Name "Armor Type", Value "Hyperline|HG2"', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Add Kit Builder rule: Property Name "Size", Value "Large"', 'bc_cm_' ); ?></li>
				</ol>
				<p><strong><?php esc_html_e( 'Result:', 'bc_cm_' ); ?></strong></p>
				<ul>
					<li style="color: #00a32a;"><?php esc_html_e( '✓ Hyperline + Large = $50 off', 'bc_cm_' ); ?></li>
					<li style="color: #00a32a;"><?php esc_html_e( '✓ HG2 + Large = $50 off', 'bc_cm_' ); ?></li>
					<li style="color: #d63638;"><?php esc_html_e( '✗ Hyperline + Medium = No discount', 'bc_cm_' ); ?></li>
					<li style="color: #d63638;"><?php esc_html_e( '✗ SRT + Large = No discount', 'bc_cm_' ); ?></li>
				</ul>

				<hr style="margin: 30px 0; border: none; border-top: 1px solid #c3c4c7;">

				<h3><?php esc_html_e( 'Example 3: Combined Restrictions', 'bc_cm_' ); ?></h3>
				<p><strong><?php esc_html_e( 'Scenario:', 'bc_cm_' ); ?></strong> <?php esc_html_e( 'You want a coupon that works for both composite components AND specific Kit Builder configurations.', 'bc_cm_' ); ?></p>
				<p><strong><?php esc_html_e( 'Setup:', 'bc_cm_' ); ?></strong></p>
				<ol>
					<li><?php esc_html_e( 'Create coupon "SPECIAL15"', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Set discount type: Percentage discount, 15%', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'In "Products" field, add the Kit Builder product', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'In "Composite component products", add products that qualify when bundled', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Add Kit Builder rules for specific configurations', 'bc_cm_' ); ?></li>
				</ol>
				<p><strong><?php esc_html_e( 'Result:', 'bc_cm_' ); ?></strong> <?php esc_html_e( 'Coupon applies to either composite components OR matching Kit Builder configurations.', 'bc_cm_' ); ?></p>

				<hr style="margin: 30px 0; border: none; border-top: 1px solid #c3c4c7;">

				<h3><?php esc_html_e( 'Example 4: Using Standard Products Field with Our Restrictions', 'bc_cm_' ); ?></h3>
				<p><strong><?php esc_html_e( 'Scenario:', 'bc_cm_' ); ?></strong> <?php esc_html_e( 'You want some products to always get the discount, while others only qualify when bundled.', 'bc_cm_' ); ?></p>
				<p><strong><?php esc_html_e( 'Setup:', 'bc_cm_' ); ?></strong></p>
				<ol>
					<li><?php esc_html_e( 'In standard "Products" field: Add Product A (always qualifies)', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'In "Composite component products": Add Product B (only as component)', 'bc_cm_' ); ?></li>
				</ol>
				<p><strong><?php esc_html_e( 'Result:', 'bc_cm_' ); ?></strong></p>
				<ul>
					<li><?php esc_html_e( 'Product A: Gets discount whether standalone or bundled', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Product B: Gets discount only when part of a composite', 'bc_cm_' ); ?></li>
				</ul>

				<div class="bc-cm-docs-note">
					<strong><?php esc_html_e( 'Note:', 'bc_cm_' ); ?></strong>
					<?php esc_html_e( 'If a product is in BOTH fields, our composite component restriction takes precedence. The product will only get the discount as a component, not standalone.', 'bc_cm_' ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the troubleshooting section.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	private function render_troubleshooting_section(): void {
		?>
		<div id="troubleshooting" class="bc-cm-docs-card">
			<div class="bc-cm-docs-card-header">
				<h2><?php esc_html_e( '6. Troubleshooting', 'bc_cm_' ); ?></h2>
			</div>
			<div class="bc-cm-docs-card-body">
				<h3><?php esc_html_e( 'Coupon Not Applying to Kit Builder Products', 'bc_cm_' ); ?></h3>
				<p><strong><?php esc_html_e( 'Checklist:', 'bc_cm_' ); ?></strong></p>
				<ol>
					<li><?php esc_html_e( 'Is the original Kit Builder product added to the standard "Products" field?', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Do ALL your Kit Builder rules match the product properties?', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Check spelling and case (though matching is case-insensitive)', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Verify the property names match what Kit Builder uses', 'bc_cm_' ); ?></li>
				</ol>

				<h3><?php esc_html_e( 'Coupon Not Applying to Composite Components', 'bc_cm_' ); ?></h3>
				<p><strong><?php esc_html_e( 'Checklist:', 'bc_cm_' ); ?></strong></p>
				<ol>
					<li><?php esc_html_e( 'Is the product actually a component of a composite in the cart?', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Is WooCommerce Composite Products plugin active?', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Is the product selected in the "Composite component products" field?', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Does the composite use component-level pricing?', 'bc_cm_' ); ?></li>
				</ol>

				<h3><?php esc_html_e( 'Discount Shows But Amount is Wrong', 'bc_cm_' ); ?></h3>
				<p><strong><?php esc_html_e( 'Possible causes:', 'bc_cm_' ); ?></strong></p>
				<ul>
					<li><?php esc_html_e( 'Check the discount type (fixed vs percentage)', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'For fixed product discounts, verify the amount per item', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'Check if there are usage limits affecting the discount', 'bc_cm_' ); ?></li>
				</ul>

				<h3><?php esc_html_e( 'Error: "This coupon requires specific product configurations"', 'bc_cm_' ); ?></h3>
				<p><?php esc_html_e( 'This error appears when:', 'bc_cm_' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'You have custom restrictions set (composite or Kit Builder)', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'No items in the cart match those restrictions', 'bc_cm_' ); ?></li>
					<li><?php esc_html_e( 'No standard products (from "Products" field) are in the cart either', 'bc_cm_' ); ?></li>
				</ul>
				<p><strong><?php esc_html_e( 'Solution:', 'bc_cm_' ); ?></strong> <?php esc_html_e( 'Ensure the cart contains products that match your coupon restrictions.', 'bc_cm_' ); ?></p>

				<h3><?php esc_html_e( 'Need More Help?', 'bc_cm_' ); ?></h3>
				<p>
					<?php
					printf(
						/* translators: %s: support email */
						esc_html__( 'Contact support at %s with details about your configuration and the issue you are experiencing.', 'bc_cm_' ),
						'<a href="mailto:support@blazecommerce.io">support@blazecommerce.io</a>'
					);
					?>
				</p>
			</div>
		</div>
		<?php
	}
}
