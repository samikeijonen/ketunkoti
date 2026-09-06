<?php
/**
 * Title: Kodin tiedot
 * Slug: ketunkoti/home-details
 * Categories: text
 * Post Types: home
 * Viewport width: 1400
 * Description: Key facts for a home - selling price, debt free price, living area and location - each with an icon.
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
 * @param string $icon  Name of a registered core icon, for example `core/payment`.
 * @param string $label Visible label, already translated.
 * @param string $key   Key passed to the `ketunkoti/home-details` bindings source.
 * @return void
 */
$ketunkoti_home_detail = static function ( string $icon, string $label, string $key ): void {
	?>
	<!-- wp:group {"className":"home-details__item","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
	<div class="wp-block-group home-details__item">
		<!-- wp:icon {"icon":"<?php echo esc_attr( $icon ); ?>","className":"home-details__icon","style":{"dimensions":{"width":"28px"}},"textColor":"accent-4"} /-->

		<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
		<div class="wp-block-group">
			<!-- wp:paragraph {"className":"home-details__label","fontSize":"small","textColor":"accent-4","style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.08em"}}} -->
			<p class="home-details__label has-accent-4-color has-text-color has-small-font-size" style="text-transform:uppercase;letter-spacing:0.08em"><?php echo esc_html( $label ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"className":"home-details__value","fontSize":"medium","fontFamily":"cormorant-garamond","style":{"typography":{"fontWeight":"600"}},"metadata":{"bindings":{"content":{"source":"ketunkoti/home-details","args":{"key":"<?php echo esc_attr( $key ); ?>"}}}}} -->
			<p class="home-details__value has-cormorant-garamond-font-family has-medium-font-size" style="font-weight:600">&#8212;</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
	<?php
};

?>
<!-- wp:group {"className":"home-details","align":"wide","backgroundColor":"accent-5","style":{"border":{"width":"1px","radius":"6px"},"spacing":{"blockGap":"var:preset|spacing|40","padding":{"top":"var:preset|spacing|50","right":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50"}}},"borderColor":"accent-6","layout":{"type":"grid","minimumColumnWidth":"15rem"}} -->
<div class="wp-block-group alignwide home-details has-border-color has-accent-6-border-color has-accent-5-background-color has-background" style="border-width:1px;border-radius:6px;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)">
	<?php
	$ketunkoti_home_detail( 'core/payment', __( 'Myyntihinta', 'ketunkoti' ), 'home_selling_price' );
	$ketunkoti_home_detail( 'core/receipt', __( 'Velaton hinta', 'ketunkoti' ), 'home_debt_free_price' );
	$ketunkoti_home_detail( 'core/block-table', __( 'Asuinpinta-ala', 'ketunkoti' ), 'home_area' );
	$ketunkoti_home_detail( 'core/map-marker', __( 'Sijainti', 'ketunkoti' ), 'home_city' );
	?>
</div>
<!-- /wp:group -->
