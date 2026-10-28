<?php
/**
 * Stored global card styles.
 *
 * @package SponsorManager
 */

namespace SponsorManager\Styles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SponsorManager\Constants;

/**
 * Option storage, sanitization, and CSS custom properties for ad cards.
 */
class Repository {

	/**
	 * Default border and shadow.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'follow_theme'    => true,
			'border_radius'   => 16,
			'border_width'    => 0,
			'border_color'    => '',
			'show_shadow'     => true,
			'shadow_color'    => '',
			'shadow_offset_x' => 5,
			'shadow_offset_y' => 5,
			'shadow_blur'     => 0,
			'shadow_spread'   => 0,
		);
	}

	/**
	 * Theme palette colors for pickers and admin preview.
	 *
	 * @return array<int, array{name:string,slug:string,css:string,hex:string}>
	 */
	public static function palette() {
		$palette = array();

		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			return $palette;
		}

		$groups = wp_get_global_settings( array( 'color', 'palette' ) );
		if ( ! is_array( $groups ) ) {
			return $palette;
		}

		$seen = array();
		foreach ( $groups as $origin ) {
			if ( ! is_array( $origin ) ) {
				continue;
			}

			$colors = isset( $origin['color'] ) || isset( $origin['slug'] ) ? array( $origin ) : $origin;
			foreach ( $colors as $color ) {
				if ( ! is_array( $color ) || empty( $color['slug'] ) || empty( $color['color'] ) ) {
					continue;
				}

				$slug = sanitize_title( $color['slug'] );
				if ( ! $slug || isset( $seen[ $slug ] ) ) {
					continue;
				}

				$seen[ $slug ] = true;
				$palette[]     = array(
					'name' => isset( $color['name'] ) ? (string) $color['name'] : $slug,
					'slug' => $slug,
					'css'  => 'var(--wp--preset--color--' . $slug . ')',
					'hex'  => (string) $color['color'],
				);
			}
		}

		return $palette;
	}

	/**
	 * Normalize a stored color to a safe CSS value.
	 *
	 * @param mixed $color Raw color.
	 * @return string
	 */
	public static function sanitize_color( $color ) {
		$color = trim( (string) $color );
		if ( '' === $color ) {
			return '';
		}

		if ( preg_match( '/^var:preset\|color\|([a-z0-9\-]+)$/i', $color, $matches ) ) {
			return 'var(--wp--preset--color--' . sanitize_title( $matches[1] ) . ')';
		}

		if ( preg_match( '/^var\(--wp--preset--color--[a-z0-9\-]+\)$/i', $color ) ) {
			return $color;
		}

		if ( sanitize_hex_color( $color ) ) {
			return $color;
		}

		if ( preg_match( '/^#([0-9a-fA-F]{4}|[0-9a-fA-F]{8})$/', $color ) ) {
			return $color;
		}

		if ( preg_match( '/^rgba?\(\s*[\d.]+\s*,\s*[\d.]+\s*,\s*[\d.]+(?:\s*,\s*[\d.]+)?\s*\)$/', $color ) ) {
			return $color;
		}

		return '';
	}

	/**
	 * @param string $color    Stored color.
	 * @param string $fallback CSS fallback.
	 * @return string
	 */
	public static function color_to_css( $color, $fallback = 'currentColor' ) {
		$color = self::sanitize_color( $color );
		return $color ? $color : $fallback;
	}

	/**
	 * Saved styles merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get() {
		$saved = get_option( Constants::STYLES_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return self::sanitize( wp_parse_args( $saved, self::defaults() ) );
	}

	/**
	 * @param mixed $input Raw styles.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();

		return array(
			'follow_theme'    => ! empty( $input['follow_theme'] ),
			'border_radius'   => max( 0, min( 32, (int) ( $input['border_radius'] ?? $defaults['border_radius'] ) ) ),
			'border_width'    => max( 0, min( 8, (int) ( $input['border_width'] ?? $defaults['border_width'] ) ) ),
			'border_color'    => self::sanitize_color( $input['border_color'] ?? '' ),
			'show_shadow'     => ! empty( $input['show_shadow'] ),
			'shadow_color'    => self::sanitize_color( $input['shadow_color'] ?? '' ),
			'shadow_offset_x' => max( -40, min( 40, (int) ( $input['shadow_offset_x'] ?? $defaults['shadow_offset_x'] ) ) ),
			'shadow_offset_y' => max( -40, min( 40, (int) ( $input['shadow_offset_y'] ?? $defaults['shadow_offset_y'] ) ) ),
			'shadow_blur'     => max( 0, min( 60, (int) ( $input['shadow_blur'] ?? $defaults['shadow_blur'] ) ) ),
			'shadow_spread'   => max( -20, min( 40, (int) ( $input['shadow_spread'] ?? $defaults['shadow_spread'] ) ) ),
		);
	}

	/**
	 * Nested custom properties so an active theme can supply the border.
	 *
	 * Town Square, the previous YeahSeven names, then theme.json custom values.
	 *
	 * @param array  $names    Custom property names, most specific first.
	 * @param string $fallback Literal CSS used when no theme token exists.
	 * @return string
	 */
	public static function theme_chain( array $names, $fallback ) {
		$css = $fallback;
		foreach ( array_reverse( $names ) as $name ) {
			$css = 'var(' . $name . ',' . $css . ')';
		}

		return $css;
	}

	/**
	 * @param array $styles      Style set.
	 * @param bool  $hover       Whether to nudge the shadow for hover.
	 * @param bool  $for_preview Resolve theme vars to hex for wp-admin.
	 * @return string
	 */
	public static function shadow_css( array $styles, $hover = false, $for_preview = false ) {
		if ( empty( $styles['show_shadow'] ) ) {
			return 'none';
		}

		$x = (int) $styles['shadow_offset_x'];
		$y = (int) $styles['shadow_offset_y'];
		$blur = (int) $styles['shadow_blur'];
		if ( $hover && 0 === $blur ) {
			return '0 0 0 2px #000';
		} elseif ( $hover ) {
			$y += ( $y >= 0 ) ? 4 : -4;
		}

		$custom_color = self::sanitize_color( $styles['shadow_color'] ?? '' );
		$shared_rest  = 5 === (int) $styles['shadow_offset_x'] && 5 === (int) $styles['shadow_offset_y'] && 0 === $blur && 0 === (int) $styles['shadow_spread'] && '' === $custom_color;
		if ( $shared_rest && ! $for_preview ) {
			return $hover
				? self::theme_chain( array( '--tsq-shadow-hover', '--yst-shadow-hover', '--wp--custom--shadow-hover' ), '0 0 0 2px #000' )
				: self::theme_chain( array( '--tsq-shadow', '--yst-shadow', '--wp--custom--shadow' ), '5px 5px 0 0 #102441' );
		}

		$fallback = $hover
			? self::theme_chain( array( '--tsq-shadow-hover-color', '--yst-shadow-hover-color', '--wp--custom--shadow-hover-color' ), '#1763df' )
			: self::theme_chain( array( '--tsq-shadow-color', '--yst-shadow-color', '--wp--custom--shadow-color' ), '#102441' );
		if ( $blur > 0 && '' === $custom_color ) {
			$fallback = '#00000061';
		}
		if ( $for_preview ) {
			$color = self::preview_color( $styles['shadow_color'] ?? '' );
			if ( '' === $color || 'currentColor' === $color ) {
				$color = ( 0 === $blur ) ? ( $hover ? '#1763df' : '#102441' ) : '#00000061';
			}
		} else {
			$color = self::color_to_css( $styles['shadow_color'] ?? '', $fallback );
		}

		return sprintf(
			'%dpx %dpx %dpx %dpx %s',
			$x,
			$y,
			(int) $styles['shadow_blur'],
			(int) $styles['shadow_spread'],
			$color
		);
	}

	/**
	 * Resolve a theme preset to hex for the Styles tab preview.
	 *
	 * @param string $color Stored color.
	 * @return string
	 */
	public static function preview_color( $color ) {
		$color = self::sanitize_color( $color );
		if ( '' === $color ) {
			return 'currentColor';
		}

		foreach ( self::palette() as $item ) {
			if ( $color === $item['css'] && ! empty( $item['hex'] ) ) {
				return $item['hex'];
			}
		}

		return $color;
	}

	/**
	 * Inline palette custom properties for the admin preview.
	 *
	 * @return string
	 */
	public static function palette_css() {
		$parts = array();
		foreach ( self::palette() as $item ) {
			if ( empty( $item['slug'] ) || empty( $item['hex'] ) ) {
				continue;
			}
			$parts[] = '--wp--preset--color--' . $item['slug'] . ':' . $item['hex'];
		}

		return implode( ';', $parts );
	}

	/**
	 * CSS custom-property string for a style set.
	 *
	 * @param array $styles      Style set.
	 * @param bool  $for_preview Use hex for theme colors.
	 * @return string
	 */
	public static function to_css( array $styles, $for_preview = false ) {
		$styles = self::sanitize( $styles );
		$defaults = self::defaults();
		if ( ! empty( $styles['follow_theme'] ) && ! $for_preview ) {
			$vars = array(
				'--sponsor-ad-radius'       => self::theme_chain( array( '--tsq-radius', '--yst-radius', '--wp--custom--radius' ), '16px' ),
				'--sponsor-ad-border-width' => $defaults['border_width'] . 'px',
				'--sponsor-ad-border-color' => self::theme_chain( array( '--tsq-shadow-color', '--yst-shadow-color', '--wp--custom--shadow-color' ), '#102441' ),
				'--sponsor-ad-border-hover' => self::theme_chain( array( '--tsq-shadow-hover-color', '--yst-shadow-hover-color', '--wp--custom--shadow-hover-color' ), '#1763df' ),
				'--sponsor-ad-shadow'       => self::theme_chain( array( '--tsq-shadow', '--yst-shadow', '--wp--custom--shadow' ), '5px 5px 0 0 #102441' ),
				'--sponsor-ad-shadow-hover' => self::theme_chain( array( '--tsq-shadow-hover', '--yst-shadow-hover', '--wp--custom--shadow-hover' ), '0 0 0 2px #000' ),
				'--sponsor-ad-hover-shift'  => 'none',
			);
			$parts = array();
			foreach ( $vars as $property => $value ) {
				$parts[] = $property . ':' . $value;
			}

			return implode( ';', $parts );
		}

		$border = self::sanitize_color( $styles['border_color'] );
		$theme_border = self::theme_chain( array( '--tsq-shadow-color', '--yst-shadow-color', '--wp--custom--shadow-color' ), '#102441' );
		$theme_border_hover = self::theme_chain( array( '--tsq-shadow-hover-color', '--yst-shadow-hover-color', '--wp--custom--shadow-hover-color' ), '#1763df' );
		$border_css = $for_preview
			? self::preview_color( $styles['border_color'] )
			: self::color_to_css( $styles['border_color'], $theme_border );
		if ( $for_preview && ( '' === $border_css || 'currentColor' === $border_css ) ) {
			$border_css = '#102441';
		}
		$border_hover = '' === $border
			? ( $for_preview ? '#1763df' : $theme_border_hover )
			: $border_css;
		$radius = ( ! $for_preview && (int) $styles['border_radius'] === (int) $defaults['border_radius'] )
			? self::theme_chain( array( '--tsq-radius', '--yst-radius', '--wp--custom--radius' ), '16px' )
			: $styles['border_radius'] . 'px';

		$vars = array(
			'--sponsor-ad-radius'         => $radius,
			'--sponsor-ad-border-width'   => $styles['border_width'] . 'px',
			'--sponsor-ad-border-color'   => $border_css,
			'--sponsor-ad-border-hover'   => $border_hover,
			'--sponsor-ad-shadow'         => self::shadow_css( $styles, false, $for_preview ),
			'--sponsor-ad-shadow-hover'   => self::shadow_css( $styles, true, $for_preview ),
			'--sponsor-ad-hover-shift'    => 'none',
		);

		$parts = array();
		foreach ( $vars as $property => $value ) {
			$parts[] = $property . ':' . $value;
		}

		return implode( ';', $parts );
	}

	/**
	 * Persist styles from the dashboard form.
	 *
	 * @param array $input Raw POST data.
	 */
	public static function save( array $input ) {
		update_option( Constants::STYLES_OPTION, self::sanitize( $input ) );
	}
}
