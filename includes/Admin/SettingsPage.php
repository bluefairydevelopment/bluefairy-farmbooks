<?php
declare(strict_types=1);

namespace BlueFairy\FarmBooks\Admin;

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
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'bluefairy-farmbooks' ) );
		}

		$saved = false;

		if ( ! empty( $_POST['bffb_save_settings'] ) ) {
			check_admin_referer( 'bffb_save_settings' );
			$this->save_settings();
			$saved = true;
		}

		/**
		 * Filter the list of settings tabs.
		 * Pro hooks here to add a WooCommerce Integration tab and a Payroll tab.
		 *
		 * @since 1.0.0
		 * @param array<string, string> $tabs Associative array of tab slug => tab label.
		 */
		$tabs = apply_filters(
			'bffb_settings_tabs',
			array(
				'general' => __( 'General', 'bluefairy-farmbooks' ),
			)
		);

		$active_tab = sanitize_key( wp_unslash( $_GET['tab'] ?? 'general' ) );
		if ( ! array_key_exists( $active_tab, $tabs ) ) {
			$active_tab = 'general';
		}

		$farm_name         = (string) $this->settings->get( 'farm_name', '' );
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

			<form method="post" action="">
				<?php wp_nonce_field( 'bffb_save_settings' ); ?>
				<input type="hidden" name="bffb_save_settings" value="1">
				<?php if ( count( $tabs ) > 1 ) : ?>
					<input type="hidden" name="bffb_active_tab" value="<?php echo esc_attr( $active_tab ); ?>">
				<?php endif; ?>

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
