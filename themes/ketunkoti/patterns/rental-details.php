<?php
/**
 * Title: Vuokrakodin tiedot
 * Slug: ketunkoti/rental-details
 * Categories: text
 * Post Types: rental
 * Viewport width: 1400
 * Description: Key facts for a rental home - rent, living area, layout, floor, elevator, address, location, availability, lease type, water charge, pets and deposit - each with an icon.
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
$ketunkoti_rental_detail = static function ( string $icon, string $label, string $key, string $source = 'ketunkoti/home-details' ): void {
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
<!-- wp:group {"className":"home-details","align":"wide","backgroundColor":"accent-5","style":{"border":{"width":"1px","radius":"12px"},"spacing":{"blockGap":"var:preset|spacing|40","padding":{"top":"var:preset|spacing|50","right":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50"}}},"borderColor":"accent-6","layout":{"type":"grid","minimumColumnWidth":"15rem"}} -->
<div class="wp-block-group alignwide home-details has-border-color has-accent-6-border-color has-accent-5-background-color has-background" style="border-width:1px;border-radius:12px;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)">
	<?php
	$ketunkoti_rental_detail( 'core/payment', __( 'Vuokra', 'ketunkoti' ), 'home_rent', 'ketunkoti/home-rental' );
	$ketunkoti_rental_detail( 'core/block-table', __( 'Asuinpinta-ala', 'ketunkoti' ), 'home_area' );
	$ketunkoti_rental_detail( 'core/home', __( 'Huoneistoselitelmä', 'ketunkoti' ), 'home_room_layout' );
	$ketunkoti_rental_detail( 'core/arrow-up', __( 'Kerros', 'ketunkoti' ), 'home_floor' );
	$ketunkoti_rental_detail( 'core/chevron-up-down', __( 'Hissi', 'ketunkoti' ), 'home_elevator', 'ketunkoti/home-rental' );
	$ketunkoti_rental_detail( 'core/envelope', __( 'Osoite', 'ketunkoti' ), 'home_address' );
	$ketunkoti_rental_detail( 'core/map-marker', __( 'Sijainti', 'ketunkoti' ), 'home_city' );
	$ketunkoti_rental_detail( 'core/calendar', __( 'Vapautuu', 'ketunkoti' ), 'home_available_from', 'ketunkoti/home-rental' );
	$ketunkoti_rental_detail( 'core/file', __( 'Vuokrasopimus', 'ketunkoti' ), 'home_lease_type', 'ketunkoti/home-rental' );
	$ketunkoti_rental_detail( 'core/receipt', __( 'Vesimaksu', 'ketunkoti' ), 'home_water_charge', 'ketunkoti/home-rental' );
	$ketunkoti_rental_detail( 'core/info', __( 'Lemmikit', 'ketunkoti' ), 'home_pets', 'ketunkoti/home-rental' );
	$ketunkoti_rental_detail( 'core/shield', __( 'Vakuus', 'ketunkoti' ), 'home_deposit', 'ketunkoti/home-rental' );
	?>
</div>
<!-- /wp:group -->
