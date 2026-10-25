<?php
/**
 * Plugin orchestrator.
 *
 * @package SponsorManager
 */

namespace SponsorManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SponsorManager\Acf\Handoff as AcfHandoff;
use SponsorManager\Admin\BookingMetaBox;
use SponsorManager\Admin\Dashboard;
use SponsorManager\Admin\LicensePage;
use SponsorManager\Admin\ListTable;
use SponsorManager\Admin\Menu;
use SponsorManager\Admin\StylesPage;
use SponsorManager\Ads\Placements;
use SponsorManager\Ads\Status;
use SponsorManager\Blocks\Registrar as BlockRegistrar;

/**
 * Single entry point after plugins_loaded.
 */
class Plugin {

	/**
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register all runtime hooks.
	 */
	public function boot() {
		require_once SPONSOR_MANAGER_PATH . 'includes/dashboard.php';

		Lifecycle::register();
		PostTypes::register();
		AcfHandoff::register();
		Placements::register();
		Status::register();
		BlockRegistrar::register();
		if ( is_readable( SPONSOR_MANAGER_PATH . 'includes/Updater.php' ) ) {
			Updater::register();
		}

		Menu::register();
		ListTable::register();
		Dashboard::register();
		StylesPage::register();
		LicensePage::register();
		BookingMetaBox::register();
	}
}
