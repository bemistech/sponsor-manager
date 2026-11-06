<?php
/**
 * Activation, deactivation, and cron.
 *
 * @package SponsorManager
 */

namespace SponsorManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin lifecycle hooks. Activation/deactivation must be registered from the main file.
 */
class Lifecycle {

	/**
	 * Wire rewrite flush and a daily status cron.
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'maybe_schedule_cron' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flag_native_types' ), 4 );
		add_action( 'init', array( __CLASS__, 'maybe_flag_private_types' ), 4 );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrites' ), 999 );
	}

	/**
	 * Schedule daily ad status updates and flag a rewrite flush.
	 */
	public static function activate() {
		if ( ! wp_next_scheduled( Constants::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', Constants::CRON_HOOK );
		}
		update_option( 'sponsor_manager_flush_rewrite_rules', 1, false );
	}

	/**
	 * Clear cron and flush rewrites.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( Constants::CRON_HOOK );
		flush_rewrite_rules();
	}

	/**
	 * Re-schedule cron if the site was copied without running activation.
	 */
	public static function maybe_schedule_cron() {
		if ( ! wp_next_scheduled( Constants::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', Constants::CRON_HOOK );
		}
	}

	/**
	 * Flush rewrites once after moving CPT registration off ACF.
	 */
	public static function maybe_flag_native_types() {
		if ( get_option( 'sponsor_manager_native_types_v1' ) ) {
			return;
		}
		update_option( 'sponsor_manager_flush_rewrite_rules', 1, false );
		update_option( 'sponsor_manager_native_types_v1', 1, false );
	}

	/**
	 * Flush once so retired public sponsor URLs drop out of the rewrite rules.
	 */
	public static function maybe_flag_private_types() {
		if ( get_option( 'sponsor_manager_private_types_v1' ) ) {
			return;
		}
		update_option( 'sponsor_manager_flush_rewrite_rules', 1, false );
		update_option( 'sponsor_manager_private_types_v1', 1, false );
	}

	/**
	 * Flush rewrite rules once after activation.
	 */
	public static function maybe_flush_rewrites() {
		if ( get_option( 'sponsor_manager_flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
			delete_option( 'sponsor_manager_flush_rewrite_rules' );
		}
	}
}
