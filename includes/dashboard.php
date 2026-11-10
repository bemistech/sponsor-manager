<?php
/**
 * Ads and Placements tab CRUD for the dashboard.
 *
 * @package SponsorManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_init', 'sponsor_manager_handle_dashboard_actions' );
/**
 * Route Ads and Placements form submissions.
 */
function sponsor_manager_handle_dashboard_actions() {
	if ( ! is_admin() ) {
		return;
	}

	sponsor_manager_save_ad_from_dashboard();
	sponsor_manager_save_placement_from_dashboard();
	sponsor_manager_trash_ad_from_dashboard();
	sponsor_manager_delete_placement_from_dashboard();
}

/**
 * Whether a placement term is Everywhere (not deletable).
 *
 * @param \WP_Term|null $term Term.
 * @return bool
 */
function sponsor_manager_is_protected_placement( $term ) {
	return \SponsorManager\Ads\Placements::is_protected( $term );
}

/**
 * Linked list of pages and templates that contain a placement.
 *
 * @param array<int, array<string, mixed>> $used Usage rows.
 * @return string
 */
function sponsor_manager_placement_usage_html( $used ) {
	if ( empty( $used ) ) {
		return esc_html__( 'Not used by a block', 'sponsor-manager' );
	}

	$links = array();
	foreach ( $used as $row ) {
		$label = sprintf(
			/* translators: 1: post title, 2: post type singular name. */
			__( '%1$s (%2$s)', 'sponsor-manager' ),
			$row['title'],
			$row['type_label']
		);
		if ( ! empty( $row['edit_url'] ) ) {
			$links[] = '<a href="' . esc_url( $row['edit_url'] ) . '">' . esc_html( $label ) . '</a>';
		} else {
			$links[] = esc_html( $label );
		}
	}

	return implode( ', ', $links );
}

/**
 * Convert stored Ymd to an HTML date input value.
 *
 * @param string $value Ymd date.
 * @return string
 */
function sponsor_manager_ymd_to_input( $value ) {
	$value = preg_replace( '/[^0-9]/', '', (string) $value );
	if ( 8 !== strlen( $value ) ) {
		return '';
	}

	return substr( $value, 0, 4 ) . '-' . substr( $value, 4, 2 ) . '-' . substr( $value, 6, 2 );
}

/**
 * Convert an HTML date input to stored Ymd.
 *
 * @param string $value YYYY-MM-DD.
 * @return string
 */
function sponsor_manager_input_to_ymd( $value ) {
	$value = sanitize_text_field( (string) $value );
	if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches ) ) {
		return $matches[1] . $matches[2] . $matches[3];
	}

	return '';
}

/**
 * Create or update an ad from the Ads tab.
 */
function sponsor_manager_save_ad_from_dashboard() {
	if ( ! isset( $_POST['sponsor_manager_save_ad'] ) ) {
		return;
	}

	check_admin_referer( 'sponsor_manager_save_ad' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage sponsor ads.', 'sponsor-manager' ) );
	}

	$input = array();
	if ( isset( $_POST['sponsor_ad'] ) && is_array( $_POST['sponsor_ad'] ) ) {
		$input = map_deep( wp_unslash( $_POST['sponsor_ad'] ), 'sanitize_text_field' );
	}

	$title   = sanitize_text_field( $input['title'] ?? '' );
	$company = sanitize_text_field( $input['company'] ?? '' );
	$link    = esc_url_raw( $input['link'] ?? '' );
	$image   = absint( $input['image_id'] ?? 0 );
	$start   = sponsor_manager_input_to_ymd( $input['start'] ?? '' );
	$end     = sponsor_manager_input_to_ymd( $input['end'] ?? '' );
	$priority = isset( $input['priority'] ) ? (int) $input['priority'] : 10;
	$paused  = ! empty( $input['paused'] );
	$term_ids = array_map( 'absint', (array) ( $input['placements'] ?? array() ) );
	$ad_id   = absint( $input['id'] ?? 0 );

	if ( '' === $title ) {
		wp_safe_redirect( sponsor_manager_dashboard_url( array( 'tab' => 'ads', 'error' => 'missing-title', 'ad' => $ad_id ) ) );
		exit;
	}

	$postarr = array(
		'post_type'   => 'sponsor_ad',
		'post_status' => 'publish',
		'post_title'  => $title,
	);

	if ( $ad_id ) {
		$existing = get_post( $ad_id );
		if ( ! $existing || 'sponsor_ad' !== $existing->post_type || ! current_user_can( 'edit_post', $ad_id ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit this ad.', 'sponsor-manager' ) );
		}
		$postarr['ID'] = $ad_id;
	}

	$saved_id = wp_insert_post( $postarr, true );
	if ( is_wp_error( $saved_id ) || ! $saved_id ) {
		wp_safe_redirect( sponsor_manager_dashboard_url( array( 'tab' => 'ads', 'error' => 'save-failed', 'ad' => $ad_id ) ) );
		exit;
	}

	update_post_meta( $saved_id, 'sponsor_name', $company );
	update_post_meta( $saved_id, 'sponsor_url', $link );
	update_post_meta( $saved_id, 'sponsor_start_date', $start );
	update_post_meta( $saved_id, 'sponsor_end_date', $end );
	update_post_meta( $saved_id, 'sponsor_priority', $priority > 0 ? $priority : 10 );
	if ( $paused ) {
		update_post_meta( $saved_id, SPONSOR_MANAGER_LEGACY_PAUSE_META, 1 );
	} else {
		delete_post_meta( $saved_id, SPONSOR_MANAGER_LEGACY_PAUSE_META );
	}

	if ( $image && wp_attachment_is_image( $image ) ) {
		set_post_thumbnail( $saved_id, $image );
	} else {
		delete_post_thumbnail( $saved_id );
	}

	wp_set_object_terms( $saved_id, array_filter( $term_ids ), 'sponsor_placement' );
	sponsor_manager_refresh_status( $saved_id );

	wp_safe_redirect( sponsor_manager_dashboard_url( array( 'tab' => 'ads', 'updated' => 'ad-saved' ) ) );
	exit;
}

/**
 * Move an ad to the trash from the Ads tab.
 */
function sponsor_manager_trash_ad_from_dashboard() {
	if ( ! isset( $_GET['sponsor_manager_trash_ad'] ) ) {
		return;
	}

	check_admin_referer( 'sponsor_manager_trash_ad_' . absint( wp_unslash( $_GET['sponsor_manager_trash_ad'] ) ) );

	$ad_id = absint( wp_unslash( $_GET['sponsor_manager_trash_ad'] ) );

	if ( ! $ad_id || ! current_user_can( 'delete_post', $ad_id ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to delete this ad.', 'sponsor-manager' ) );
	}

	$post = get_post( $ad_id );
	if ( $post && 'sponsor_ad' === $post->post_type ) {
		wp_trash_post( $ad_id );
	}

	wp_safe_redirect( sponsor_manager_dashboard_url( array( 'tab' => 'ads', 'updated' => 'ad-trashed' ) ) );
	exit;
}

/**
 * Create or update a placement from the Placements tab.
 */
function sponsor_manager_save_placement_from_dashboard() {
	if ( ! isset( $_POST['sponsor_manager_save_placement'] ) ) {
		return;
	}

	check_admin_referer( 'sponsor_manager_save_placement' );

	if ( ! current_user_can( 'manage_categories' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage placements.', 'sponsor-manager' ) );
	}

	$input = array();
	if ( isset( $_POST['sponsor_placement'] ) && is_array( $_POST['sponsor_placement'] ) ) {
		$input = map_deep( wp_unslash( $_POST['sponsor_placement'] ), 'sanitize_text_field' );
	}

	$name        = sanitize_text_field( $input['name'] ?? '' );
	$slug        = sanitize_title( $input['slug'] ?? '' );
	$description = sanitize_textarea_field( $input['description'] ?? '' );
	$term_id     = absint( $input['id'] ?? 0 );

	if ( '' === $name ) {
		wp_safe_redirect( sponsor_manager_dashboard_url( array( 'tab' => 'placements', 'error' => 'missing-name', 'placement' => $term_id ) ) );
		exit;
	}

	$args = array(
		'description' => $description,
	);
	if ( $slug ) {
		$args['slug'] = $slug;
	}

	if ( $term_id ) {
		$term = get_term( $term_id, 'sponsor_placement' );
		if ( ! $term || is_wp_error( $term ) ) {
			wp_safe_redirect( sponsor_manager_dashboard_url( array( 'tab' => 'placements', 'error' => 'save-failed' ) ) );
			exit;
		}
		if ( sponsor_manager_is_protected_placement( $term ) ) {
			unset( $args['slug'] );
		}
		$result = wp_update_term( $term_id, 'sponsor_placement', array_merge( array( 'name' => $name ), $args ) );
	} else {
		$result = wp_insert_term( $name, 'sponsor_placement', $args );
	}

	if ( is_wp_error( $result ) ) {
		wp_safe_redirect( sponsor_manager_dashboard_url( array( 'tab' => 'placements', 'error' => 'save-failed', 'placement' => $term_id ) ) );
		exit;
	}

	wp_safe_redirect( sponsor_manager_dashboard_url( array( 'tab' => 'placements', 'updated' => 'placement-saved' ) ) );
	exit;
}

/**
 * Delete a non-protected placement from the Placements tab.
 */
function sponsor_manager_delete_placement_from_dashboard() {
	if ( ! isset( $_GET['sponsor_manager_delete_placement'] ) ) {
		return;
	}

	check_admin_referer( 'sponsor_manager_delete_placement_' . absint( wp_unslash( $_GET['sponsor_manager_delete_placement'] ) ) );

	$term_id = absint( wp_unslash( $_GET['sponsor_manager_delete_placement'] ) );

	if ( ! current_user_can( 'manage_categories' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage placements.', 'sponsor-manager' ) );
	}

	$term = get_term( $term_id, 'sponsor_placement' );
	if ( $term && ! is_wp_error( $term ) && ! sponsor_manager_is_protected_placement( $term ) ) {
		wp_delete_term( $term_id, 'sponsor_placement' );
	}

	wp_safe_redirect( sponsor_manager_dashboard_url( array( 'tab' => 'placements', 'updated' => 'placement-deleted' ) ) );
	exit;
}

/**
 * Flash notices after Ads/Placements CRUD.
 */
function sponsor_manager_render_dashboard_notices() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display-only query args after our own redirect.
	$updated = isset( $_GET['updated'] ) ? sanitize_key( wp_unslash( $_GET['updated'] ) ) : '';
	$error   = isset( $_GET['error'] ) ? sanitize_key( wp_unslash( $_GET['error'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$messages = array(
		'ad-saved'           => __( 'Ad saved.', 'sponsor-manager' ),
		'ad-trashed'         => __( 'Ad moved to trash.', 'sponsor-manager' ),
		'placement-saved'    => __( 'Placement saved.', 'sponsor-manager' ),
		'placement-deleted'  => __( 'Placement deleted.', 'sponsor-manager' ),
	);
	$errors = array(
		'missing-title' => __( 'Please enter an ad name.', 'sponsor-manager' ),
		'missing-name'  => __( 'Please enter a placement name.', 'sponsor-manager' ),
		'save-failed'   => __( 'Could not save. Try a different name or slug.', 'sponsor-manager' ),
	);

	if ( isset( $messages[ $updated ] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $updated ] ) . '</p></div>';
	}
	if ( isset( $errors[ $error ] ) ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $errors[ $error ] ) . '</p></div>';
	}
}

/**
 * Overview shortcuts to Ads and Placements.
 */
function sponsor_manager_render_overview_actions() {
	echo '<p class="sponsor-manager-overview-actions" style="margin:16px 0 8px;">';
	printf(
		'<a class="button button-primary" href="%s">%s</a> ',
		esc_url( sponsor_manager_dashboard_url( array( 'tab' => 'ads', 'ad' => 'new' ) ) ),
		esc_html__( 'Add New Ad', 'sponsor-manager' )
	);
	printf(
		'<a class="button" href="%s">%s</a> ',
		esc_url( sponsor_manager_dashboard_url( array( 'tab' => 'ads' ) ) ),
		esc_html__( 'Manage Ads', 'sponsor-manager' )
	);
	if ( current_user_can( 'manage_categories' ) ) {
		printf(
			'<a class="button" href="%s">%s</a> ',
			esc_url( sponsor_manager_dashboard_url( array( 'tab' => 'placements', 'placement' => 'new' ) ) ),
			esc_html__( 'Add Placement', 'sponsor-manager' )
		);
		printf(
			'<a class="button" href="%s">%s</a>',
			esc_url( sponsor_manager_dashboard_url( array( 'tab' => 'placements' ) ) ),
			esc_html__( 'Manage Placements', 'sponsor-manager' )
		);
	}
	echo '</p>';
}

/**
 * Ads tab: list plus create/edit form.
 */
function sponsor_manager_render_ads_tab() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab state.
	$editing_id = isset( $_GET['ad'] ) ? sanitize_text_field( wp_unslash( $_GET['ad'] ) ) : '';
	$show_form  = 'new' === $editing_id || absint( $editing_id ) > 0;
	$post       = absint( $editing_id ) ? get_post( absint( $editing_id ) ) : null;
	if ( $post && 'sponsor_ad' !== $post->post_type ) {
		$post      = null;
		$show_form = false;
	}

	echo '<div class="sponsor-manager-dashboard-split" style="display:grid;grid-template-columns:minmax(0,1fr);gap:24px;margin-top:20px;max-width:1100px;">';

	if ( $show_form ) {
		sponsor_manager_render_ad_form( $post );
	} else {
		printf(
			'<p><a class="button button-primary" href="%s">%s</a></p>',
			esc_url( sponsor_manager_dashboard_url( array( 'tab' => 'ads', 'ad' => 'new' ) ) ),
			esc_html__( 'Add New Ad', 'sponsor-manager' )
		);
	}

	$ads = get_posts(
		array(
			'post_type'      => 'sponsor_ad',
			'post_status'    => array( 'publish', 'draft', 'future', 'pending', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);

	echo '<h2>' . esc_html__( 'All Ads', 'sponsor-manager' ) . '</h2>';
	echo '<table class="widefat striped"><thead><tr>';
	echo '<th>' . esc_html__( 'Ad', 'sponsor-manager' ) . '</th>';
	echo '<th>' . esc_html__( 'Company', 'sponsor-manager' ) . '</th>';
	echo '<th>' . esc_html__( 'Placement(s)', 'sponsor-manager' ) . '</th>';
	echo '<th>' . esc_html__( 'Status', 'sponsor-manager' ) . '</th>';
	echo '<th>' . esc_html__( 'Runs', 'sponsor-manager' ) . '</th>';
	echo '<th>' . esc_html__( 'Actions', 'sponsor-manager' ) . '</th>';
	echo '</tr></thead><tbody>';

	if ( ! $ads ) {
		echo '<tr><td colspan="6"><em>' . esc_html__( 'No ads yet.', 'sponsor-manager' ) . '</em></td></tr>';
	} else {
		foreach ( $ads as $ad ) {
			$company     = get_post_meta( $ad->ID, 'sponsor_name', true );
			$terms       = get_the_terms( $ad->ID, 'sponsor_placement' );
			$placements  = ( $terms && ! is_wp_error( $terms ) ) ? implode( ', ', wp_list_pluck( $terms, 'name' ) ) : __( 'none assigned', 'sponsor-manager' );
			$status      = sponsor_manager_refresh_status( $ad->ID );
			$start       = get_post_meta( $ad->ID, 'sponsor_start_date', true );
			$end         = get_post_meta( $ad->ID, 'sponsor_end_date', true );
			$runs        = ( $start ? date_i18n( 'M j, Y', strtotime( $start ) ) : '—' ) . ' – ' . ( $end ? date_i18n( 'M j, Y', strtotime( $end ) ) : '—' );
			$edit_url    = sponsor_manager_dashboard_url( array( 'tab' => 'ads', 'ad' => $ad->ID ) );
			$trash_url   = wp_nonce_url( sponsor_manager_dashboard_url( array( 'tab' => 'ads', 'sponsor_manager_trash_ad' => $ad->ID ) ), 'sponsor_manager_trash_ad_' . $ad->ID );

			echo '<tr>';
			printf( '<td><a href="%s"><strong>%s</strong></a></td>', esc_url( $edit_url ), esc_html( get_the_title( $ad ) ) );
			echo '<td>' . esc_html( $company ? $company : '—' ) . '</td>';
			echo '<td>' . esc_html( $placements ) . '</td>';
			echo '<td>' . esc_html( $status ) . '</td>';
			echo '<td>' . esc_html( $runs ) . '</td>';
			echo '<td>';
			printf( '<a href="%s">%s</a> | ', esc_url( $edit_url ), esc_html__( 'Edit', 'sponsor-manager' ) );
			if ( current_user_can( 'delete_post', $ad->ID ) ) {
				printf( '<a href="%s" class="submitdelete">%s</a>', esc_url( $trash_url ), esc_html__( 'Trash', 'sponsor-manager' ) );
			}
			echo '</td>';
			echo '</tr>';
		}
	}

	echo '</tbody></table>';
	echo '</div>';
}

/**
 * Create/edit form for a sponsor ad.
 *
 * @param \WP_Post|null $post Existing ad.
 */
function sponsor_manager_render_ad_form( $post = null ) {
	$is_edit   = $post instanceof WP_Post;
	$title     = $is_edit ? $post->post_title : '';
	$company   = $is_edit ? get_post_meta( $post->ID, 'sponsor_name', true ) : '';
	$link      = $is_edit ? sponsor_manager_get_ad_url( $post->ID ) : '';
	$image_id  = $is_edit ? (int) get_post_thumbnail_id( $post ) : 0;
	$start     = $is_edit ? sponsor_manager_ymd_to_input( get_post_meta( $post->ID, 'sponsor_start_date', true ) ) : '';
	$end       = $is_edit ? sponsor_manager_ymd_to_input( get_post_meta( $post->ID, 'sponsor_end_date', true ) ) : '';
	$priority  = $is_edit ? (int) get_post_meta( $post->ID, 'sponsor_priority', true ) : 10;
	$paused    = $is_edit && (bool) get_post_meta( $post->ID, SPONSOR_MANAGER_LEGACY_PAUSE_META, true );
	$selected  = $is_edit ? wp_get_post_terms( $post->ID, 'sponsor_placement', array( 'fields' => 'ids' ) ) : array();
	$selected  = is_wp_error( $selected ) ? array() : array_map( 'intval', $selected );
	$placements = get_terms( array( 'taxonomy' => 'sponsor_placement', 'hide_empty' => false ) );

	echo '<div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;max-width:720px;">';
	echo '<h2 style="margin-top:0;">' . esc_html( $is_edit ? __( 'Edit Ad', 'sponsor-manager' ) : __( 'Add New Ad', 'sponsor-manager' ) ) . '</h2>';
	echo '<form method="post" action="' . esc_url( sponsor_manager_dashboard_url( array( 'tab' => 'ads' ) ) ) . '">';
	wp_nonce_field( 'sponsor_manager_save_ad' );
	if ( $is_edit ) {
		echo '<input type="hidden" name="sponsor_ad[id]" value="' . esc_attr( (string) $post->ID ) . '" />';
	}
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="sponsor-ad-title"><?php esc_html_e( 'Ad name', 'sponsor-manager' ); ?></label></th>
			<td><input name="sponsor_ad[title]" id="sponsor-ad-title" type="text" class="regular-text" required value="<?php echo esc_attr( $title ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="sponsor-ad-company"><?php esc_html_e( 'Company', 'sponsor-manager' ); ?></label></th>
			<td><input name="sponsor_ad[company]" id="sponsor-ad-company" type="text" class="regular-text" value="<?php echo esc_attr( $company ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="sponsor-ad-link"><?php esc_html_e( 'Sponsor URL', 'sponsor-manager' ); ?></label></th>
			<td><input name="sponsor_ad[link]" id="sponsor-ad-link" type="url" class="regular-text" value="<?php echo esc_attr( $link ); ?>" placeholder="https://" /></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Ad image', 'sponsor-manager' ); ?></th>
			<td>
				<input type="hidden" name="sponsor_ad[image_id]" id="sponsor-ad-image-id" value="<?php echo esc_attr( (string) $image_id ); ?>" />
				<div class="sponsor-manager-image-preview" style="margin-bottom:8px;">
					<?php
					if ( $image_id ) {
						echo wp_get_attachment_image( $image_id, 'medium', false, array( 'style' => 'max-width:320px;height:auto;display:block;' ) );
					}
					?>
				</div>
				<button type="button" class="button sponsor-manager-select-image"><?php esc_html_e( 'Select image', 'sponsor-manager' ); ?></button>
				<button type="button" class="button-link sponsor-manager-remove-image" <?php echo $image_id ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'sponsor-manager' ); ?></button>
				<p class="description"><?php esc_html_e( 'Use a 16:9 image. Source ads are 3840×2160.', 'sponsor-manager' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Placements', 'sponsor-manager' ); ?></th>
			<td>
				<?php if ( is_wp_error( $placements ) || empty( $placements ) ) : ?>
					<p><?php esc_html_e( 'No placements yet. Create one on the Placements tab first.', 'sponsor-manager' ); ?></p>
				<?php else : ?>
					<fieldset>
						<?php foreach ( $placements as $placement ) : ?>
							<label style="display:block;margin-bottom:4px;">
								<input type="checkbox" name="sponsor_ad[placements][]" value="<?php echo esc_attr( (string) $placement->term_id ); ?>" <?php checked( in_array( (int) $placement->term_id, $selected, true ) ); ?> />
								<?php
								$placement_label = $placement->name;
								if ( ! \SponsorManager\Ads\PlacementUsage::is_active( $placement ) ) {
									$placement_label .= ' (' . __( 'inactive', 'sponsor-manager' ) . ')';
								}
								echo esc_html( $placement_label );
								?>
							</label>
						<?php endforeach; ?>
					</fieldset>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="sponsor-ad-start"><?php esc_html_e( 'Start date', 'sponsor-manager' ); ?></label></th>
			<td><input name="sponsor_ad[start]" id="sponsor-ad-start" type="date" value="<?php echo esc_attr( $start ); ?>" />
				<p class="description"><?php esc_html_e( 'Leave blank to go live immediately.', 'sponsor-manager' ); ?></p></td>
		</tr>
		<tr>
			<th scope="row"><label for="sponsor-ad-end"><?php esc_html_e( 'End date', 'sponsor-manager' ); ?></label></th>
			<td><input name="sponsor_ad[end]" id="sponsor-ad-end" type="date" value="<?php echo esc_attr( $end ); ?>" />
				<p class="description"><?php esc_html_e( 'Leave blank to run indefinitely.', 'sponsor-manager' ); ?></p></td>
		</tr>
		<tr>
			<th scope="row"><label for="sponsor-ad-priority"><?php esc_html_e( 'Priority', 'sponsor-manager' ); ?></label></th>
			<td><input name="sponsor_ad[priority]" id="sponsor-ad-priority" type="number" class="small-text" min="1" value="<?php echo esc_attr( (string) ( $priority > 0 ? $priority : 10 ) ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Pause', 'sponsor-manager' ); ?></th>
			<td>
				<label>
					<input name="sponsor_ad[paused]" type="checkbox" value="1" <?php checked( $paused ); ?> />
					<?php esc_html_e( 'Paused (manual override)', 'sponsor-manager' ); ?>
				</label>
			</td>
		</tr>
	</table>
	<?php
	submit_button( $is_edit ? __( 'Update ad', 'sponsor-manager' ) : __( 'Save ad', 'sponsor-manager' ), 'primary', 'sponsor_manager_save_ad' );
	if ( $is_edit ) {
		printf(
			'<p><a href="%s">%s</a></p>',
			esc_url( get_edit_post_link( $post->ID ) ),
			esc_html__( 'Open in the full editor', 'sponsor-manager' )
		);
	}
	echo '</form></div>';
}

/**
 * Placements tab: list plus create/edit form.
 */
function sponsor_manager_render_placements_tab() {
	if ( ! current_user_can( 'manage_categories' ) ) {
		echo '<p>' . esc_html__( 'You do not have permission to manage placements.', 'sponsor-manager' ) . '</p>';
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab state.
	$editing_id = isset( $_GET['placement'] ) ? sanitize_text_field( wp_unslash( $_GET['placement'] ) ) : '';
	$show_form  = 'new' === $editing_id || absint( $editing_id ) > 0;
	$term       = absint( $editing_id ) ? get_term( absint( $editing_id ), 'sponsor_placement' ) : null;
	if ( $term && is_wp_error( $term ) ) {
		$term      = null;
		$show_form = false;
	}

	echo '<div style="margin-top:20px;max-width:900px;">';

	if ( $show_form ) {
		sponsor_manager_render_placement_form( $term );
	} else {
		printf(
			'<p><a class="button button-primary" href="%s">%s</a></p>',
			esc_url( sponsor_manager_dashboard_url( array( 'tab' => 'placements', 'placement' => 'new' ) ) ),
			esc_html__( 'Add Placement', 'sponsor-manager' )
		);
	}

	$terms = get_terms( array( 'taxonomy' => 'sponsor_placement', 'hide_empty' => false ) );
	$usage = \SponsorManager\Ads\PlacementUsage::sync_all();
	echo '<h2>' . esc_html__( 'All Placements', 'sponsor-manager' ) . '</h2>';
	echo '<table class="widefat striped"><thead><tr>';
	echo '<th>' . esc_html__( 'Name', 'sponsor-manager' ) . '</th>';
	echo '<th>' . esc_html__( 'Slug', 'sponsor-manager' ) . '</th>';
	echo '<th>' . esc_html__( 'Status', 'sponsor-manager' ) . '</th>';
	echo '<th>' . esc_html__( 'Used by', 'sponsor-manager' ) . '</th>';
	echo '<th>' . esc_html__( 'Ads', 'sponsor-manager' ) . '</th>';
	echo '<th>' . esc_html__( 'Actions', 'sponsor-manager' ) . '</th>';
	echo '</tr></thead><tbody>';

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		echo '<tr><td colspan="6"><em>' . esc_html__( 'No placements yet.', 'sponsor-manager' ) . '</em></td></tr>';
	} else {
		foreach ( $terms as $placement ) {
			$edit_url = sponsor_manager_dashboard_url( array( 'tab' => 'placements', 'placement' => $placement->term_id ) );
			$used     = isset( $usage[ $placement->slug ] ) ? $usage[ $placement->slug ] : array();
			$active   = sponsor_manager_is_protected_placement( $placement ) || ! empty( $used );
			echo '<tr>';
			printf( '<td><a href="%s"><strong>%s</strong></a></td>', esc_url( $edit_url ), esc_html( $placement->name ) );
			echo '<td><code>' . esc_html( $placement->slug ) . '</code></td>';
			echo '<td>' . esc_html( $active ? __( 'Active', 'sponsor-manager' ) : __( 'Inactive', 'sponsor-manager' ) ) . '</td>';
			echo '<td>' . wp_kses_post( sponsor_manager_placement_usage_html( $used ) ) . '</td>';
			echo '<td>' . esc_html( (string) $placement->count ) . '</td>';
			echo '<td>';
			printf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'sponsor-manager' ) );
			if ( ! sponsor_manager_is_protected_placement( $placement ) ) {
				$delete_url = wp_nonce_url(
					sponsor_manager_dashboard_url( array( 'tab' => 'placements', 'sponsor_manager_delete_placement' => $placement->term_id ) ),
					'sponsor_manager_delete_placement_' . $placement->term_id
				);
				echo ' | ';
				printf( '<a href="%s" class="submitdelete">%s</a>', esc_url( $delete_url ), esc_html__( 'Delete', 'sponsor-manager' ) );
			}
			echo '</td>';
			echo '</tr>';
		}
	}

	echo '</tbody></table></div>';
}

/**
 * Create/edit form for a placement term.
 *
 * @param \WP_Term|null $term Existing term.
 */
function sponsor_manager_render_placement_form( $term = null ) {
	$is_edit     = $term instanceof WP_Term;
	$protected   = $is_edit && sponsor_manager_is_protected_placement( $term );
	$name        = $is_edit ? $term->name : '';
	$slug        = $is_edit ? $term->slug : '';
	$description = $is_edit ? $term->description : '';

	echo '<div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;max-width:640px;margin-bottom:24px;">';
	echo '<h2 style="margin-top:0;">' . esc_html( $is_edit ? __( 'Edit Placement', 'sponsor-manager' ) : __( 'Add Placement', 'sponsor-manager' ) ) . '</h2>';
	echo '<form method="post" action="' . esc_url( sponsor_manager_dashboard_url( array( 'tab' => 'placements' ) ) ) . '">';
	wp_nonce_field( 'sponsor_manager_save_placement' );
	if ( $is_edit ) {
		echo '<input type="hidden" name="sponsor_placement[id]" value="' . esc_attr( (string) $term->term_id ) . '" />';
	}
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="sponsor-placement-name"><?php esc_html_e( 'Name', 'sponsor-manager' ); ?></label></th>
			<td><input name="sponsor_placement[name]" id="sponsor-placement-name" type="text" class="regular-text" required value="<?php echo esc_attr( $name ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="sponsor-placement-slug"><?php esc_html_e( 'Slug', 'sponsor-manager' ); ?></label></th>
			<td>
				<input name="sponsor_placement[slug]" id="sponsor-placement-slug" type="text" class="regular-text" value="<?php echo esc_attr( $slug ); ?>" <?php disabled( $protected ); ?> />
				<?php if ( $protected ) : ?>
					<input type="hidden" name="sponsor_placement[slug]" value="<?php echo esc_attr( $slug ); ?>" />
					<p class="description"><?php esc_html_e( 'The Everywhere placement slug cannot be changed.', 'sponsor-manager' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="sponsor-placement-description"><?php esc_html_e( 'Description', 'sponsor-manager' ); ?></label></th>
			<td><textarea name="sponsor_placement[description]" id="sponsor-placement-description" class="large-text" rows="3"><?php echo esc_textarea( $description ); ?></textarea></td>
		</tr>
	</table>
	<?php
	submit_button( $is_edit ? __( 'Update placement', 'sponsor-manager' ) : __( 'Save placement', 'sponsor-manager' ), 'primary', 'sponsor_manager_save_placement' );
	echo '</form></div>';
}
