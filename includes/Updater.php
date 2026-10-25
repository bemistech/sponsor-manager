<?php
/**
 * Plugin updates from the Cloudflare metadata file.
 *
 * @package SponsorManager
 */

namespace SponsorManager;

use SponsorManager\Updates\License;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Offers Sponsor Manager from the published metadata on updates.bemistech.com.
 */
class Updater {

	const METADATA_URL = 'https://updates.bemistech.com/sponsor-manager.json';

	const PACKAGE_PREFIX = 'https://updates.bemistech.com/sponsor-manager/';

	/**
	 * Whether a metadata request is already running.
	 *
	 * @var bool
	 */
	private static $checking = false;

	/**
	 * Whether the update hooks are already registered.
	 *
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Register the metadata check and the request key.
	 */
	public static function register() {
		if ( self::$registered ) {
			return;
		}
		self::$registered = true;
		add_filter( 'update_plugins_updates.bemistech.com', array( __CLASS__, 'from_hostname' ), 10, 4 );
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'offer' ) );
		add_filter( 'http_request_args', array( __CLASS__, 'authorize' ), 10, 2 );
	}

	/**
	 * Answer WordPress when it asks the Update URI host about this plugin.
	 *
	 * @param array|false $update      Existing update data.
	 * @param array       $plugin_data Plugin headers.
	 * @param string      $plugin_file Plugin basename.
	 * @param array       $locales     Installed locales.
	 * @return array|false
	 */
	public static function from_hostname( $update, $plugin_data, $plugin_file, $locales ) {
		unset( $plugin_data, $locales );
		if ( plugin_basename( SPONSOR_MANAGER_FILE ) !== $plugin_file ) {
			return $update;
		}
		$remote = self::metadata();
		if ( ! isset( $remote['version'], $remote['download_url'] ) ) {
			return $update;
		}
		return array(
			'slug'        => 'sponsor-manager',
			'plugin'      => $plugin_file,
			'version'     => $remote['version'],
			'new_version' => $remote['version'],
			'url'         => $remote['details_url'],
			'package'     => $remote['download_url'],
		);
	}

	/**
	 * Add a newer package to the plugin update list.
	 *
	 * @param mixed $transient Update transient.
	 * @return mixed
	 */
	public static function offer( $transient ) {
		if ( self::$checking || ! is_object( $transient ) ) {
			return $transient;
		}
		self::$checking = true;
		$remote         = self::metadata();
		self::$checking = false;
		$file           = plugin_basename( SPONSOR_MANAGER_FILE );
		$installed      = defined( 'SPONSOR_MANAGER_VERSION' ) ? SPONSOR_MANAGER_VERSION : '';
		if ( ! isset( $remote['version'], $remote['download_url'] ) || '' === $installed || ! version_compare( $remote['version'], $installed, '>' ) ) {
			return $transient;
		}
		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}
		unset( $transient->no_update[ $file ] );
		$transient->response[ $file ] = (object) array(
			'id'          => $file,
			'slug'        => 'sponsor-manager',
			'plugin'      => $file,
			'new_version' => $remote['version'],
			'url'         => $remote['details_url'],
			'package'     => $remote['download_url'],
		);
		return $transient;
	}

	/**
	 * Send this plugin's license key only with its own update requests.
	 *
	 * @param array  $args Request arguments.
	 * @param string $url  Request URL.
	 * @return array
	 */
	public static function authorize( $args, $url ) {
		if ( ! is_string( $url ) || ! self::is_update_url( $url ) ) {
			return $args;
		}
		$key = License::plain();
		if ( '' === $key ) {
			return $args;
		}
		if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
			$args['headers'] = array();
		}
		$args['headers']['Authorization'] = 'Bearer ' . $key;
		return $args;
	}

	/**
	 * Whether a URL is this plugin's metadata or package.
	 *
	 * @param string $url Request URL.
	 * @return bool
	 */
	private static function is_update_url( $url ) {
		if ( self::METADATA_URL === $url || 0 === strpos( $url, self::METADATA_URL . '?' ) ) {
			return true;
		}
		return 0 === strpos( $url, self::PACKAGE_PREFIX );
	}

	/**
	 * Read and validate the published metadata.
	 *
	 * @return array
	 */
	private static function metadata() {
		$response = wp_remote_get( self::METADATA_URL, array( 'timeout' => 8 ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return array();
		}
		$version  = isset( $data['version'] ) ? (string) $data['version'] : '';
		$download = isset( $data['download_url'] ) ? (string) $data['download_url'] : '';
		$details  = isset( $data['details_url'] ) ? (string) $data['details_url'] : '';
		if ( ! preg_match( '/^\d+\.\d+\.\d+(?:\.\d+)?(?:[-+][A-Za-z0-9.]+)?$/', $version ) || 0 !== strpos( $download, self::PACKAGE_PREFIX ) || ! wp_http_validate_url( $download ) ) {
			return array();
		}
		if ( '' !== $details && ! wp_http_validate_url( $details ) ) {
			$details = '';
		}
		return array(
			'version'      => $version,
			'download_url' => $download,
			'details_url'  => $details,
		);
	}
}
