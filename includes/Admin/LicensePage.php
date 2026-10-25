<?php
/**
 * License tab on the Sponsor Manager dashboard.
 *
 * @package SponsorManager
 */

namespace SponsorManager\Admin;

use SponsorManager\Constants;
use SponsorManager\Updates\License;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Saves the license key and renders its form.
 */
class LicensePage {

	/**
	 * Register the save handler.
	 */
	public static function register() {
		add_action( 'admin_init', array( __CLASS__, 'handle_save' ) );
	}

	/**
	 * Persist the license key.
	 */
	public static function handle_save() {
		if ( ! isset( $_POST['sponsor_manager_save_license'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to change the Sponsor Manager license.', 'sponsor-manager' ) );
		}
		check_admin_referer( 'sponsor_manager_save_license' );

		$key   = isset( $_POST['sponsor_manager_license_key'] ) && is_string( $_POST['sponsor_manager_license_key'] ) ? wp_unslash( $_POST['sponsor_manager_license_key'] ) : '';
		$saved = License::save( $key, ! empty( $_POST['sponsor_manager_license_clear'] ) );
		$arg   = is_wp_error( $saved ) ? 'license-invalid' : 'license-saved';
		wp_safe_redirect( add_query_arg( array( 'page' => Constants::MENU_SLUG, 'tab' => 'license', 'updated' => $arg ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Output the License tab.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to change the Sponsor Manager license.', 'sponsor-manager' ) );
		}
		$saved = License::saved();
		$view  = SPONSOR_MANAGER_PATH . 'includes/Admin/views/license-tab.php';
		if ( is_readable( $view ) ) {
			require $view;
		}
	}
}
