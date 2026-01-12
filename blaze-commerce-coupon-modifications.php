<?php
/**
 * Plugin Name: Blaze Commerce Coupon Modifications
 * Plugin URI: https://blazecommerce.io
 * Description: Extends WooCommerce coupons to add restrictions based on composite product component selections.
 * Version: 1.0.0
 * Author: Blaze Commerce
 * Author URI: https://blazecommerce.io
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bc_cm_
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 *
 * @package Blaze_Commerce_Coupon_Modifications
 */

// Security: Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version constant.
 */
define( 'BC_CM_VERSION', '1.0.0' );

/**
 * Plugin directory path constant.
 */
define( 'BC_CM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin file path constant.
 */
define( 'BC_CM_PLUGIN_FILE', __FILE__ );

/**
 * Meta key for storing composite component product restrictions.
 * Prefixed with underscore to hide from custom fields UI.
 */
define( 'BC_CM_META_KEY', '_bc_cm_composite_component_products' );

/**
 * Main plugin class using singleton pattern.
 *
 * Handles plugin initialization, dependency checks, and loading of subclasses.
 *
 * @since 1.0.0
 */
final class BC_CM_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var BC_CM_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Admin class instance.
	 *
	 * @var BC_CM_Admin|null
	 */
	public $admin = null;

	/**
	 * Coupon validator class instance.
	 *
	 * @var BC_CM_Coupon_Validator|null
	 */
	public $validator = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return BC_CM_Plugin
	 */
	public static function instance(): BC_CM_Plugin {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Private to enforce singleton pattern.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Hook into plugins_loaded to ensure WooCommerce is available.
		add_action( 'plugins_loaded', array( $this, 'init' ), 20 );

		// Register uninstall hook for cleanup.
		register_uninstall_hook( __FILE__, array( 'BC_CM_Plugin', 'uninstall' ) );
	}

	/**
	 * Initialize the plugin after checking dependencies.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		// Check for required plugins before initializing.
		if ( ! $this->check_dependencies() ) {
			return;
		}

		// Load plugin text domain for translations.
		$this->load_textdomain();

		// Include required class files.
		$this->includes();

		// Initialize classes.
		$this->init_classes();
	}

	/**
	 * Check if required plugins are active.
	 *
	 * Verifies WooCommerce and WooCommerce Composite Products are active.
	 * Displays admin notice if dependencies are missing.
	 *
	 * @since 1.0.0
	 * @return bool True if all dependencies met, false otherwise.
	 */
	private function check_dependencies(): bool {
		$missing = array();

		// Check for WooCommerce.
		if ( ! class_exists( 'WooCommerce' ) ) {
			$missing[] = 'WooCommerce';
		}

		// Check for WooCommerce Composite Products.
		// The plugin defines this class for composite product handling.
		if ( ! class_exists( 'WC_Product_Composite' ) && ! function_exists( 'wc_cp_is_composited_cart_item' ) ) {
			$missing[] = 'WooCommerce Composite Products';
		}

		if ( ! empty( $missing ) ) {
			add_action( 'admin_notices', function() use ( $missing ) {
				$this->dependency_notice( $missing );
			} );
			return false;
		}

		return true;
	}

	/**
	 * Display admin notice for missing dependencies.
	 *
	 * @since 1.0.0
	 * @param array $missing List of missing plugin names.
	 * @return void
	 */
	private function dependency_notice( array $missing ): void {
		// Only show to users who can install plugins.
		if ( ! current_user_can( 'install_plugins' ) ) {
			return;
		}

		$plugins = implode( ', ', $missing );
		?>
		<div class="notice notice-error">
			<p>
				<?php
				printf(
					/* translators: %s: comma-separated list of required plugin names */
					esc_html__( 'Blaze Commerce Coupon Modifications requires the following plugins to be active: %s', 'bc_cm_' ),
					'<strong>' . esc_html( $plugins ) . '</strong>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Load plugin text domain for translations.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function load_textdomain(): void {
		load_plugin_textdomain(
			'bc_cm_',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}

	/**
	 * Include required class files.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function includes(): void {
		require_once BC_CM_PLUGIN_DIR . 'includes/class-bc-cm-admin.php';
		require_once BC_CM_PLUGIN_DIR . 'includes/class-bc-cm-coupon-validator.php';
	}

	/**
	 * Initialize plugin classes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_classes(): void {
		// Admin class handles coupon edit screen fields.
		$this->admin = new BC_CM_Admin();

		// Validator class handles coupon application logic.
		$this->validator = new BC_CM_Coupon_Validator();
	}

	/**
	 * Static uninstall method called by register_uninstall_hook.
	 *
	 * Cleans up all plugin data from the database.
	 * This is a fallback; primary cleanup is in uninstall.php.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function uninstall(): void {
		global $wpdb;

		// Delete all coupon meta with our key.
		// Using direct query for efficiency when dealing with potentially many coupons.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$wpdb->postmeta,
			array( 'meta_key' => BC_CM_META_KEY ),
			array( '%s' )
		);
	}

	/**
	 * Prevent cloning of singleton.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing of singleton.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}

/**
 * Returns the main plugin instance.
 *
 * @since 1.0.0
 * @return BC_CM_Plugin
 */
function BC_CM(): BC_CM_Plugin {
	return BC_CM_Plugin::instance();
}

// Initialize the plugin.
BC_CM();
