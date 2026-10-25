<?php
/**
 * Uninstall: remove plugin options and cron. Ads and placements are left in the database.
 *
 * @package SponsorManager
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

wp_clear_scheduled_hook( 'sponsor_manager_daily_status_check' );
delete_option( 'sponsor_manager_global_styles' );
delete_option( 'sponsor_manager_license_key' );
delete_option( 'sponsor_manager_terms_seeded_v1' );
delete_option( 'sponsor_manager_flush_rewrite_rules' );
delete_option( 'sponsor_manager_hide_acf_handoff_notice' );
delete_option( 'sponsor_manager_native_types_v1' );
