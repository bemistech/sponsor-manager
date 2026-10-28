<?php
/**
 * Markup and weighted picks used by dynamic blocks.
 *
 * @package SponsorManager
 */

namespace SponsorManager\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SponsorManager\Ads\Query;
use SponsorManager\Styles\Repository;

/**
 * Shared rendering helpers for the sponsors block.
 */
class Renderer {

	/**
	 * Pick one ad using priority-weighted random selection.
	 *
	 * @param array $ads         Ad arrays with id + priority.
	 * @param int[] $exclude_ids IDs to skip.
	 * @return array|null
	 */
	public static function weighted_pick( array $ads, array $exclude_ids = array() ) {
		$pool = array_values(
			array_filter(
				$ads,
				function ( $ad ) use ( $exclude_ids ) {
					return ! in_array( (int) $ad['id'], $exclude_ids, true );
				}
			)
		);

		if ( empty( $pool ) ) {
			return null;
		}

		$total_weight = 0;
		foreach ( $pool as $ad ) {
			$total_weight += max( 1, (int) $ad['priority'] );
		}

		$roll = ( wp_rand( 0, PHP_INT_MAX ) / PHP_INT_MAX ) * $total_weight;
		foreach ( $pool as $ad ) {
			$roll -= max( 1, (int) $ad['priority'] );
			if ( $roll <= 0 ) {
				return $ad;
			}
		}

		return $pool[ count( $pool ) - 1 ];
	}

	/**
	 * Pick several unique ads via weighted random selection.
	 *
	 * @param array $ads         Ad pool.
	 * @param int   $count       How many to pick.
	 * @param int[] $exclude_ids Starting exclusions.
	 * @return array
	 */
	public static function weighted_pick_multiple( array $ads, $count, array $exclude_ids = array() ) {
		$picked      = array();
		$exclude_ids = array_map( 'intval', $exclude_ids );

		for ( $i = 0; $i < $count; $i++ ) {
			$ad = self::weighted_pick( $ads, $exclude_ids );
			if ( ! $ad ) {
				break;
			}
			$picked[]      = $ad;
			$exclude_ids[] = (int) $ad['id'];
		}

		return $picked;
	}

	/**
	 * Horizontal row or vertical stack.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function layout( array $attributes ) {
		$orientation = isset( $attributes['orientation'] ) ? $attributes['orientation'] : '';
		if ( ! is_string( $orientation ) || '' === $orientation ) {
			$legacy = $attributes['layout'] ?? '';
			$orientation = is_string( $legacy ) ? $legacy : '';
		}

		return 'vertical' === $orientation ? 'vertical' : 'horizontal';
	}

	/**
	 * How many ads to show. Falls back to legacy columns × rows.
	 *
	 * @param array $attributes Block attributes.
	 * @return int
	 */
	public static function max_items( array $attributes ) {
		$max = isset( $attributes['maxItems'] ) ? (int) $attributes['maxItems'] : 0;
		if ( $max > 0 ) {
			return max( 1, min( 24, $max ) );
		}

		$columns = isset( $attributes['columns'] ) ? (int) $attributes['columns'] : 4;
		$rows    = isset( $attributes['rows'] ) ? (int) $attributes['rows'] : 1;
		$columns = max( 1, min( 6, $columns ) );
		$rows    = max( 1, min( 4, $rows ) );

		return max( 1, min( 24, $columns * $rows ) );
	}

	/**
	 * Front-end markup for the unified sponsors block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_block( array $attributes ) {
		$placement = isset( $attributes['placement'] ) ? sanitize_title( $attributes['placement'] ) : '';
		if ( ! $placement ) {
			return '';
		}

		$all_ads = Query::get_ads( $placement );
		if ( empty( $all_ads ) ) {
			return '';
		}

		$layout      = self::layout( $attributes );
		$max_items   = self::max_items( $attributes );
		$slot_count  = min( $max_items, count( $all_ads ) );
		$visible     = self::weighted_pick_multiple( $all_ads, $slot_count );
		$can_rotate  = count( $all_ads ) > $slot_count;
		$visual_cols = 'vertical' === $layout ? 1 : max( 1, min( 6, $slot_count ) );
		$mobile_cols = ( 'vertical' === $layout || $visual_cols < 2 ) ? 1 : min( 2, $visual_cols );

		$style = self::style_attribute( $attributes );
		$style = trim(
			$style . ';--sponsor-grid-columns:' . $visual_cols . ';--sponsor-grid-mobile-columns:' . $mobile_cols,
			';'
		);

		$classes = 'sponsor-manager-grid sponsor-manager-grid--' . $layout;
		if ( $can_rotate ) {
			$classes .= ' sponsor-manager-grid--rotating';
		}

		$wrapper_attrs = get_block_wrapper_attributes(
			array(
				'class'                    => $classes,
				'style'                    => $style,
				'role'                     => 'region',
				'aria-label'               => __( 'Sponsors', 'sponsor-manager' ),
				'data-sponsor-rotate'      => $can_rotate ? '1' : '0',
				'data-sponsor-initial-ms'  => '120000',
				'data-sponsor-interval-ms' => '120000',
				'data-sponsor-new-tab'     => __( '(opens in a new tab)', 'sponsor-manager' ),
			)
		);

		if ( $can_rotate ) {
			$wrapper_attrs .= ' data-sponsor-pool="' . esc_attr( wp_json_encode( self::ads_for_client( $all_ads ) ) ) . '"';
		}

		ob_start();
		?>
		<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="sponsor-manager-grid__slots">
				<?php foreach ( $visible as $ad ) : ?>
					<div class="sponsor-manager-grid__slot" data-ad-id="<?php echo esc_attr( (string) $ad['id'] ); ?>">
						<?php echo self::ad_markup( $ad ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a single sponsor ad link/image.
	 *
	 * @param array  $ad     Ad data.
	 * @param string $layout Unused; kept for existing callers.
	 * @return string
	 */
	public static function ad_markup( array $ad, $layout = 'grid' ) {
		unset( $layout );

		$title = isset( $ad['title'] ) ? trim( (string) $ad['title'] ) : '';
		if ( '' === $title ) {
			$title = __( 'Sponsor', 'sponsor-manager' );
		}

		ob_start();
		?>
		<a
			class="sponsor-manager-grid__item"
			href="<?php echo esc_url( $ad['link'] ); ?>"
			target="_blank"
			rel="noopener noreferrer sponsored"
		>
			<?php if ( ! empty( $ad['image'] ) ) : ?>
				<img
					class="sponsor-manager-grid__item-image"
					src="<?php echo esc_url( $ad['image'] ); ?>"
					alt="<?php echo esc_attr( $title ); ?>"
					width="16"
					height="9"
					loading="lazy"
					decoding="async"
				/>
			<?php else : ?>
				<span class="sponsor-manager-grid__item-fallback"><?php echo esc_html( $title ); ?></span>
			<?php endif; ?>
			<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'sponsor-manager' ); ?></span>
		</a>
		<?php
		return ob_get_clean();
	}

	/**
	 * Inline style attribute when the block opts out of global styles.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function style_attribute( array $attributes ) {
		$use_global = ! array_key_exists( 'useGlobalStyles', $attributes ) || ! empty( $attributes['useGlobalStyles'] );
		if ( $use_global ) {
			return '';
		}

		$globals = Repository::get();

		return Repository::to_css(
			array(
				'border_radius'   => array_key_exists( 'borderRadius', $attributes ) ? $attributes['borderRadius'] : $globals['border_radius'],
				'border_width'    => array_key_exists( 'borderWidth', $attributes ) ? $attributes['borderWidth'] : $globals['border_width'],
				'border_color'    => $attributes['borderColor'] ?? $globals['border_color'],
				'show_shadow'     => array_key_exists( 'showShadow', $attributes ) ? ! empty( $attributes['showShadow'] ) : $globals['show_shadow'],
				'shadow_color'    => $attributes['shadowColor'] ?? $globals['shadow_color'],
				'shadow_offset_x' => array_key_exists( 'shadowOffsetX', $attributes ) ? $attributes['shadowOffsetX'] : $globals['shadow_offset_x'],
				'shadow_offset_y' => array_key_exists( 'shadowOffsetY', $attributes ) ? $attributes['shadowOffsetY'] : $globals['shadow_offset_y'],
				'shadow_blur'     => array_key_exists( 'shadowBlur', $attributes ) ? $attributes['shadowBlur'] : $globals['shadow_blur'],
				'shadow_spread'   => array_key_exists( 'shadowSpread', $attributes ) ? $attributes['shadowSpread'] : $globals['shadow_spread'],
			)
		);
	}

	/**
	 * Payload for the grid rotation script.
	 *
	 * @param array $ads Ads.
	 * @return array
	 */
	public static function ads_for_client( array $ads ) {
		return array_map(
			function ( $ad ) {
				$title = isset( $ad['title'] ) ? trim( (string) $ad['title'] ) : '';
				return array(
					'id'       => (int) $ad['id'],
					'title'    => $title,
					'image'    => $ad['image'],
					'link'     => $ad['link'],
					'priority' => (int) $ad['priority'],
				);
			},
			$ads
		);
	}
}
