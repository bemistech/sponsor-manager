<?php
/**
 * Styles tab form and live preview.
 *
 * @package SponsorManager
 *
 * @var array $styles Sanitized global styles.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

	if ( isset( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice after redirect.
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Global styles saved.', 'sponsor-manager' ) . '</p></div>';
	}
	?>
	<style>
		.sponsor-manager-styles {
			display: grid;
			grid-template-columns: minmax(0, 1fr) minmax(260px, 340px);
			gap: 40px;
			align-items: start;
			margin-top: 20px;
			max-width: 1100px;
		}
		.sponsor-manager-styles__form { min-width: 0; }
		.sponsor-manager-styles .form-table { width: 100%; margin-top: 0; }
		.sponsor-manager-styles .form-table th { width: 150px; padding-right: 16px; }
		.sponsor-manager-styles .form-table td { min-width: 0; overflow: visible; }
		.sponsor-manager-styles .form-table tr { overflow: visible; }
		.sponsor-manager-styles__preview { min-width: 0; position: sticky; top: 46px; }
		.sponsor-manager-styles__preview-card {
			margin-top: 16px;
			color: #1d2327;
			--tsq-radius: 16px;
			--tsq-shadow-color: #102441;
			--tsq-shadow-hover-color: #1763df;
			--tsq-shadow: 5px 5px 0 0 var(--tsq-shadow-color);
			--tsq-shadow-hover: 7px 7px 0 0 var(--tsq-shadow-hover-color);
		}
		.sponsor-manager-styles__custom[hidden] { display: none; }
		.sponsor-manager-styles__preview-card .sponsor-manager-grid__item,
		.sponsor-manager-styles__preview-card .sponsor-manager-grid__item:hover,
		.sponsor-manager-styles__preview-card .sponsor-manager-grid__item:focus {
			color: inherit;
			background: #fff;
			text-decoration: none;
			outline: none;
		}
		.sponsor-manager-color { position: relative; max-width: 280px; }
		.sponsor-manager-color__toggle {
			display: flex;
			align-items: center;
			gap: 8px;
			width: 100%;
			min-height: 36px;
			padding: 4px 8px;
			background: #fff;
			border: 1px solid #8c8f94;
			border-radius: 4px;
			cursor: pointer;
			text-align: left;
		}
		.sponsor-manager-color__toggle:focus { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; }
		.sponsor-manager-color__chip {
			width: 22px;
			height: 22px;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			background: currentColor;
			flex: 0 0 auto;
		}
		.sponsor-manager-color__label { flex: 1 1 auto; }
		.sponsor-manager-color__chevron { color: #1d2327; }
		.sponsor-manager-color__menu {
			position: absolute;
			z-index: 20;
			top: calc(100% + 4px);
			left: 0;
			right: 0;
			max-height: 240px;
			overflow: auto;
			margin: 0;
			padding: 4px 0;
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			box-shadow: 0 2px 8px rgba(0,0,0,.12);
		}
		.sponsor-manager-color__menu[hidden] { display: none; }
		.sponsor-manager-color__option {
			display: flex;
			align-items: center;
			gap: 8px;
			width: 100%;
			padding: 6px 10px;
			background: none;
			border: 0;
			cursor: pointer;
			text-align: left;
		}
		.sponsor-manager-color__option:hover,
		.sponsor-manager-color__option.is-selected { background: #f0f0f1; }
		.sponsor-manager-color__custom { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 8px; }
		.sponsor-manager-color__custom[hidden] { display: none; }
		.sponsor-manager-color__custom input[type="text"] { width: 120px; max-width: 100%; }
		.sponsor-manager-shadow-grid {
			display: grid;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 10px 16px;
			max-width: 360px;
			margin-top: 12px;
		}
		.sponsor-manager-shadow-grid label { display: block; font-weight: 600; margin-bottom: 4px; }
		.sponsor-manager-shadow-fields[hidden] { display: none; }
		@media screen and (max-width: 960px) {
			.sponsor-manager-styles { grid-template-columns: 1fr; }
			.sponsor-manager-styles__preview { position: static; }
		}
	</style>
	<div class="sponsor-manager-styles">
		<form class="sponsor-manager-styles__form" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=sponsor_manager&tab=styles' ) ); ?>">
			<?php wp_nonce_field( 'sponsor_manager_save_styles' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Theme borders', 'sponsor-manager' ); ?></th>
					<td>
						<input type="hidden" name="sponsor_styles[follow_theme]" value="0" />
						<label>
							<input name="sponsor_styles[follow_theme]" id="sponsor-style-follow-theme" type="checkbox" value="1" <?php checked( ! empty( $styles['follow_theme'] ) ); ?> />
							<?php esc_html_e( 'Use the active theme’s border radius, color, and shadow', 'sponsor-manager' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Town Square cards use a 16px corner and a solid offset shadow. Uncheck this to set a custom border.', 'sponsor-manager' ); ?></p>
					</td>
				</tr>
			</table>
			<div class="sponsor-manager-styles__custom" id="sponsor-style-custom" <?php echo empty( $styles['follow_theme'] ) ? '' : 'hidden'; ?>>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="sponsor-style-radius"><?php esc_html_e( 'Border radius', 'sponsor-manager' ); ?></label></th>
					<td>
						<input name="sponsor_styles[border_radius]" id="sponsor-style-radius" type="number" min="0" max="32" value="<?php echo esc_attr( (string) $styles['border_radius'] ); ?>" class="small-text" />
						<span class="description">px</span>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sponsor-style-width"><?php esc_html_e( 'Border width', 'sponsor-manager' ); ?></label></th>
					<td>
						<input name="sponsor_styles[border_width]" id="sponsor-style-width" type="number" min="0" max="8" value="<?php echo esc_attr( (string) $styles['border_width'] ); ?>" class="small-text" />
						<span class="description">px</span>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Border color', 'sponsor-manager' ); ?></th>
					<td>
						<?php \SponsorManager\Admin\StylesPage::color_control( 'sponsor-style-border-color', 'sponsor_styles[border_color]', $styles['border_color'] ); ?>
						<p class="description"><?php esc_html_e( 'Theme colors show a swatch. Choose Custom to enter your own, or Inherit for the current text color.', 'sponsor-manager' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Drop shadow', 'sponsor-manager' ); ?></th>
					<td>
						<label>
							<input name="sponsor_styles[show_shadow]" id="sponsor-style-shadow" type="checkbox" value="1" <?php checked( $styles['show_shadow'] ); ?> />
							<?php esc_html_e( 'Show a drop shadow on ad cards', 'sponsor-manager' ); ?>
						</label>
						<div class="sponsor-manager-shadow-fields" id="sponsor-style-shadow-fields" <?php echo $styles['show_shadow'] ? '' : 'hidden'; ?>>
							<div class="sponsor-manager-shadow-grid">
								<div>
									<label for="sponsor-style-shadow-x"><?php esc_html_e( 'Horizontal offset', 'sponsor-manager' ); ?></label>
									<input name="sponsor_styles[shadow_offset_x]" id="sponsor-style-shadow-x" type="number" min="-40" max="40" value="<?php echo esc_attr( (string) $styles['shadow_offset_x'] ); ?>" class="small-text" />
									<span class="description">px</span>
								</div>
								<div>
									<label for="sponsor-style-shadow-y"><?php esc_html_e( 'Vertical offset', 'sponsor-manager' ); ?></label>
									<input name="sponsor_styles[shadow_offset_y]" id="sponsor-style-shadow-y" type="number" min="-40" max="40" value="<?php echo esc_attr( (string) $styles['shadow_offset_y'] ); ?>" class="small-text" />
									<span class="description">px</span>
								</div>
								<div>
									<label for="sponsor-style-shadow-blur"><?php esc_html_e( 'Blur', 'sponsor-manager' ); ?></label>
									<input name="sponsor_styles[shadow_blur]" id="sponsor-style-shadow-blur" type="number" min="0" max="60" value="<?php echo esc_attr( (string) $styles['shadow_blur'] ); ?>" class="small-text" />
									<span class="description">px</span>
								</div>
								<div>
									<label for="sponsor-style-shadow-spread"><?php esc_html_e( 'Spread', 'sponsor-manager' ); ?></label>
									<input name="sponsor_styles[shadow_spread]" id="sponsor-style-shadow-spread" type="number" min="-20" max="40" value="<?php echo esc_attr( (string) $styles['shadow_spread'] ); ?>" class="small-text" />
									<span class="description">px</span>
								</div>
							</div>
							<p style="margin:16px 0 8px;"><strong><?php esc_html_e( 'Shadow color', 'sponsor-manager' ); ?></strong></p>
							<?php \SponsorManager\Admin\StylesPage::color_control( 'sponsor-style-shadow-color', 'sponsor_styles[shadow_color]', $styles['shadow_color'] ); ?>
						</div>
					</td>
				</tr>
			</table>
			</div>
			<?php submit_button( __( 'Save styles', 'sponsor-manager' ), 'primary', 'sponsor_manager_save_styles' ); ?>
		</form>

		<div class="sponsor-manager-styles__preview">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Preview', 'sponsor-manager' ); ?></h2>
			<p class="description"><?php esc_html_e( 'These styles apply to every Sponsors block, unless a block opts out and sets its own look.', 'sponsor-manager' ); ?></p>
			<div id="sponsor-manager-style-preview" class="sponsor-manager-styles__preview-card wp-block-sponsor-manager-grid sponsor-manager-grid" style="<?php echo esc_attr( trim( \SponsorManager\Styles\Repository::palette_css() . ';' . \SponsorManager\Styles\Repository::to_css( $styles, true ), ';' ) ); ?>">
				<a href="#" class="sponsor-manager-grid__item" onclick="return false;">
					<span class="sponsor-manager-grid__item-fallback"><?php esc_html_e( 'Sample sponsor ad', 'sponsor-manager' ); ?></span>
				</a>
			</div>
		</div>
	</div>
	<script>
	(function () {
		var preview = document.getElementById('sponsor-manager-style-preview');
		if (!preview) {
			return;
		}

		var follow = document.getElementById('sponsor-style-follow-theme');
		var custom = document.getElementById('sponsor-style-custom');
		var radius = document.getElementById('sponsor-style-radius');
		var width = document.getElementById('sponsor-style-width');
		var shadow = document.getElementById('sponsor-style-shadow');
		var shadowFields = document.getElementById('sponsor-style-shadow-fields');
		var offsetX = document.getElementById('sponsor-style-shadow-x');
		var offsetY = document.getElementById('sponsor-style-shadow-y');
		var blur = document.getElementById('sponsor-style-shadow-blur');
		var spread = document.getElementById('sponsor-style-shadow-spread');

		function resolveColor(id) {
			var input = document.getElementById(id);
			if (!input || !input.value) {
				return 'currentColor';
			}
			var value = input.value.trim();
			var control = input.closest('[data-color-control]');
			if (!control) {
				return value;
			}
			var selected = control.querySelector('.sponsor-manager-color__option.is-selected');
			if (!selected) {
				return value;
			}
			var stored = selected.getAttribute('data-color') || '';
			if (stored === '' ) {
				return 'currentColor';
			}
			if (stored === '__custom') {
				return value || 'currentColor';
			}
			return selected.getAttribute('data-hex') || value;
		}

		function buildShadow(isHover) {
			if (!shadow.checked) {
				return 'none';
			}
			var x = parseInt(offsetX.value, 10) || 0;
			var y = parseInt(offsetY.value, 10) || 0;
			var blurValue = parseInt(blur.value, 10) || 0;
			if (isHover && blurValue === 0) {
				x += x >= 0 ? 2 : -2;
				y += y >= 0 ? 2 : -2;
			} else if (isHover) {
				y += y >= 0 ? 4 : -4;
			}
			var color = resolveColor('sponsor-style-shadow-color');
			if (!color || color === 'currentColor') {
				color = blurValue > 0 ? '#00000061' : (isHover ? '#1763df' : '#102441');
			}
			return x + 'px ' + y + 'px ' + blurValue + 'px ' + (parseInt(spread.value, 10) || 0) + 'px ' + color;
		}

		function updatePreview() {
			if (custom) {
				custom.hidden = follow && follow.checked;
			}
			if (follow && follow.checked) {
				preview.style.setProperty('--sponsor-ad-radius', 'var(--tsq-radius, 16px)');
				preview.style.setProperty('--sponsor-ad-border-width', '2px');
				preview.style.setProperty('--sponsor-ad-border-color', 'var(--tsq-shadow-color, #102441)');
				preview.style.setProperty('--sponsor-ad-border-hover', 'var(--tsq-shadow-hover-color, #1763df)');
				preview.style.setProperty('--sponsor-ad-shadow', 'var(--tsq-shadow, 5px 5px 0 0 #102441)');
				preview.style.setProperty('--sponsor-ad-shadow-hover', 'var(--tsq-shadow-hover, 7px 7px 0 0 #1763df)');
				var themed = preview.querySelector('.sponsor-manager-grid__item');
				if (themed) {
					themed.style.borderRadius = '';
					themed.style.borderWidth = '';
					themed.style.borderStyle = '';
					themed.style.borderColor = '';
					themed.style.boxShadow = '';
				}
				return;
			}
			var x = parseInt(offsetX.value, 10) || 0;
			var y = parseInt(offsetY.value, 10) || 0;
			var blurValue = parseInt(blur.value, 10) || 0;
			var radiusValue = (radius.value || 0) + 'px';
			var widthValue = (width.value || 0) + 'px';
			var borderColor = resolveColor('sponsor-style-border-color');
			var shadowValue = buildShadow(false);
			var item = preview.querySelector('.sponsor-manager-grid__item');
			var hoverShift = 'none';

			preview.style.setProperty('--sponsor-ad-radius', radiusValue);
			preview.style.setProperty('--sponsor-ad-border-width', widthValue);
			preview.style.setProperty('--sponsor-ad-border-color', borderColor);
			preview.style.setProperty('--sponsor-ad-border-hover', (!borderColor || borderColor === 'currentColor') ? '#1763df' : borderColor);
			preview.style.setProperty('--sponsor-ad-shadow', shadowValue);
			preview.style.setProperty('--sponsor-ad-shadow-hover', buildShadow(true));
			preview.style.setProperty('--sponsor-ad-hover-shift', hoverShift);

			if (item) {
				item.style.borderRadius = radiusValue;
				item.style.borderWidth = widthValue;
				item.style.borderStyle = 'solid';
				item.style.borderColor = borderColor;
				item.style.boxShadow = shadowValue;
			}

			if (shadowFields) {
				shadowFields.hidden = !shadow.checked;
			}
		}

		function bindColorControl(control) {
			var valueInput = control.querySelector('input[type="hidden"]');
			var toggle = control.querySelector('.sponsor-manager-color__toggle');
			var menu = control.querySelector('.sponsor-manager-color__menu');
			var options = control.querySelectorAll('.sponsor-manager-color__option');
			var hexInput = control.querySelector('.sponsor-manager-color__hex');
			var picker = control.querySelector('.sponsor-manager-color__picker');
			var custom = control.querySelector('.sponsor-manager-color__custom');
			var chip = control.querySelector('.sponsor-manager-color__toggle .sponsor-manager-color__chip');
			var label = control.querySelector('.sponsor-manager-color__label');

			function expandHex(value) {
				if (value && value.length === 4 && value.indexOf('#') === 0) {
					return '#' + value[1] + value[1] + value[2] + value[2] + value[3] + value[3];
				}
				return value;
			}

			function findOption(value, mode) {
				var key = mode === 'custom' || (value && value.indexOf('#') === 0) ? '__custom' : value;
				return Array.prototype.find.call(options, function (option) {
					return option.getAttribute('data-color') === key;
				}) || options[0];
			}

			function setColor(value, mode) {
				var option = findOption(value, mode);
				var isCustom = option && option.getAttribute('data-color') === '__custom';
				valueInput.value = isCustom ? value : (option ? option.getAttribute('data-color') : '');
				options.forEach(function (item) {
					item.classList.toggle('is-selected', item === option);
				});
				if (label && option) {
					label.textContent = option.getAttribute('data-label') || option.textContent;
				}
				if (chip) {
					chip.style.background = isCustom
						? (value || '#000000')
						: (option.getAttribute('data-hex') || 'currentColor');
				}
				if (custom) {
					custom.hidden = !isCustom;
				}
				if (hexInput && mode !== 'hex' && isCustom) {
					hexInput.value = value.indexOf('#') === 0 ? value : '';
				}
				if (picker && value && value.indexOf('#') === 0) {
					picker.value = expandHex(value);
				}
				updatePreview();
			}

			function closeMenu() {
				if (menu) {
					menu.hidden = true;
				}
				if (toggle) {
					toggle.setAttribute('aria-expanded', 'false');
				}
			}

			if (toggle && menu) {
				toggle.addEventListener('click', function () {
					var open = menu.hidden;
					document.querySelectorAll('.sponsor-manager-color__menu').forEach(function (other) {
						other.hidden = true;
					});
					menu.hidden = !open;
					toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
				});
			}

			options.forEach(function (option) {
				option.addEventListener('click', function () {
					var next = option.getAttribute('data-color') || '';
					if (next === '__custom') {
						setColor(hexInput && hexInput.value ? hexInput.value : (picker ? picker.value : '#000000'), 'custom');
					} else {
						setColor(next);
					}
					closeMenu();
				});
			});

			if (hexInput) {
				hexInput.addEventListener('input', function () {
					var value = hexInput.value.trim();
					if (!value || /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/.test(value)) {
						setColor(value, 'hex');
					}
				});
			}

			if (picker) {
				picker.addEventListener('input', function () {
					setColor(picker.value, 'custom');
				});
			}

			document.addEventListener('click', function (event) {
				if (!control.contains(event.target)) {
					closeMenu();
				}
			});

			setColor(valueInput.value, valueInput.value.indexOf('#') === 0 ? 'custom' : '');
		}

		document.querySelectorAll('[data-color-control]').forEach(bindColorControl);

		if (follow) {
			follow.addEventListener('change', updatePreview);
		}
		[radius, width, shadow, offsetX, offsetY, blur, spread].forEach(function (field) {
			if (field) {
				field.addEventListener('input', updatePreview);
				field.addEventListener('change', updatePreview);
			}
		});
	})();
	</script>
	<?php
