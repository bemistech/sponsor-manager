<?php
/**
 * Which saved blocks still use a sponsor placement.
 *
 * @package SponsorManager
 */

namespace SponsorManager\Ads;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SponsorManager\Constants;

/**
 * Scans saved content and marks placements inactive when no block references them.
 */
class PlacementUsage {

	/**
	 * Term meta. "1" while a saved block uses the placement, otherwise "0".
	 */
	public const META_ACTIVE = 'placement_active';

	/**
	 * Block names that store a placement slug.
	 *
	 * @var string[]
	 */
	private const BLOCKS = array(
		'sponsor-manager/grid',
		'sponsor-manager/sidebar',
	);

	/**
	 * Hook save reconciliation and the editor field.
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_meta' ), 15 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_field' ) );
		add_action( 'wp_after_insert_post', array( __CLASS__, 'reconcile_saved_post' ), 10, 4 );
	}

	/**
	 * Store the active flag on the placement term.
	 */
	public static function register_meta() {
		register_term_meta(
			Constants::TAXONOMY,
			self::META_ACTIVE,
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_flag' ),
				'show_in_rest'      => false,
				'auth_callback'     => array( __CLASS__, 'can_read_meta' ),
			)
		);
	}

	/**
	 * Expose a boolean the block editor can group by.
	 */
	public static function register_rest_field() {
		register_rest_field(
			Constants::TAXONOMY,
			'placement_active',
			array(
				'get_callback' => array( __CLASS__, 'rest_active' ),
				'schema'       => array(
					'description' => __( 'Whether a saved block still uses this placement.', 'sponsor-manager' ),
					'type'        => 'boolean',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
			)
		);
	}

	/**
	 * @param string $value Raw meta.
	 * @return string
	 */
	public static function sanitize_flag( $value ) {
		return '0' === (string) $value ? '0' : '1';
	}

	/**
	 * @return bool
	 */
	public static function can_read_meta() {
		return current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ) || current_user_can( 'edit_theme_options' );
	}

	/**
	 * REST representation of the active flag.
	 *
	 * @param array $term Term prepared for REST.
	 * @return bool
	 */
	public static function rest_active( $term ) {
		if ( ! is_array( $term ) || empty( $term['id'] ) ) {
			return true;
		}

		$object = get_term( (int) $term['id'], Constants::TAXONOMY );
		return self::is_active( $object );
	}

	/**
	 * Missing meta stays active so existing placements keep working until a save says otherwise.
	 *
	 * @param \WP_Term|null $term Placement term.
	 * @return bool
	 */
	public static function is_active( $term ) {
		if ( ! $term || is_wp_error( $term ) ) {
			return false;
		}

		if ( Placements::is_protected( $term ) ) {
			return true;
		}

		return '0' !== (string) get_term_meta( $term->term_id, self::META_ACTIVE, true );
	}

	/**
	 * After a post, template, or pattern is saved, activate slugs it still uses
	 * and deactivate slugs that this save removed when nothing else uses them.
	 *
	 * @param int           $post_id     Post ID.
	 * @param \WP_Post      $post        Saved post.
	 * @param bool          $update      Whether this was an update.
	 * @param \WP_Post|null $post_before Previous post.
	 */
	public static function reconcile_saved_post( $post_id, $post, $update = false, $post_before = null ) {
		unset( $post_id, $update );

		if ( ! $post instanceof \WP_Post || 'revision' === $post->post_type ) {
			return;
		}

		$before = self::slugs_for_post( $post_before );
		$after  = self::slugs_for_post( $post );

		foreach ( $after as $slug ) {
			self::mark( $slug, true );
		}

		foreach ( array_diff( $before, $after ) as $slug ) {
			if ( ! self::slug_is_used( $slug ) ) {
				self::mark( $slug, false );
			}
		}
	}

	/**
	 * Refresh every placement's flag from current saved content.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public static function sync_all() {
		$usage = self::usage_map();
		$terms = get_terms(
			array(
				'taxonomy'   => Constants::TAXONOMY,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return $usage;
		}

		foreach ( $terms as $term ) {
			if ( Placements::is_protected( $term ) ) {
				self::write_flag( $term->term_id, true );
				continue;
			}

			self::write_flag( $term->term_id, ! empty( $usage[ $term->slug ] ) );
		}

		return $usage;
	}

	/**
	 * Pages, templates, and patterns whose saved content contains this slug.
	 *
	 * @param string $slug Placement slug.
	 * @return array<int, array<string, mixed>>
	 */
	public static function find_usages( $slug ) {
		$slug = sanitize_title( $slug );
		if ( '' === $slug || in_array( $slug, Placements::everywhere_slugs(), true ) ) {
			return array();
		}

		$map = self::usage_map();
		return isset( $map[ $slug ] ) ? $map[ $slug ] : array();
	}

	/**
	 * @param string $slug Placement slug.
	 * @return bool
	 */
	public static function slug_is_used( $slug ) {
		return ! empty( self::find_usages( $slug ) );
	}

	/**
	 * @param string $slug   Placement slug.
	 * @param bool   $active Whether a block still uses it.
	 */
	public static function mark( $slug, $active ) {
		$slug = sanitize_title( $slug );
		if ( '' === $slug || in_array( $slug, Placements::everywhere_slugs(), true ) ) {
			return;
		}

		$term = get_term_by( 'slug', $slug, Constants::TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}

		self::write_flag( $term->term_id, $active );
	}

	/**
	 * @param int  $term_id Term ID.
	 * @param bool $active  Active flag.
	 */
	private static function write_flag( $term_id, $active ) {
		$value   = $active ? '1' : '0';
		$current = get_term_meta( $term_id, self::META_ACTIVE, true );
		if ( (string) $current === $value ) {
			return;
		}

		update_term_meta( $term_id, self::META_ACTIVE, $value );
	}

	/**
	 * @param \WP_Post|null $post Post.
	 * @return string[]
	 */
	private static function slugs_for_post( $post ) {
		if ( ! $post instanceof \WP_Post ) {
			return array();
		}

		if ( in_array( $post->post_status, array( 'trash', 'auto-draft', 'inherit' ), true ) ) {
			return array();
		}

		return self::slugs_in_content( $post->post_content );
	}

	/**
	 * @param string $content Post content.
	 * @return string[]
	 */
	public static function slugs_in_content( $content ) {
		if ( ! is_string( $content ) || '' === $content || false === strpos( $content, 'sponsor-manager/' ) ) {
			return array();
		}

		$slugs = array();
		self::collect_slugs( parse_blocks( $content ), $slugs );

		return array_values( array_unique( $slugs ) );
	}

	/**
	 * @param array $blocks Parsed blocks.
	 * @param array $slugs  Collected slugs.
	 */
	private static function collect_slugs( array $blocks, array &$slugs ) {
		foreach ( $blocks as $block ) {
			$name = isset( $block['blockName'] ) ? $block['blockName'] : '';
			if ( in_array( $name, self::BLOCKS, true ) ) {
				$placement = isset( $block['attrs']['placement'] ) ? $block['attrs']['placement'] : '';
				$placement = is_string( $placement ) ? sanitize_title( $placement ) : '';
				if ( '' !== $placement ) {
					$slugs[] = $placement;
				}
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				self::collect_slugs( $block['innerBlocks'], $slugs );
			}
		}
	}

	/**
	 * One pass over saved content that contains a Sponsors block.
	 *
	 * Theme template files are not included until the template is saved in the Site Editor.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	private static function usage_map() {
		global $wpdb;

		$like = '%' . $wpdb->esc_like( 'sponsor-manager/' ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_type, post_content FROM {$wpdb->posts} WHERE post_type <> %s AND post_status NOT IN ('trash','auto-draft','inherit') AND post_content LIKE %s",
				'revision',
				$like
			)
		);

		$map = array();
		if ( ! is_array( $rows ) ) {
			return $map;
		}

		foreach ( $rows as $row ) {
			$post = get_post( $row->ID );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			foreach ( self::slugs_in_content( $post->post_content ) as $slug ) {
				if ( ! isset( $map[ $slug ] ) ) {
					$map[ $slug ] = array();
				}
				$map[ $slug ][ (int) $post->ID ] = self::usage_row( $post );
			}
		}

		foreach ( $map as $slug => $posts ) {
			$map[ $slug ] = array_values( $posts );
		}

		return $map;
	}

	/**
	 * @param \WP_Post $post Post that contains the block.
	 * @return array{id:int,title:string,type_label:string,edit_url:string}
	 */
	private static function usage_row( \WP_Post $post ) {
		$type       = get_post_type_object( $post->post_type );
		$type_label = ( $type && isset( $type->labels->singular_name ) ) ? $type->labels->singular_name : $post->post_type;
		$link       = get_edit_post_link( $post->ID, 'raw' );
		$title      = '' !== $post->post_title ? $post->post_title : __( '(no title)', 'sponsor-manager' );

		return array(
			'id'         => (int) $post->ID,
			'title'      => $title,
			'type_label' => $type_label,
			'edit_url'   => is_string( $link ) ? $link : '',
		);
	}
}
