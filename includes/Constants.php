<?php
/**
 * Shared plugin constants.
 *
 * @package SponsorManager
 */

namespace SponsorManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Names used across ads, cron, and admin.
 */
final class Constants {

	public const VERSION          = '1.5.3';
	public const POST_TYPE        = 'sponsor_ad';
	public const TAXONOMY         = 'sponsor_placement';
	public const PAUSE_META       = 'expired';
	public const EVERYWHERE_SLUG  = 'everywhere';
	public const CRON_HOOK        = 'sponsor_manager_daily_status_check';
	public const STYLES_OPTION    = 'sponsor_manager_global_styles';
	public const MENU_SLUG        = 'sponsor_manager';
	public const TEXT_DOMAIN      = 'sponsor-manager';
}
