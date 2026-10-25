<?php
/**
 * Dashboard page chrome, overview, inventory, and WP dashboard widget.
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
 * Renders the Sponsor Manager dashboard and needs-attention widget.
 */
class Dashboard {

	/**
	 * Form handlers live in dashboard.php until those screens are class methods.
	 */
	public static function register() {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register_widget' ) );
	}

	/**
	 * WP Admin dashboard widget.
	 */
	public static function register_widget() {
		wp_add_dashboard_widget(
			'sponsor_manager_alerts',
			__( 'Sponsor Manager — Needs Attention', 'sponsor-manager' ),
			array( __CLASS__, 'render_widget' )
		);
	}

	/**
	 * Dashboard URL helper.
	 *
	 * @param array $args Query args.
	 * @return string
	 */
	public static function url( $args = array() ) {
		return add_query_arg(
			array_merge(
				array( 'page' => Constants::MENU_SLUG ),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Ad title plus company.
	 *
	 * @param \WP_Post|int $post Ad.
	 * @return string
	 */
	public static function booking_label( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return '';
		}

		$title   = get_the_title( $post );
		$company = trim( (string) get_post_meta( $post->ID, 'sponsor_name', true ) );

		if ( $title && $company && 0 !== strcasecmp( $title, $company ) ) {
			return $title . ' — ' . $company;
		}

		return $title ? $title : $company;
	}

	/**
	 * Main plugin page with tabs.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'sponsor-manager' ) );
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $tab, array( 'overview', 'ads', 'placements', 'styles', 'license', 'how-to-use' ), true ) ) {
			$tab = 'overview';
		}
		if ( 'license' === $tab && ! current_user_can( 'manage_options' ) ) {
			$tab = 'overview';
		}

		$base_url = admin_url( 'admin.php?page=' . Constants::MENU_SLUG );
		$tabs     = array(
			'overview'   => __( 'Overview', 'sponsor-manager' ),
			'ads'        => __( 'Ads', 'sponsor-manager' ),
			'placements' => __( 'Placements', 'sponsor-manager' ),
			'styles'     => __( 'Styles', 'sponsor-manager' ),
			'how-to-use' => __( 'How to Use', 'sponsor-manager' ),
		);
		if ( current_user_can( 'manage_options' ) ) {
			$tabs['license'] = __( 'License', 'sponsor-manager' );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Sponsor Manager', 'sponsor-manager' ) . '</h1>';
		sponsor_manager_render_dashboard_notices();

		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			printf(
				'<a href="%1$s" class="nav-tab %2$s">%3$s</a>',
				esc_url( 'overview' === $slug ? $base_url : add_query_arg( 'tab', $slug, $base_url ) ),
				$tab === $slug ? 'nav-tab-active' : '',
				esc_html( $label )
			);
		}
		echo '</h2>';

		if ( 'how-to-use' === $tab ) {
			self::render_how_to_use();
		} elseif ( 'styles' === $tab ) {
			StylesPage::render();
		} elseif ( 'license' === $tab ) {
			LicensePage::render();
		} elseif ( 'ads' === $tab ) {
			sponsor_manager_render_ads_tab();
		} elseif ( 'placements' === $tab ) {
			sponsor_manager_render_placements_tab();
		} else {
			self::render_overview();
		}

		echo '</div>';
	}

	/**
	 * Overview stats and inventory.
	 */
	public static function render_overview() {
		$stats = self::stats();
		$cards = array(
			array( 'label' => __( 'Active Ads', 'sponsor-manager' ), 'value' => $stats['active_ads'] ),
			array( 'label' => __( 'Total Ads', 'sponsor-manager' ), 'value' => $stats['total_ads'] ),
			array( 'label' => __( 'Total Placements', 'sponsor-manager' ), 'value' => $stats['total_placements'] ),
		);

		echo '<div style="display:flex;gap:16px;margin:20px 0;flex-wrap:wrap;">';
		foreach ( $cards as $card ) {
			printf(
				'<div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 24px;min-width:160px;">
					<div style="font-size:28px;font-weight:600;line-height:1.2;">%1$s</div>
					<div style="color:#646970;">%2$s</div>
				</div>',
				esc_html( (string) $card['value'] ),
				esc_html( $card['label'] )
			);
		}
		echo '</div>';

		sponsor_manager_render_overview_actions();

		echo '<h2>' . esc_html__( 'Ad Inventory', 'sponsor-manager' ) . '</h2>';
		echo '<p>' . esc_html__( "What's booked and what's open to sell, by placement.", 'sponsor-manager' ) . '</p>';
		self::render_inventory_table();
	}

	/**
	 * How to Use copy.
	 */
	public static function render_how_to_use() {
		echo '<div style="max-width:760px;margin-top:20px;">';
		echo '<h2>' . esc_html__( 'How Sponsor Manager Works', 'sponsor-manager' ) . '</h2>';
		echo '<ol style="list-style:decimal;margin-left:1.2em;">
			<li><strong>Create a Sponsor Ad</strong> from the Ads tab (or Overview → Add New Ad). Set the ad name, company, image, Sponsor URL, and placements.</li>
			<li><strong>Create a Placement</strong> from the Placements tab. A specific placement also includes ads assigned to <em>Everywhere</em>. Use Everywhere for ads that should appear in every block.</li>
			<li><strong>Schedule it.</strong> Leave the start date blank to go live immediately, and the end date blank to run indefinitely. The ad moves through Scheduled → Active → Expired automatically based on today\'s date — no manual cleanup required.</li>
			<li><strong>Set a priority</strong> to control ordering when a placement has more than one active sponsor; higher numbers are shown first (and more often), with random rotation among ties.</li>
			<li><strong>Target by category</strong> (optional) by assigning post categories to the ad, the same as you would a normal post — it will then only serve on single posts/pages within those categories. Leave it uncategorized to serve everywhere.</li>
			<li><strong>Pause anytime</strong> with the "Paused (manual override)" checkbox to instantly pull an ad from rotation, regardless of its scheduled dates.</li>
			<li><strong>Place ads on the site</strong> using the <em>Sponsors</em> block. Choose a placement, horizontal or vertical, and how many ads to show. Extra ads rotate in every two minutes (oldest slots first). The block is in the inserter under Widgets.</li>
			<li><strong>Set global styles</strong> on the Styles tab (border radius, border, shadow). Individual blocks use those defaults unless you turn on a per-block override.</li>
		</ol>';
		echo '<h2>' . esc_html__( 'Where to Check Status', 'sponsor-manager' ) . '</h2>';
		echo '<ul style="list-style:disc;margin-left:1.2em;">
			<li>The <strong>Overview</strong> tab shows current totals and what\'s open to sell, by placement. Use Ads and Placements to create or edit without leaving the dashboard.</li>
			<li>The <strong>Ads</strong> tab lists each ad name next to its company, status, and run dates.</li>
			<li>The dashboard widget ("Sponsor Manager — Needs Attention") flags anything expiring within 7 days, or still published past its end date.</li>
			<li>Plugin updates come from GitHub Releases. When a new version is published, WordPress will offer it on the Plugins screen like any other plugin.</li>
		</ul>';
		echo '</div>';
	}

	/**
	 * @return array{active_ads:int,total_ads:int,total_placements:int}
	 */
	public static function stats() {
		$ad_ids = get_posts(
			array(
				'post_type'      => Constants::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$active = 0;
		foreach ( $ad_ids as $ad_id ) {
			if ( 'active' === Status::refresh( $ad_id ) ) {
				++$active;
			}
		}

		$placement_count = wp_count_terms( array( 'taxonomy' => Constants::TAXONOMY, 'hide_empty' => false ) );

		return array(
			'active_ads'       => $active,
			'total_ads'        => count( $ad_ids ),
			'total_placements' => is_wp_error( $placement_count ) ? 0 : (int) $placement_count,
		);
	}

	/**
	 * Booked vs open inventory by placement.
	 */
	public static function render_inventory_table() {
		$terms = get_terms( array( 'taxonomy' => Constants::TAXONOMY, 'hide_empty' => false ) );
		$today = current_time( 'Ymd' );

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Placement', 'sponsor-manager' ) . '</th><th>' . esc_html__( 'Current / Upcoming Bookings', 'sponsor-manager' ) . '</th><th>' . esc_html__( 'Availability', 'sponsor-manager' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			echo '<tr><td colspan="3">' . esc_html__( 'No sponsor_placement terms found.', 'sponsor-manager' ) . '</td></tr>';
		} else {
			foreach ( $terms as $term ) {
				$query = new \WP_Query(
					array(
						'post_type'      => Constants::POST_TYPE,
						'post_status'    => 'publish',
						'posts_per_page' => -1,
						'no_found_rows'  => true,
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Inventory is grouped per placement term.
						'tax_query'      => array(
							array(
								'taxonomy' => Constants::TAXONOMY,
								'field'    => 'term_id',
								'terms'    => $term->term_id,
							),
						),
					)
				);

				$rows           = array();
				$latest_end     = null;
				$has_open_ended = false;

				foreach ( $query->posts as $post ) {
					if ( (bool) get_post_meta( $post->ID, Constants::PAUSE_META, true ) ) {
						continue;
					}
					$end = get_post_meta( $post->ID, 'sponsor_end_date', true );
					if ( $end && $end < $today ) {
						continue;
					}
					$rows[] = sprintf(
						'<a href="%s">%s</a>%s',
						esc_url( self::url( array( 'tab' => 'ads', 'ad' => $post->ID ) ) ),
						esc_html( self::booking_label( $post ) ),
						$end ? ' (through ' . esc_html( date_i18n( 'M j, Y', strtotime( $end ) ) ) . ')' : ' (no end date)'
					);
					if ( ! $end ) {
						$has_open_ended = true;
					} elseif ( ! $latest_end || $end > $latest_end ) {
						$latest_end = $end;
					}
				}

				if ( $has_open_ended ) {
					$availability = '<strong style="color:#cf222e;">' . esc_html__( 'Booked (indefinite)', 'sponsor-manager' ) . '</strong>';
				} elseif ( $latest_end ) {
					$availability = '<strong style="color:#9a6700;">' . esc_html( sprintf( /* translators: %s: date */ __( 'Booked through %s', 'sponsor-manager' ), date_i18n( 'M j, Y', strtotime( $latest_end ) ) ) ) . '</strong>';
				} else {
					$availability = '<strong style="color:#1a7f37;">' . esc_html__( 'OPEN — available to sell', 'sponsor-manager' ) . '</strong>';
				}

				$bookings = $rows ? implode( '<br>', $rows ) : '<em>' . esc_html__( 'none', 'sponsor-manager' ) . '</em>';
				$allowed  = array(
					'a'      => array( 'href' => true ),
					'br'     => array(),
					'em'     => array(),
					'strong' => array( 'style' => true ),
				);

				printf(
					'<tr><td><a href="%s">%s</a></td><td>%s</td><td>%s</td></tr>',
					esc_url( self::url( array( 'tab' => 'placements', 'placement' => $term->term_id ) ) ),
					esc_html( $term->name ),
					wp_kses( $bookings, $allowed ),
					wp_kses( $availability, $allowed )
				);
			}
		}

		echo '</tbody></table>';
	}

	/**
	 * Expired / expiring ads widget.
	 */
	public static function render_widget() {
		$ads = get_posts(
			array(
				'post_type'      => Constants::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$today    = current_time( 'Ymd' );
		$soon     = gmdate( 'Ymd', strtotime( '+7 days' ) );
		$expiring = array();
		$expired  = array();

		foreach ( $ads as $ad ) {
			$end = get_post_meta( $ad->ID, 'sponsor_end_date', true );
			if ( ! $end ) {
				continue;
			}
			if ( $end < $today ) {
				$expired[] = $ad;
			} elseif ( $end <= $soon ) {
				$expiring[] = $ad;
			}
		}

		if ( ! $expiring && ! $expired ) {
			echo '<p>' . esc_html__( 'All sponsor placements are current — nothing expiring in the next 7 days.', 'sponsor-manager' ) . '</p>';
			return;
		}

		if ( $expired ) {
			echo '<p><strong style="color:#cf222e;">' . esc_html__( 'Expired but still published:', 'sponsor-manager' ) . '</strong></p><ul style="margin-left:1em;list-style:disc;">';
			foreach ( $expired as $ad ) {
				printf(
					'<li><a href="%s">%s</a> — ended %s</li>',
					esc_url( self::url( array( 'tab' => 'ads', 'ad' => $ad->ID ) ) ),
					esc_html( self::booking_label( $ad ) ),
					esc_html( date_i18n( 'M j, Y', strtotime( get_post_meta( $ad->ID, 'sponsor_end_date', true ) ) ) )
				);
			}
			echo '</ul>';
		}

		if ( $expiring ) {
			echo '<p><strong style="color:#9a6700;">' . esc_html__( 'Expiring within 7 days:', 'sponsor-manager' ) . '</strong></p><ul style="margin-left:1em;list-style:disc;">';
			foreach ( $expiring as $ad ) {
				printf(
					'<li><a href="%s">%s</a> — ends %s</li>',
					esc_url( self::url( array( 'tab' => 'ads', 'ad' => $ad->ID ) ) ),
					esc_html( self::booking_label( $ad ) ),
					esc_html( date_i18n( 'M j, Y', strtotime( get_post_meta( $ad->ID, 'sponsor_end_date', true ) ) ) )
				);
			}
			echo '</ul>';
		}
	}
}
