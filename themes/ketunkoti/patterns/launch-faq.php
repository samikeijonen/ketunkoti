<?php
/**
 * Title: Avajaiset: usein kysytyt
 * Slug: ketunkoti/launch-faq
 * Categories: ketunkoti_page
 * Viewport width: 1400
 * Description: Launch FAQ accordion with FAQ schema enabled.
 *
 * @package Ketunkoti
 */

$ketunkoti_faq = array(
	__( 'Maksaako listalle liittyminen?', 'ketunkoti' ) => __( 'Ei maksa mitään. Kettu ei edes ota tippiä.', 'ketunkoti' ),
	__( 'Sitoudunko ostamaan jotain?', 'ketunkoti' )    => __( 'Et. Katsot, kiinnostaako, ja jos ei, kettu ei loukkaannu (paljon).', 'ketunkoti' ),
	__( 'Missä kohteet sijaitsevat?', 'ketunkoti' )     => __( 'Lahden keskustan kupeessa, hyvien kulkuyhteyksien päässä.', 'ketunkoti' ),
	__( 'Kuinka usein postia tulee?', 'ketunkoti' )     => __( 'Vain kun uusi kohde on tulossa myyntiin.', 'ketunkoti' ),
	__( 'Miten perun tilauksen?', 'ketunkoti' )         => __( 'Jokaisen viestin lopussa on linkki. Yksi klikkaus, eikä kysellä perään.', 'ketunkoti' ),
);

?>
<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|80"}}},"layout":{"type":"constrained","contentSize":"44rem"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--80)">
	<!-- wp:heading -->
	<h2 class="wp-block-heading"><?php echo esc_html__( 'Usein kysytyt', 'ketunkoti' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:accordion {"ketunkotiFaqSchema":true} -->
	<div role="group" class="wp-block-accordion">
		<?php foreach ( $ketunkoti_faq as $ketunkoti_question => $ketunkoti_answer ) : ?>
		<!-- wp:accordion-item {"style":{"border":{"bottom":{"color":"var:preset|color|accent-6","width":"1px"}},"spacing":{"padding":{"bottom":"var:preset|spacing|xl-l"}}}} -->
		<div class="wp-block-accordion-item" style="border-bottom-color:var(--wp--preset--color--accent-6);border-bottom-width:1px;padding-bottom:var(--wp--preset--spacing--xl-l)"><!-- wp:accordion-heading {"fontSize":"large"} -->
		<h3 class="wp-block-accordion-heading has-icon has-icon-right has-large-font-size"><button type="button" class="wp-block-accordion-heading__toggle"><span class="wp-block-accordion-heading__toggle-title"><?php echo esc_html( $ketunkoti_question ); ?></span><span class="wp-block-accordion-heading__toggle-icon" aria-hidden="true">+</span></button></h3>
		<!-- /wp:accordion-heading -->

		<!-- wp:accordion-panel -->
		<div role="region" class="wp-block-accordion-panel"><!-- wp:paragraph -->
		<p><?php echo esc_html( $ketunkoti_answer ); ?></p>
		<!-- /wp:paragraph --></div>
		<!-- /wp:accordion-panel --></div>
		<!-- /wp:accordion-item -->
		<?php endforeach; ?>
	</div>
	<!-- /wp:accordion -->
</div>
<!-- /wp:group -->
