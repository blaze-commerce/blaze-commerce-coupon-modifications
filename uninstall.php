<?php
/**
 * Uninstall script for Blaze Commerce Coupon Modifications.
 *
 * This file is executed when the plugin is uninstalled via the WordPress admin.
 * It removes all plugin data from the database, specifically the coupon meta
 * entries that store composite component product restrictions.
 *
 * @package Blaze_Commerce_Coupon_Modifications
 * @since 1.0.0
 */

// Security: Ensure this is being called by WordPress uninstall process.
// WP_UNINSTALL_PLUGIN is only defined when WordPress is running an uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Cleanup Behavior:
 *
 * This uninstall script removes all coupon post meta entries created by this plugin.
 * Specifically, it deletes:
 * - '_bc_cm_composite_component_products' - Composite component restrictions
 * - '_bc_cm_kit_builder_rules' - Kit Builder property rules
 *
 * This cleanup is necessary because:
 * 1. The meta data serves no purpose without the plugin active
 * 2. Leaving orphaned meta data clutters the database
 * 3. If the plugin is reinstalled, fresh configuration is preferred
 *
 * Note: This does NOT modify any coupons themselves - only removes the custom
 * meta data added by this plugin. Coupons will continue to function normally
 * under WooCommerce's default rules after uninstall.
 */

global $wpdb;

// Meta keys used by this plugin.
$meta_keys = array(
	'_bc_cm_composite_component_products', // Composite component restrictions.
	'_bc_cm_kit_builder_rules',            // Kit Builder property rules.
);

// Delete all post meta entries with our meta keys.
// Using direct database query for efficiency when potentially dealing with many coupons.
// This is the recommended approach in uninstall scripts per WordPress documentation.
foreach ( $meta_keys as $meta_key ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->delete(
		$wpdb->postmeta,
		array( 'meta_key' => $meta_key ),
		array( '%s' )
	);
}

// Clear any cached data that might exist.
wp_cache_flush();
