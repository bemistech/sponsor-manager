<?php
/**
 * Create and rename sponsor placements from the block editor.
 *
 * @package SponsorManager
 */

namespace SponsorManager\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SponsorManager\Ads\PlacementUsage;
use SponsorManager\Ads\Placements;
use SponsorManager\Ads\Query;
use SponsorManager\Constants;

/**
 * Routes that let page and template editors name a placement without manage_categories.
 */
class PlacementsController {

	/**
	 * Hook route registration.
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * POST to create, PUT to rename. The slug is assigned once and then left alone.
	 */
	public static function register_routes() {
		register_rest_route(
			'sponsor-manager/v1',
			'/placements',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_item' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'args'                => array(
					'name' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'sponsor-manager/v1',
			'/placements/(?P<slug>[a-z0-9_-]+)/summary',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'summary' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'args'                => array(
					'slug' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			'sponsor-manager/v1',
			'/placements/(?P<slug>[a-z0-9_-]+)',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'rename_item' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'args'                => array(
					'slug' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_title',
					),
					'name' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * People who can place the block, plus placement managers.
	 *
	 * @return true|\WP_Error
	 */
	public static function can_edit() {
		if ( current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ) || current_user_can( 'edit_theme_options' ) || current_user_can( 'manage_categories' ) ) {
			return true;
		}

		return new \WP_Error(
			'sponsor_manager_forbidden',
			__( 'Sorry, you are not allowed to edit placements.', 'sponsor-manager' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Create a placement from its pretty name, or return the term that already has that name.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function create_item( \WP_REST_Request $request ) {
		$name = trim( (string) $request->get_param( 'name' ) );
		if ( '' === $name ) {
			return new \WP_Error(
				'sponsor_manager_missing_name',
				__( 'Enter a placement name.', 'sponsor-manager' ),
				array( 'status' => 400 )
			);
		}

		$existing = self::term_by_name( $name );
		if ( $existing ) {
			return rest_ensure_response( self::payload( $existing, true ) );
		}

		$slug = sanitize_title( $name );
		if ( '' === $slug ) {
			return new \WP_Error(
				'sponsor_manager_missing_name',
				__( 'Enter a placement name.', 'sponsor-manager' ),
				array( 'status' => 400 )
			);
		}

		if ( in_array( $slug, Placements::everywhere_slugs(), true ) ) {
			$everywhere = get_term_by( 'slug', Constants::EVERYWHERE_SLUG, Constants::TAXONOMY );
			if ( $everywhere && ! is_wp_error( $everywhere ) ) {
				return rest_ensure_response( self::payload( $everywhere, true ) );
			}

			return new \WP_Error(
				'sponsor_manager_protected',
				__( 'Everywhere is already a placement.', 'sponsor-manager' ),
				array( 'status' => 400 )
			);
		}

		$result = wp_insert_term( $name, Constants::TAXONOMY );
		if ( is_wp_error( $result ) ) {
			if ( 'term_exists' === $result->get_error_code() ) {
				$term_id = (int) $result->get_error_data();
				$term    = get_term( $term_id, Constants::TAXONOMY );
				if ( $term && ! is_wp_error( $term ) ) {
					return rest_ensure_response( self::payload( $term, true ) );
				}
			}

			return new \WP_Error(
				'sponsor_manager_create_failed',
				$result->get_error_message(),
				array( 'status' => 400 )
			);
		}

		$term = get_term( (int) $result['term_id'], Constants::TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) {
			return new \WP_Error(
				'sponsor_manager_create_failed',
				__( 'The placement could not be created.', 'sponsor-manager' ),
				array( 'status' => 500 )
			);
		}

		update_term_meta( $term->term_id, PlacementUsage::META_ACTIVE, '0' );

		return rest_ensure_response( self::payload( $term, false ) );
	}

	/**
	 * Change the pretty name and keep the slug so assigned ads stay put.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rename_item( \WP_REST_Request $request ) {
		$slug = sanitize_title( (string) $request->get_param( 'slug' ) );
		$name = trim( (string) $request->get_param( 'name' ) );
		$term = get_term_by( 'slug', $slug, Constants::TAXONOMY );

		if ( ! $term || is_wp_error( $term ) ) {
			return new \WP_Error(
				'sponsor_manager_not_found',
				__( 'That placement could not be found.', 'sponsor-manager' ),
				array( 'status' => 404 )
			);
		}

		if ( Placements::is_protected( $term ) ) {
			return new \WP_Error(
				'sponsor_manager_protected',
				__( 'The Everywhere placement cannot be renamed.', 'sponsor-manager' ),
				array( 'status' => 403 )
			);
		}

		if ( '' === $name ) {
			return new \WP_Error(
				'sponsor_manager_missing_name',
				__( 'Enter a placement name.', 'sponsor-manager' ),
				array( 'status' => 400 )
			);
		}

		$other = self::term_by_name( $name );
		if ( $other && (int) $other->term_id !== (int) $term->term_id ) {
			return new \WP_Error(
				'sponsor_manager_name_exists',
				__( 'Another placement already uses that name.', 'sponsor-manager' ),
				array( 'status' => 400 )
			);
		}

		$updated = wp_update_term(
			$term->term_id,
			Constants::TAXONOMY,
			array(
				'name' => $name,
			)
		);
		if ( is_wp_error( $updated ) ) {
			return new \WP_Error(
				'sponsor_manager_rename_failed',
				$updated->get_error_message(),
				array( 'status' => 400 )
			);
		}

		$fresh = get_term( $term->term_id, Constants::TAXONOMY );
		return rest_ensure_response( self::payload( $fresh, false ) );
	}

	/**
	 * Placement name, eligible ad count, and a link to Sponsor Manager.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function summary( \WP_REST_Request $request ) {
		$slug = sanitize_key( (string) $request->get_param( 'slug' ) );
		$term = '' !== $slug ? get_term_by( 'slug', $slug, Constants::TAXONOMY ) : false;
		if ( ! $term || is_wp_error( $term ) ) {
			return new \WP_Error(
				'sponsor_manager_not_found',
				__( 'That Placement could not be found.', 'sponsor-manager' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response(
			array(
				'name'      => $term->name,
				'slug'      => $term->slug,
				'count'     => count( Query::get_ads( $term->slug ) ),
				'admin_url' => admin_url( 'admin.php?page=' . Constants::MENU_SLUG ),
			)
		);
	}

	/**
	 * @param string $name Pretty name.
	 * @return \WP_Term|null
	 */
	private static function term_by_name( $name ) {
		$term = get_term_by( 'name', $name, Constants::TAXONOMY );
		if ( $term && ! is_wp_error( $term ) ) {
			return $term;
		}

		return null;
	}

	/**
	 * @param \WP_Term $term    Placement.
	 * @param bool     $existed Whether the name was already taken.
	 * @return array{id:int,name:string,slug:string,placement_active:bool,existed:bool}
	 */
	private static function payload( $term, $existed ) {
		return array(
			'id'               => (int) $term->term_id,
			'name'             => $term->name,
			'slug'             => $term->slug,
			'placement_active' => PlacementUsage::is_active( $term ),
			'existed'          => (bool) $existed,
		);
	}
}
