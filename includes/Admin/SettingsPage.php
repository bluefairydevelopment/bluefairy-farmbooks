<?php
declare(strict_types=1);

namespace BlueFairy\FarmBooks\Admin;

defined( 'ABSPATH' ) || exit;

use BlueFairy\FarmBooks\Settings;

/**
 * FarmBooks Settings admin page.
 *
 * Stores settings in the bffb_settings table (not wp_options).
 * Nonce verified on every POST. All input sanitized per field type.
 */
class SettingsPage {

	private Settings $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;

		add_action( 'admin_post_bffb_save_settings', array( $this, 'handle_save' ) );
	}

	/**
	 * Save handler, on admin-post.php.
	 *
	 * Not in render(): that is a menu-page callback, and admin-header.php has
	 * already sent the whole document head by the time it runs, so a redirect
	 * from there cannot set a header.
	 */
	public function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'bluefairy-farmbooks' ) );
		}

		check_admin_referer( 'bffb_save_settings' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified immediately above.
		$requested_tab = sanitize_key( wp_unslash( $_POST['bffb_active_tab'] ?? 'general' ) );
		$tabs          = $this->tabs();

		// Only the General tab may save these fields, and the test is against
		// the tab the form SAID it was on - not against a validated one.
		//
		// Falling back to 'general' for an unknown slug is right when deciding
		// what to render and wrong when deciding what to write: an unknown tab
		// then saved the General fields from a $_POST that never carried them,
		// blanking farm name, EIN, fiscal year start, tax year and currency.
		// Proven, on the fix that was supposed to prevent exactly that.
		$notice = 'nothing_to_save';

		if ( 'general' === $requested_tab ) {
			$this->save_settings();
			$notice = 'saved';
		}

		// Only a real tab may steer the redirect.
		$active_tab = array_key_exists( $requested_tab, $tabs ) ? $requested_tab : 'general';

		/**
		 * Fires so a tab added via bffb_settings_tabs can save its own fields.
		 *
		 * This handler runs on admin_post_bffb_save_settings at priority 10 and
		 * exits, so an add-on that hooks the same action at the default
		 * priority is appended after it and never runs. This action is the
		 * seam that does: it fires before the redirect, for whatever tab the
		 * form said it was on.
		 *
		 * @since 1.0.1
		 * @param string               $requested_tab The tab slug the form claimed,
		 *                                            sanitize_key()'d but NOT checked
		 *                                            against the registered tabs.
		 *                                            Compare it to your own slug.
		 * @param array<string, mixed> $post          Raw POST. Re-sanitize before use.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by check_admin_referer() above.
		do_action( 'bffb_save_settings_tab', $requested_tab, wp_unslash( $_POST ) );

		/**
		 * Filter the notice shown after a settings save.
		 *
		 * A tab that saved on the action above must be able to say so. Without
		 * this the screen told the user "Nothing was saved" over a save that
		 * had just succeeded, and they re-entered and re-saved in a loop.
		 *
		 * @since 1.0.1
		 * @param string $notice        One of: saved, nothing_to_save.
		 * @param string $requested_tab The tab the form was on.
		 */
		$notice = sanitize_key( (string) apply_filters( 'bffb_settings_saved_notice', $notice, $requested_tab ) );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'bffb-settings',
					'tab'         => $active_tab,
					'bffb_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * The tab list, in one place so render() and handle_save() cannot disagree
	 * about which slugs are real.
	 *
	 * @return array<string, string>
	 */
	private function tabs(): array {
		/**
		 * Filter the list of settings tabs.
		 *
		 * A tab added here renders its own fields and saves them itself, on
		 * the bffb_save_settings_tab action - not by hooking
		 * admin_post_bffb_save_settings, which this class already answers and
		 * exits from. This plugin's own save only ever runs for General.
		 *
		 * @since 1.0.0
		 * @param array<string, string> $tabs Associative array of tab slug => tab label.
		 */
		return apply_filters(
			'bffb_settings_tabs',
			array(
				'general' => __( 'General', 'bluefairy-farmbooks' ),
			)
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'bluefairy-farmbooks' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active_tab = sanitize_key( wp_unslash( $_GET['tab'] ?? 'general' ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice = isset( $_GET['bffb_notice'] ) ? sanitize_key( wp_unslash( $_GET['bffb_notice'] ) ) : '';
		$saved  = 'saved' === $notice;

		$tabs = $this->tabs();

		if ( ! array_key_exists( $active_tab, $tabs ) ) {
			$active_tab = 'general';
		}

		$farm_name         = (string) $this->settings->get( 'farm_name', '' );
		$farm_ein          = (string) $this->settings->get( 'farm_ein', '' );
		$fiscal_year_start = (string) $this->settings->get( 'fiscal_year_start', '1' );
		$tax_year          = (string) $this->settings->get( 'tax_year', (string) (int) wp_date( 'Y' ) );
		$currency_symbol   = (string) $this->settings->get( 'currency_symbol', '$' );

		$months = array(
			'1'  => __( 'January', 'bluefairy-farmbooks' ),
			'2'  => __( 'February', 'bluefairy-farmbooks' ),
			'3'  => __( 'March', 'bluefairy-farmbooks' ),
			'4'  => __( 'April', 'bluefairy-farmbooks' ),
			'5'  => __( 'May', 'bluefairy-farmbooks' ),
			'6'  => __( 'June', 'bluefairy-farmbooks' ),
			'7'  => __( 'July', 'bluefairy-farmbooks' ),
			'8'  => __( 'August', 'bluefairy-farmbooks' ),
			'9'  => __( 'September', 'bluefairy-farmbooks' ),
			'10' => __( 'October', 'bluefairy-farmbooks' ),
			'11' => __( 'November', 'bluefairy-farmbooks' ),
			'12' => __( 'December', 'bluefairy-farmbooks' ),
		);

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'FarmBooks Settings', 'bluefairy-farmbooks' ); ?></h1>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'bluefairy-farmbooks' ); ?></p>
				</div>
			<?php elseif ( 'nothing_to_save' === $notice ) : ?>
				<?php // Saying "Settings saved." over discarded input is worse than saying nothing. ?>
				<div class="notice notice-warning is-dismissible">
					<p><?php esc_html_e( 'Nothing was saved: that tab is provided by another plugin and stores its own settings.', 'bluefairy-farmbooks' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( count( $tabs ) > 1 ) : ?>
			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Settings tabs', 'bluefairy-farmbooks' ); ?>">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a
						href="
						<?php
						echo esc_url(
							add_query_arg(
								array(
									'page' => 'bffb-settings',
									'tab'  => $slug,
								),
								admin_url( 'admin.php' )
							)
						);
						?>
								"
						class="nav-tab<?php echo $active_tab === $slug ? ' nav-tab-active' : ''; ?>"
					><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'bffb_save_settings' ); ?>
				<input type="hidden" name="action" value="bffb_save_settings">
				<?php // Always emitted, not only when a second tab exists: the handler reads it to decide whether it may save. ?>
				<input type="hidden" name="bffb_active_tab" value="<?php echo esc_attr( $active_tab ); ?>">

				<?php if ( 'general' === $active_tab ) : ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="bffb_farm_name"><?php esc_html_e( 'Farm Name', 'bluefairy-farmbooks' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="bffb_farm_name"
								name="bffb_farm_name"
								value="<?php echo esc_attr( $farm_name ); ?>"
								class="regular-text"
								maxlength="191"
							>
							<p class="description">
								<?php esc_html_e( 'Your farm name as it should appear on reports.', 'bluefairy-farmbooks' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="bffb_farm_ein"><?php esc_html_e( 'Farm EIN', 'bluefairy-farmbooks' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="bffb_farm_ein"
								name="bffb_farm_ein"
								value="<?php echo esc_attr( $farm_ein ); ?>"
								class="regular-text"
								maxlength="20"
							>
							<p class="description">
								<?php esc_html_e( 'Employer Identification Number, printed on the Schedule F prep report. Leave blank if you file under your Social Security number.', 'bluefairy-farmbooks' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="bffb_fiscal_year_start"><?php esc_html_e( 'Fiscal Year Start', 'bluefairy-farmbooks' ); ?></label>
						</th>
						<td>
							<select id="bffb_fiscal_year_start" name="bffb_fiscal_year_start">
								<?php foreach ( $months as $value => $label ) : ?>
									<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $fiscal_year_start, $value ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'The month your fiscal year begins. Most farms use January.', 'bluefairy-farmbooks' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="bffb_tax_year"><?php esc_html_e( 'Tax Year', 'bluefairy-farmbooks' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								id="bffb_tax_year"
								name="bffb_tax_year"
								value="<?php echo esc_attr( $tax_year ); ?>"
								class="small-text"
								min="2000"
								max="2099"
								step="1"
							>
							<p class="description">
								<?php esc_html_e( 'The default tax year used when filtering transactions and generating reports.', 'bluefairy-farmbooks' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="bffb_currency_symbol"><?php esc_html_e( 'Currency Symbol', 'bluefairy-farmbooks' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="bffb_currency_symbol"
								name="bffb_currency_symbol"
								value="<?php echo esc_attr( $currency_symbol ); ?>"
								class="small-text"
								maxlength="3"
							>
							<p class="description">
								<?php esc_html_e( 'Currency symbol displayed on reports and the dashboard (e.g. $, £, €). Maximum 3 characters.', 'bluefairy-farmbooks' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php endif; ?>

				<?php submit_button( __( 'Save Settings', 'bluefairy-farmbooks' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Sanitize and persist submitted settings.
	 * Called only after nonce verification in render().
	 */
	private function save_settings(): void {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified via check_admin_referer() in render() before this method is called

		$farm_name = sanitize_text_field(
			wp_unslash( (string) ( $_POST['bffb_farm_name'] ?? '' ) )
		);
		$this->settings->set( 'farm_name', $farm_name );

		// Read by Pro's Schedule F header, which had no way to set it.
		$this->settings->set(
			'farm_ein',
			sanitize_text_field( wp_unslash( (string) ( $_POST['bffb_farm_ein'] ?? '' ) ) )
		);

		$fiscal_year_start_raw = sanitize_key( wp_unslash( $_POST['bffb_fiscal_year_start'] ?? '1' ) );
		$fiscal_year_start     = in_array( $fiscal_year_start_raw, array_map( 'strval', range( 1, 12 ) ), true )
			? $fiscal_year_start_raw
			: '1';
		$this->settings->set( 'fiscal_year_start', $fiscal_year_start );

		$tax_year_raw = absint( wp_unslash( $_POST['bffb_tax_year'] ?? wp_date( 'Y' ) ) );
		$tax_year     = ( $tax_year_raw >= 2000 && $tax_year_raw <= 2099 )
			? (string) $tax_year_raw
			: (string) (int) wp_date( 'Y' );
		$this->settings->set( 'tax_year', $tax_year );

		$currency_symbol = sanitize_text_field(
			wp_unslash( (string) ( $_POST['bffb_currency_symbol'] ?? '$' ) )
		);
		$currency_symbol = mb_substr( $currency_symbol, 0, 3 );
		$this->settings->set( 'currency_symbol', $currency_symbol !== '' ? $currency_symbol : '$' );

        // phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}
