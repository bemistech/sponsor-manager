<?php
/**
 * Front-end, editor, and admin assets.
 *
 * @package SponsorManager
 */

namespace SponsorManager;

use SponsorManager\Styles\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image sizes and stylesheets. Hooks are registered from the main plugin file.
 */
class Assets {

	/**
	 * 16:9 crop for source ads that are 3840×2160.
	 */
	public static function register_image_sizes() {
		add_image_size( 'sponsor-ad', 1280, 720, true );
	}

	/**
	 * Print global ad CSS variables on the front end.
	 */
	public static function enqueue_front() {
		self::enqueue_global_styles();
	}

	/**
	 * Print global ad CSS variables in the block editor.
	 */
	public static function enqueue_editor() {
		self::enqueue_global_styles();
	}

	/**
	 * Dashboard media picker and Styles tab CSS.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_admin( $hook ) {
		if ( 'toplevel_page_sponsor_manager' !== $hook ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'ads' === $tab ) {
			wp_enqueue_media();
			wp_enqueue_script(
				'sponsor-manager-admin-ads',
				SPONSOR_MANAGER_URL . 'assets/js/admin-ads.js',
				array( 'jquery' ),
				Constants::VERSION,
				true
			);
		}

		if ( 'styles' === $tab ) {
			$style_path = SPONSOR_MANAGER_PATH . 'build/sponsor-grid/style-index.css';
			if ( file_exists( $style_path ) ) {
				wp_enqueue_style(
					'sponsor-manager-grid',
					SPONSOR_MANAGER_URL . 'build/sponsor-grid/style-index.css',
					array(),
					Constants::VERSION
				);
			}
			self::enqueue_global_styles();
		}
	}

	/**
	 * Register an empty handle and attach :root custom properties.
	 */
	public static function enqueue_global_styles() {
		$css = ':where(.wp-site-blocks,.editor-styles-wrapper){' . Repository::to_css( Repository::get() ) . ';}';

		wp_register_style( 'sponsor-manager-global', false, array(), Constants::VERSION );
		wp_enqueue_style( 'sponsor-manager-global' );
		wp_add_inline_style( 'sponsor-manager-global', $css );
	}
}
