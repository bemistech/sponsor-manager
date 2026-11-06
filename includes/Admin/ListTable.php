<?php
/**
 * Sponsor Ad list table columns.
 *
 * @package SponsorManager
 */

namespace SponsorManager\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SponsorManager\Ads\Status;
use SponsorManager\Constants;

/**
 * Extra columns on the All Sponsor Ads screen.
 */
class ListTable {

	/**
	 * Register list table hooks.
	 */
	public static function register() {
		add_filter( 'manage_' . Constants::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . Constants::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
		add_filter( 'manage_edit-' . Constants::POST_TYPE . '_sortable_columns', array( __CLASS__, 'sortable' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'orderby_end_date' ) );
	}

	/**
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['sponsor_name']  = __( 'Sponsor', 'sponsor-manager' );
				$new['placements']    = __( 'Placement(s)', 'sponsor-manager' );
				$new['status']        = __( 'Status', 'sponsor-manager' );
				$new['sponsor_dates'] = __( 'Runs', 'sponsor-manager' );
			}
		}
		return $new;
	}

	/**
	 * @param string $column  Column key.
	 * @param int    $post_id Ad ID.
	 */
	public static function column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'sponsor_name':
				echo esc_html( get_post_meta( $post_id, 'sponsor_name', true ) ?: '—' );
				break;

			case 'placements':
				$terms = get_the_terms( $post_id, Constants::TAXONOMY );
				if ( $terms && ! is_wp_error( $terms ) ) {
					echo esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) );
				} else {
					echo '<em>' . esc_html__( 'none assigned', 'sponsor-manager' ) . '</em>';
				}
				break;

			case 'status':
				$status = Status::refresh( $post_id );
				$colors = array(
					'active'    => '#1a7f37',
					'scheduled' => '#9a6700',
					'expired'   => '#cf222e',
					'paused'    => '#57606a',
					'draft'     => '#57606a',
				);
				$color = $colors[ $status ] ?? '#57606a';
				printf(
					'<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:%1$s;color:#fff;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.03em;">%2$s</span>',
					esc_attr( $color ),
					esc_html( $status )
				);
				break;

			case 'sponsor_dates':
				$start = get_post_meta( $post_id, 'sponsor_start_date', true );
				$end   = get_post_meta( $post_id, 'sponsor_end_date', true );
				$fmt   = static function ( $d ) {
					return $d ? date_i18n( 'M j, Y', strtotime( $d ) ) : '—';
				};
				echo esc_html( $fmt( $start ) . ' – ' . $fmt( $end ) );
				break;
		}
	}

	/**
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public static function sortable( $columns ) {
		$columns['sponsor_dates'] = 'sponsor_end_date';
		return $columns;
	}

	/**
	 * Sort by end date meta when requested.
	 *
	 * @param \WP_Query $query Query.
	 */
	public static function orderby_end_date( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( Constants::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		if ( 'sponsor_end_date' === $query->get( 'orderby' ) ) {
			$query->set( 'meta_key', 'sponsor_end_date' );
			$query->set( 'orderby', 'meta_value' );
		}
	}
}
