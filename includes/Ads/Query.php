<?php
/**
 * Eligible ads for a placement.
 *
 * @package SponsorManager
 */

namespace SponsorManager\Ads;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SponsorManager\Constants;

/**
 * Query published ads that are in date, not paused, and assigned to a placement.
 */
class Query {

	/**
	 * Click-through URL. Prefers Sponsor URL, then legacy meta keys.
	 *
	 * @param int $post_id Ad ID.
	 * @return string
	 */
	public static function get_ad_url( $post_id ) {
		$url = get_post_meta( $post_id, 'sponsor_url', true );
		if ( empty( $url ) ) {
			$url = get_post_meta( $post_id, 'ad_link', true );
		}
		if ( empty( $url ) ) {
			$url = get_post_meta( $post_id, 'link', true );
		}

		return is_string( $url ) ? $url : '';
	}

	/**
	 * Eligible ads for a block placement, ordered by priority.
	 *
	 * @param string $placement_slug Placement term slug.
	 * @param array  $args           Optional limit.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_ads( $placement_slug, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'limit' => 0,
			)
		);

		$query = new \WP_Query(
			array(
				'post_type'      => Constants::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Ads are scoped to one placement (plus Everywhere).
				'tax_query'      => array(
					array(
						'taxonomy' => Constants::TAXONOMY,
						'field'    => 'slug',
						'terms'    => Placements::query_slugs( $placement_slug ),
					),
				),
			)
		);

		$today    = current_time( 'Ymd' );
		$eligible = array();

		foreach ( $query->posts as $post ) {
			if ( (bool) get_post_meta( $post->ID, Constants::PAUSE_META, true ) ) {
				continue;
			}

			$start = get_post_meta( $post->ID, 'sponsor_start_date', true );
			$end   = get_post_meta( $post->ID, 'sponsor_end_date', true );
			if ( $start && $today < $start ) {
				continue;
			}
			if ( $end && $today > $end ) {
				continue;
			}

			$priority = (int) get_post_meta( $post->ID, 'sponsor_priority', true );
			$priority = $priority > 0 ? $priority : 10;

			$link    = self::get_ad_url( $post->ID );
			$img_url = get_the_post_thumbnail_url( $post->ID, 'sponsor-ad' );
			if ( empty( $img_url ) ) {
				$img_url = get_the_post_thumbnail_url( $post->ID, 'large' );
			}

			/**
			 * Filter the image URL for an eligible ad.
			 *
			 * @param string $img_url Image URL.
			 * @param int    $post_id Ad ID.
			 */
			$img_url = apply_filters( 'sponsor_manager_ad_image_url', $img_url, $post->ID );

			$eligible[] = array(
				'id'       => $post->ID,
				'title'    => get_the_title( $post->ID ),
				'image'    => $img_url,
				'link'     => $link ? $link : '#',
				'priority' => $priority,
			);
		}

		if ( empty( $eligible ) ) {
			return array();
		}

		$tiers = array();
		foreach ( $eligible as $ad ) {
			$tiers[ $ad['priority'] ][] = $ad;
		}
		krsort( $tiers );
		$ordered = array();
		foreach ( $tiers as $tier ) {
			shuffle( $tier );
			foreach ( $tier as $ad ) {
				$ordered[] = $ad;
			}
		}

		if ( $args['limit'] > 0 ) {
			$ordered = array_slice( $ordered, 0, $args['limit'] );
		}

		return $ordered;
	}
}
