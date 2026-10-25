<?php
/**
 * License key form. The saved key is never printed.
 *
 * @var bool $saved Whether a key is stored.
 *
 * @package SponsorManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$updated = isset( $_GET['updated'] ) ? sanitize_key( wp_unslash( $_GET['updated'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( 'license-saved' === $updated ) {
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'License settings saved.', 'sponsor-manager' ) . '</p></div>';
} elseif ( 'license-invalid' === $updated ) {
	echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'That license key was not saved. Use 16 to 200 letters, numbers, or the symbols _ - . ~ + / =.', 'sponsor-manager' ) . '</p></div>';
}
?>
<div class="card" style="max-width:720px;margin-top:20px;padding:8px 20px 20px;">
	<h2><?php esc_html_e( 'License key', 'sponsor-manager' ); ?></h2>
	<p><?php esc_html_e( 'Sponsor Manager sends this key when it checks updates.bemistech.com for its own package. The key is stored encrypted and is not shown after you save.', 'sponsor-manager' ); ?></p>
	<?php if ( $saved ) : ?>
		<p><strong><?php esc_html_e( 'A license key is saved.', 'sponsor-manager' ); ?></strong></p>
	<?php else : ?>
		<p><?php esc_html_e( 'No license key is saved yet.', 'sponsor-manager' ); ?></p>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=sponsor_manager&tab=license' ) ); ?>">
		<?php wp_nonce_field( 'sponsor_manager_save_license' ); ?>
		<input type="hidden" name="sponsor_manager_save_license" value="1">
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="sponsor-manager-license-key"><?php esc_html_e( 'License key', 'sponsor-manager' ); ?></label></th>
				<td>
					<input type="password" class="regular-text" id="sponsor-manager-license-key" name="sponsor_manager_license_key" value="" autocomplete="new-password" spellcheck="false" data-1p-ignore="true" data-lpignore="true">
					<?php if ( $saved ) : ?>
						<p><label for="sponsor-manager-license-clear"><input type="checkbox" id="sponsor-manager-license-clear" name="sponsor_manager_license_clear" value="1"> <?php esc_html_e( 'Remove the saved key', 'sponsor-manager' ); ?></label></p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Save license', 'sponsor-manager' ) ); ?>
	</form>
</div>
