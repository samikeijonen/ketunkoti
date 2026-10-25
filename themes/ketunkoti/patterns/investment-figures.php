<?php
/**
 * Title: Sijoituslaskelma
 * Slug: ketunkoti/investment-figures
 * Categories: text
 * Post Types: home
 * Viewport width: 1400
 * Description: Investment figures for a sijoitusasunto - vuokratuotto, kassavirta, oman pääoman tuotto and the taloyhtiö loan interest rate - with a note on how they are calculated. Insert under "Kodin tiedot" on investment homes only.
 *
 * @package Ketunkoti
 */

/**
 * Renders one fact: an icon, a label and a value bound to the home details source.
 *
 * A closure rather than a named function: pattern files are included by the
 * pattern registry, and a global function would fatal if the file were ever
 * included twice.
 *
 * @param string $icon   Name of a registered core icon, for example `core/payment`.
 * @param string $label  Visible label, already translated.
 * @param string $key    Key passed to the bindings source.
 * @param string $source Bindings source slug. Defaults to the general
 *                        `ketunkoti/home-details` source; pass a different
 *                        one (for example `ketunkoti/home-pricing`) so the
 *                        field groups under the matching heading in the
 *                        editor's "connect to a field" picker.
 * @return void
 */
$ketunkoti_investment_figure = static function ( string $icon, string $label, string $key, string $source = 'ketunkoti/home-details' ): void {
	?>
	<!-- wp:group {"className":"home-details__item","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
	<div class="wp-block-group home-details__item">
		<!-- wp:icon {"icon":"<?php echo esc_attr( $icon ); ?>","className":"home-details__icon","style":{"dimensions":{"width":"28px"}},"textColor":"accent-4"} /-->

		<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
		<div class="wp-block-group">
			<!-- wp:paragraph {"className":"home-details__label","fontSize":"small","textColor":"accent-4","style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.08em"}}} -->
			<p class="home-details__label has-accent-4-color has-text-color has-small-font-size" style="text-transform:uppercase;letter-spacing:0.08em"><?php echo esc_html( $label ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"className":"home-details__value","fontSize":"medium","style":{"typography":{"fontWeight":"600"}},"metadata":{"bindings":{"content":{"source":"<?php echo esc_attr( $source ); ?>","args":{"key":"<?php echo esc_attr( $key ); ?>"}}}}} -->
			<p class="home-details__value has-medium-font-size" style="font-weight:600">&#8212;</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
	<?php
};

?>
<!-- wp:group {"className":"investment-figures","align":"wide","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide investment-figures">
	<!-- wp:group {"className":"home-details","backgroundColor":"accent-5","style":{"border":{"width":"1px","radius":"12px"},"spacing":{"blockGap":"var:preset|spacing|40","padding":{"top":"var:preset|spacing|50","right":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50"}}},"borderColor":"accent-6","layout":{"type":"grid","minimumColumnWidth":"15rem"}} -->
	<div class="wp-block-group home-details has-border-color has-accent-6-border-color has-accent-5-background-color has-background" style="border-width:1px;border-radius:12px;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)">
		<?php
		$ketunkoti_investment_figure( 'core/chart-bar', __( 'Vuokratuotto', 'ketunkoti' ), 'home_rental_yield', 'ketunkoti/home-investment' );
		$ketunkoti_investment_figure( 'core/payment', __( 'Kassavirta', 'ketunkoti' ), 'home_cash_flow', 'ketunkoti/home-investment' );
		$ketunkoti_investment_figure( 'core/arrow-up-right', __( 'Oman pääoman tuotto', 'ketunkoti' ), 'home_equity_return', 'ketunkoti/home-investment' );
		$ketunkoti_investment_figure( 'core/receipt', __( 'Taloyhtiölainan korko', 'ketunkoti' ), 'home_loan_interest', 'ketunkoti/home-investment' );
		?>
	</div>
	<!-- /wp:group -->

	<!-- wp:paragraph {"fontSize":"small","textColor":"accent-4","style":{"spacing":{"margin":{"top":"var:preset|spacing|30"}}}} -->
	<p class="has-accent-4-color has-text-color has-small-font-size" style="margin-top:var(--wp--preset--spacing--30)"><?php esc_html_e( 'Laskelmat ennen veroja. Oman pääoman tuotto on ensimmäisen vuoden tuotto myyntihinnalle, ja lyhennykset lasketaan sijoittajan varallisuudeksi.', 'ketunkoti' ); ?></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
